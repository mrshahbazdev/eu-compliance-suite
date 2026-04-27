<?php
/**
 * CSV export.
 *
 * @package EuroComply\Whistleblower
 */

declare( strict_types = 1 );

namespace EuroComply\Whistleblower;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CsvExport {

	public const ACTION = 'eurocomply_wb_csv';
	public const NONCE  = 'eurocomply_wb_csv';

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

	public static function url( string $dataset = 'reports' ) : string {
		return wp_nonce_url(
			add_query_arg( 'dataset', $dataset, admin_url( 'admin-post.php?action=' . self::ACTION ) ),
			self::NONCE,
			'_wpnonce'
		);
	}

	public function handle() : void {
		if ( ! Recipient::can_view() ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-whistleblower' ), 403 );
		}
		check_admin_referer( self::NONCE );

		$dataset = isset( $_GET['dataset'] ) ? sanitize_key( (string) $_GET['dataset'] ) : 'reports';
		$limit   = License::is_pro() ? 5000 : 500;

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="eurocomply-whistleblower-' . $dataset . '-' . gmdate( 'Ymd-His' ) . '.csv"' );

		$fp = fopen( 'php://output', 'w' );
		if ( ! $fp ) {
			exit;
		}

		if ( 'access_log' === $dataset ) {
			fputcsv( $fp, array( 'id', 'occurred_at', 'report_id', 'user_id', 'user_login', 'action' ) );
			foreach ( AccessLog::recent( $limit ) as $r ) {
				fputcsv( $fp, array(
					(int) $r['id'],
					(string) $r['occurred_at'],
					(int) $r['report_id'],
					(int) $r['user_id'],
					(string) $r['user_login'],
					(string) $r['action'],
				) );
			}
		} else {
			fputcsv( $fp, array( 'id', 'created_at', 'category', 'subject', 'status', 'anonymous', 'acknowledged_at', 'feedback_sent_at', 'closed_at' ) );
			foreach ( ReportStore::recent( $limit ) as $r ) {
				fputcsv( $fp, array(
					(int) $r['id'],
					(string) $r['created_at'],
					(string) $r['category'],
					(string) $r['subject'],
					(string) $r['status'],
					(int) $r['anonymous'],
					(string) ( $r['acknowledged_at'] ?? '' ),
					(string) ( $r['feedback_sent_at'] ?? '' ),
					(string) ( $r['closed_at'] ?? '' ),
				) );
			}
		}

		fclose( $fp );
		exit;
	}
}
