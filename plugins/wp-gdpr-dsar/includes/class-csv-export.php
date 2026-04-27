<?php
/**
 * CSV export for DSAR request log.
 *
 * @package EuroComply\DSAR
 */

declare( strict_types = 1 );

namespace EuroComply\DSAR;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CsvExport {

	public const ACTION = 'eurocomply_dsar_csv';
	public const NONCE  = 'eurocomply_dsar_csv';

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

	public static function url() : string {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=' . self::ACTION ),
			self::NONCE,
			'_wpnonce'
		);
	}

	public function handle() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-dsar' ), 403 );
		}
		check_admin_referer( self::NONCE );

		$limit = License::is_pro() ? 5000 : 500;
		$rows  = RequestStore::recent( $limit );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="eurocomply-dsar-' . gmdate( 'Ymd-His' ) . '.csv"' );

		$fp = fopen( 'php://output', 'w' );
		if ( ! $fp ) {
			exit;
		}

		fputcsv( $fp, array(
			'id',
			'submitted_at',
			'updated_at',
			'deadline_at',
			'completed_at',
			'request_type',
			'status',
			'verified',
			'user_id',
			'requester_email',
			'requester_name',
			'ip_hash',
			'handler_user_id',
			'export_path',
		) );

		foreach ( $rows as $row ) {
			fputcsv( $fp, array(
				(int) $row['id'],
				(string) $row['submitted_at'],
				(string) $row['updated_at'],
				(string) $row['deadline_at'],
				(string) ( $row['completed_at'] ?? '' ),
				(string) $row['request_type'],
				(string) $row['status'],
				(int) $row['verified'],
				(int) $row['user_id'],
				(string) $row['requester_email'],
				(string) $row['requester_name'],
				(string) $row['ip_hash'],
				(int) $row['handler_user_id'],
				(string) $row['export_path'],
			) );
		}

		fclose( $fp );
		exit;
	}
}
