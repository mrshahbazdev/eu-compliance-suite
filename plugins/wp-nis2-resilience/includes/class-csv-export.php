<?php
/**
 * CSV export for events + incidents.
 *
 * @package EuroComply\NIS2
 */

declare( strict_types = 1 );

namespace EuroComply\NIS2;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CsvExport {

	public const ACTION = 'eurocomply_nis2_csv';
	public const NONCE  = 'eurocomply_nis2_csv';

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

	public static function url( string $dataset = 'events' ) : string {
		return wp_nonce_url(
			add_query_arg( 'dataset', $dataset, admin_url( 'admin-post.php?action=' . self::ACTION ) ),
			self::NONCE,
			'_wpnonce'
		);
	}

	public function handle() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-nis2' ), 403 );
		}
		check_admin_referer( self::NONCE );

		$dataset = isset( $_GET['dataset'] ) ? sanitize_key( (string) $_GET['dataset'] ) : 'events';
		$limit   = License::is_pro() ? 5000 : 500;

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="eurocomply-nis2-' . $dataset . '-' . gmdate( 'Ymd-His' ) . '.csv"' );

		$fp = fopen( 'php://output', 'w' );
		if ( ! $fp ) {
			exit;
		}

		if ( 'incidents' === $dataset ) {
			fputcsv( $fp, array(
				'id',
				'created_at',
				'aware_at',
				'resolved_at',
				'title',
				'category',
				'severity',
				'status',
				'affected_users_estimate',
				'csirt_case_ref',
				'early_warning_sent_at',
				'notification_sent_at',
				'intermediate_sent_at',
				'final_sent_at',
			) );
			foreach ( IncidentStore::recent( $limit ) as $row ) {
				fputcsv( $fp, array(
					(int) $row['id'],
					(string) $row['created_at'],
					(string) ( $row['aware_at'] ?? '' ),
					(string) ( $row['resolved_at'] ?? '' ),
					(string) $row['title'],
					(string) $row['category'],
					(string) $row['severity'],
					(string) $row['status'],
					(int) ( $row['affected_users_estimate'] ?? 0 ),
					(string) ( $row['csirt_case_ref'] ?? '' ),
					(string) ( $row['early_warning_sent_at'] ?? '' ),
					(string) ( $row['notification_sent_at'] ?? '' ),
					(string) ( $row['intermediate_sent_at'] ?? '' ),
					(string) ( $row['final_sent_at'] ?? '' ),
				) );
			}
		} else {
			fputcsv( $fp, array(
				'id',
				'occurred_at',
				'category',
				'severity',
				'action',
				'actor_user_id',
				'actor_login',
				'ip_hash',
				'user_agent',
				'target',
			) );
			foreach ( EventStore::recent( $limit ) as $row ) {
				fputcsv( $fp, array(
					(int) $row['id'],
					(string) $row['occurred_at'],
					(string) $row['category'],
					(string) $row['severity'],
					(string) $row['action'],
					(int) $row['actor_user_id'],
					(string) $row['actor_login'],
					(string) $row['ip_hash'],
					(string) $row['user_agent'],
					(string) $row['target'],
				) );
			}
		}

		fclose( $fp );
		exit;
	}
}
