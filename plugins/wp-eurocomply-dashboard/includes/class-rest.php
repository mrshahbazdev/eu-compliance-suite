<?php
/**
 * REST API surface for the Compliance Dashboard.
 *
 * Pro-tier reference implementation. Routes are registered on every request
 * but each callback re-checks {@see License::is_pro()} so deactivating the
 * license takes effect immediately without flushing rewrite rules.
 *
 * Routes (namespace `eurocomply/v1`):
 *
 *   GET  /compliance              Full live snapshot payload (plugins + alerts).
 *   GET  /compliance/summary      Headline numbers only (overall, active, alerts).
 *   GET  /snapshots               Paginated history of recorded snapshots.
 *   POST /snapshots               Trigger a snapshot capture now.
 *
 * All callbacks require the `manage_options` capability — the same cap that
 * gates the wp-admin UI — so cookie auth and Application Passwords both work.
 *
 * @package EuroComply\Dashboard
 */

declare( strict_types = 1 );

namespace EuroComply\Dashboard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Rest {

	public const NAMESPACE = 'eurocomply/v1';

	private static ?Rest $instance = null;

	public static function instance() : Rest {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register();
		}
		return self::$instance;
	}

	private function register() : void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() : void {
		register_rest_route(
			self::NAMESPACE,
			'/compliance',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_compliance' ),
				'permission_callback' => array( $this, 'permission_read' ),
				'args'                => array(
					'include_alerts'  => array(
						'type'        => 'boolean',
						'default'     => true,
						'description' => __( 'Whether to include the merged alerts feed.', 'eurocomply-dashboard' ),
					),
					'include_plugins' => array(
						'type'        => 'boolean',
						'default'     => true,
						'description' => __( 'Whether to include per-plugin connector output.', 'eurocomply-dashboard' ),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/compliance/summary',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_summary' ),
				'permission_callback' => array( $this, 'permission_read' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/snapshots',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_snapshots' ),
					'permission_callback' => array( $this, 'permission_read' ),
					'args'                => array(
						'per_page' => array(
							'type'              => 'integer',
							'default'           => 30,
							'minimum'           => 1,
							'maximum'           => 500,
							'sanitize_callback' => 'absint',
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_snapshot' ),
					'permission_callback' => array( $this, 'permission_write' ),
				),
			)
		);
	}

	/**
	 * @return true|\WP_Error
	 */
	public function permission_read( \WP_REST_Request $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'eurocomply_dashboard_forbidden',
				__( 'You do not have permission to read compliance data.', 'eurocomply-dashboard' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}
		if ( ! License::is_pro() ) {
			return new \WP_Error(
				'eurocomply_dashboard_pro_required',
				__( 'The compliance REST API is a Pro feature. Activate a license to enable it.', 'eurocomply-dashboard' ),
				array( 'status' => 402 )
			);
		}
		unset( $request );
		return true;
	}

	/**
	 * @return true|\WP_Error
	 */
	public function permission_write( \WP_REST_Request $request ) {
		return $this->permission_read( $request );
	}

	public function get_compliance( \WP_REST_Request $request ) : \WP_REST_Response {
		$payload = Aggregator::snapshot_payload();

		$include_alerts  = (bool) $request->get_param( 'include_alerts' );
		$include_plugins = (bool) $request->get_param( 'include_plugins' );

		$body = array(
			'overall'      => (int) $payload['overall'],
			'label'        => Aggregator::score_label( (int) $payload['overall'] ),
			'active_count' => (int) $payload['active_count'],
			'total_count'  => (int) $payload['total_count'],
			'alert_count'  => (int) $payload['alert_count'],
			'generated_at' => gmdate( 'c' ),
		);
		if ( $include_plugins ) {
			$body['plugins'] = array_map(
				static function ( array $p ) : array {
					return array(
						'slug'      => (string) $p['slug'],
						'name'      => (string) $p['name'],
						'reference' => (string) $p['reference'],
						'active'    => (bool) $p['active'],
						'pro'       => (bool) $p['pro'],
						'score'     => (int) $p['score'],
						'menu_url'  => (string) $p['menu_url'],
						'metrics'   => (array) $p['metrics'],
						'alerts'    => array_map(
							static function ( array $a ) : array {
								return array(
									'severity' => (string) ( $a['severity'] ?? 'info' ),
									'message'  => (string) ( $a['message'] ?? '' ),
									'link'     => (string) ( $a['link'] ?? '' ),
								);
							},
							(array) $p['alerts']
						),
					);
				},
				(array) $payload['plugins']
			);
		}
		if ( $include_alerts ) {
			$body['alerts'] = array_map(
				static function ( array $a ) : array {
					return array(
						'plugin'   => (string) ( $a['plugin'] ?? '' ),
						'severity' => (string) ( $a['severity'] ?? 'info' ),
						'message'  => (string) ( $a['message'] ?? '' ),
						'link'     => (string) ( $a['link'] ?? '' ),
					);
				},
				(array) $payload['alerts']
			);
		}

		$response = new \WP_REST_Response( $body, 200 );
		$response->header( 'Cache-Control', 'no-store, max-age=0' );
		$response->header( 'X-EuroComply-Schema', 'eurocomply-compliance-1' );
		return $response;
	}

	public function get_summary( \WP_REST_Request $request ) : \WP_REST_Response {
		unset( $request );
		$payload = Aggregator::snapshot_payload();

		$crit = 0;
		$warn = 0;
		$info = 0;
		foreach ( (array) $payload['alerts'] as $a ) {
			$sev = (string) ( $a['severity'] ?? 'info' );
			if ( 'crit' === $sev ) {
				++$crit;
			} elseif ( 'warn' === $sev ) {
				++$warn;
			} else {
				++$info;
			}
		}

		$body = array(
			'overall'      => (int) $payload['overall'],
			'label'        => Aggregator::score_label( (int) $payload['overall'] ),
			'active_count' => (int) $payload['active_count'],
			'total_count'  => (int) $payload['total_count'],
			'alerts'       => array(
				'total' => (int) $payload['alert_count'],
				'crit'  => $crit,
				'warn'  => $warn,
				'info'  => $info,
			),
			'generated_at' => gmdate( 'c' ),
		);
		$response = new \WP_REST_Response( $body, 200 );
		$response->header( 'Cache-Control', 'no-store, max-age=0' );
		$response->header( 'X-EuroComply-Schema', 'eurocomply-compliance-summary-1' );
		return $response;
	}

	public function get_snapshots( \WP_REST_Request $request ) : \WP_REST_Response {
		$per_page = (int) $request->get_param( 'per_page' );
		if ( $per_page <= 0 ) {
			$per_page = 30;
		}
		$rows = SnapshotStore::recent( $per_page );

		$out = array();
		foreach ( $rows as $r ) {
			$out[] = array(
				'id'           => (int) $r['id'],
				'occurred_at'  => (string) $r['occurred_at'],
				'score'        => (int) $r['score'],
				'active_count' => (int) $r['active_count'],
				'alert_count'  => (int) $r['alert_count'],
			);
		}

		$response = new \WP_REST_Response(
			array(
				'count'   => count( $out ),
				'results' => $out,
			),
			200
		);
		$response->header( 'X-EuroComply-Schema', 'eurocomply-snapshots-1' );
		return $response;
	}

	public function create_snapshot( \WP_REST_Request $request ) : \WP_REST_Response {
		unset( $request );
		Aggregator::snapshot();
		$rows = SnapshotStore::recent( 1 );
		$row  = ! empty( $rows ) ? $rows[0] : null;
		return new \WP_REST_Response(
			array(
				'created' => null !== $row,
				'id'      => $row ? (int) $row['id'] : 0,
				'score'   => $row ? (int) $row['score'] : 0,
			),
			201
		);
	}
}
