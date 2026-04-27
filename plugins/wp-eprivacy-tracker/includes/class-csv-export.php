<?php
/**
 * CSV exports.
 *
 * @package EuroComply\EPrivacy
 */

declare( strict_types = 1 );

namespace EuroComply\EPrivacy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CsvExport {

	private const NONCE = 'eurocomply_eprivacy_export';

	private static ?CsvExport $instance = null;

	public static function instance() : CsvExport {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register();
		}
		return self::$instance;
	}

	private function register() : void {
		add_action( 'admin_post_eurocomply_eprivacy_export', array( $this, 'handle' ) );
	}

	public function handle() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-eprivacy' ), 403 );
		}
		check_admin_referer( self::NONCE );

		$dataset = isset( $_POST['dataset'] ) ? sanitize_key( (string) $_POST['dataset'] ) : 'findings';
		$max     = License::is_pro() ? 5000 : 500;

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="eurocomply-eprivacy-' . $dataset . '-' . gmdate( 'Y-m-d-His' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' );
		if ( false === $out ) {
			wp_die( esc_html__( 'Unable to open output stream.', 'eurocomply-eprivacy' ) );
		}

		switch ( $dataset ) {
			case 'cookies':
				fputcsv( $out, array( 'observed_at', 'page_url', 'cookie_name', 'cookie_domain', 'tracker_slug', 'category' ) );
				foreach ( CookieStore::recent( $max ) as $row ) {
					fputcsv( $out, array(
						(string) $row['observed_at'],
						(string) $row['page_url'],
						(string) $row['cookie_name'],
						(string) $row['cookie_domain'],
						(string) $row['tracker_slug'],
						(string) $row['category'],
					) );
				}
				break;

			case 'scans':
				fputcsv( $out, array( 'id', 'started_at', 'finished_at', 'status', 'urls_scanned', 'findings_count', 'cookies_count', 'notes' ) );
				foreach ( ScanStore::recent( $max ) as $row ) {
					fputcsv( $out, array(
						(string) $row['id'],
						(string) $row['started_at'],
						(string) ( $row['finished_at'] ?? '' ),
						(string) $row['status'],
						(string) $row['urls_scanned'],
						(string) $row['findings_count'],
						(string) ( $row['cookies_count'] ?? '0' ),
						(string) ( $row['notes'] ?? '' ),
					) );
				}
				break;

			case 'findings':
			default:
				fputcsv( $out, array( 'observed_at', 'scan_id', 'page_url', 'tracker_slug', 'tracker_name', 'category', 'consent_required', 'evidence' ) );
				foreach ( FindingStore::recent( $max ) as $row ) {
					$reg = TrackerRegistry::get( (string) $row['tracker_slug'] );
					fputcsv( $out, array(
						(string) $row['observed_at'],
						(string) $row['scan_id'],
						(string) $row['page_url'],
						(string) $row['tracker_slug'],
						$reg ? (string) $reg['name'] : '',
						(string) $row['category'],
						$reg && $reg['consent_required'] ? '1' : '0',
						(string) ( $row['evidence'] ?? '' ),
					) );
				}
				break;
		}

		fclose( $out );
		exit;
	}
}
