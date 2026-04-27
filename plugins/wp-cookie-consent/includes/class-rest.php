<?php
/**
 * REST endpoint for recording consent choices.
 *
 * @package EuroComply\CookieConsent
 */

declare( strict_types = 1 );

namespace EuroComply\CookieConsent;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exposes POST /wp-json/eurocomply-cc/v1/consent for the banner to log choices.
 */
final class Rest {

	private static ?Rest $instance = null;

	public static function instance() : Rest {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}
		return self::$instance;
	}

	private function boot() : void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() : void {
		register_rest_route(
			'eurocomply-cc/v1',
			'/consent',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'record_consent' ),
				'permission_callback' => array( $this, 'permission' ),
				'args'                => array(
					'consent_id' => array(
						'type'     => 'string',
						'required' => true,
					),
					'state'      => array(
						'type'     => 'object',
						'required' => true,
					),
					'language'   => array(
						'type'     => 'string',
						'required' => false,
					),
					'version'    => array(
						'type'     => 'string',
						'required' => false,
					),
				),
			)
		);
	}

	/**
	 * Verify the nonce issued by wp_localize_script so bots / CSRF requests are rejected.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return bool|WP_Error
	 */
	public function permission( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'eurocomply_cc_invalid_nonce', __( 'Invalid nonce.', 'eurocomply-cookie-consent' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Store a consent log row.
	 */
	public function record_consent( WP_REST_Request $request ) : WP_REST_Response {
		$consent_id = sanitize_text_field( (string) $request->get_param( 'consent_id' ) );
		$version    = sanitize_text_field( (string) ( $request->get_param( 'version' ) ?? '1' ) );
		$language   = sanitize_text_field( (string) ( $request->get_param( 'language' ) ?? '' ) );
		$state      = $request->get_param( 'state' );

		if ( ! is_array( $state ) ) {
			$state = array();
		}
		$clean_state = array();
		foreach ( $state as $key => $value ) {
			$clean_state[ sanitize_key( (string) $key ) ] = (bool) $value;
		}

		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		$id = ConsentLog::insert(
			array(
				'consent_id'      => '' !== $consent_id ? substr( $consent_id, 0, 64 ) : wp_generate_uuid4(),
				'consent_version' => '' !== $version ? substr( $version, 0, 16 ) : '1',
				'ip_hash'         => ConsentLog::hash_ip( $ip ),
				'ua_hash'         => ConsentLog::hash_ua( $ua ),
				'language'        => substr( $language, 0, 8 ),
				'region'          => '',
				'state'           => wp_json_encode( $clean_state ),
			)
		);

		return new WP_REST_Response(
			array(
				'ok' => (bool) $id,
				'id' => $id,
			),
			$id ? 201 : 500
		);
	}
}
