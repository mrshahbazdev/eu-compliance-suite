<?php
/**
 * CSV export.
 *
 * @package EuroComply\Dashboard
 */

declare( strict_types = 1 );

namespace EuroComply\Dashboard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CsvExport {

	public const ACTION = 'eurocomply_dashboard_csv';
	public const NONCE  = 'eurocomply_dashboard_csv';

	private static ?CsvExport $instance = null;

	public static function instance() : CsvExport {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register();
		}
		return self::$instance;
	}

	private function register() : void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	public static function url( string $dataset = 'plugins' ) : string {
		return wp_nonce_url(
			add_query_arg( 'dataset', $dataset, admin_url( 'admin-post.php?action=' . self::ACTION ) ),
			self::NONCE,
			'_wpnonce'
		);
	}

	public function handle() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-dashboard' ), 403 );
		}
		check_admin_referer( self::NONCE );

		$dataset = isset( $_GET['dataset'] ) ? sanitize_key( (string) $_GET['dataset'] ) : 'plugins';
		$limit   = License::is_pro() ? 5000 : 500;

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="eurocomply-dashboard-' . $dataset . '-' . gmdate( 'Ymd-His' ) . '.csv"' );

		$fp = fopen( 'php://output', 'w' );
		if ( ! $fp ) {
			exit;
		}

		if ( 'snapshots' === $dataset ) {
			fputcsv( $fp, array( 'id', 'occurred_at', 'score', 'active_count', 'alert_count' ) );
			foreach ( SnapshotStore::recent( $limit ) as $r ) {
				fputcsv( $fp, array(
					(int) $r['id'],
					(string) $r['occurred_at'],
					(int) $r['score'],
					(int) $r['active_count'],
					(int) $r['alert_count'],
				) );
			}
		} elseif ( 'alerts' === $dataset ) {
			fputcsv( $fp, array( 'plugin', 'severity', 'message', 'link' ) );
			$payload = Aggregator::snapshot_payload();
			foreach ( (array) $payload['alerts'] as $a ) {
				fputcsv( $fp, array(
					(string) ( $a['plugin'] ?? '' ),
					(string) ( $a['severity'] ?? '' ),
					(string) ( $a['message'] ?? '' ),
					(string) ( $a['link'] ?? '' ),
				) );
			}
		} else {
			fputcsv( $fp, array( 'slug', 'name', 'reference', 'active', 'pro', 'score', 'alert_count' ) );
			$payload = Aggregator::snapshot_payload();
			foreach ( (array) $payload['plugins'] as $p ) {
				fputcsv( $fp, array(
					(string) $p['slug'],
					(string) $p['name'],
					(string) $p['reference'],
					! empty( $p['active'] ) ? 1 : 0,
					! empty( $p['pro'] ) ? 1 : 0,
					(int) $p['score'],
					count( (array) $p['alerts'] ),
				) );
			}
		}

		fclose( $fp );
		exit;
	}
}
