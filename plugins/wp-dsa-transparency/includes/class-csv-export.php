<?php
/**
 * CSV export handler (notices + statements + traders + transparency report).
 *
 * Nonce-gated, admin-only. Free cap: 500 rows per export; Pro: 5000 rows.
 *
 * @package EuroComply\DSA
 */

declare( strict_types = 1 );

namespace EuroComply\DSA;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CsvExport {

	public const NONCE       = 'eurocomply_dsa_export';
	public const ACTION      = 'eurocomply_dsa_export';
	public const FREE_LIMIT  = 500;
	public const PRO_LIMIT   = 5000;

	private static ?CsvExport $instance = null;

	public static function instance() : CsvExport {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register();
		}
		return self::$instance;
	}

	private function register() : void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_download' ) );
	}

	public function handle_download() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'eurocomply-dsa' ) );
		}
		check_admin_referer( self::NONCE );

		$dataset = isset( $_GET['dataset'] ) ? sanitize_key( (string) $_GET['dataset'] ) : 'notices';
		$limit   = License::is_pro() ? self::PRO_LIMIT : self::FREE_LIMIT;

		if ( 'report' === $dataset ) {
			$this->stream_report();
			exit;
		}

		$rows = array();
		switch ( $dataset ) {
			case 'statements':
				$rows = StatementStore::recent( $limit );
				break;
			case 'traders':
				$rows = TraderStore::recent( $limit );
				break;
			case 'notices':
			default:
				$rows = NoticeStore::recent( $limit );
				break;
		}

		$this->stream_rows( $rows, $dataset );
		exit;
	}

	/**
	 * @param array<int,array<string,mixed>> $rows
	 */
	private function stream_rows( array $rows, string $dataset ) : void {
		$filename = 'eurocomply-dsa-' . $dataset . '-' . gmdate( 'Ymd-His' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$out = fopen( 'php://output', 'w' );
		if ( false === $out ) {
			return;
		}

		if ( empty( $rows ) ) {
			fputcsv( $out, array( 'no_data' ) );
			fclose( $out );
			return;
		}

		$columns = array_keys( $rows[0] );
		fputcsv( $out, $columns );
		foreach ( $rows as $row ) {
			$line = array();
			foreach ( $columns as $col ) {
				$line[] = isset( $row[ $col ] ) ? (string) $row[ $col ] : '';
			}
			fputcsv( $out, $line );
		}
		fclose( $out );
	}

	private function stream_report() : void {
		$format = isset( $_GET['format'] ) ? sanitize_key( (string) $_GET['format'] ) : 'json';
		$since  = isset( $_GET['since'] ) ? (int) $_GET['since'] : 0;
		$until  = isset( $_GET['until'] ) ? (int) $_GET['until'] : 0;
		if ( $since <= 0 ) {
			$since = strtotime( '-12 months' );
		}
		if ( $until <= 0 || $until < $since ) {
			$until = time();
		}

		$report = TransparencyReport::build( $since, $until );

		nocache_headers();
		if ( 'csv' === $format ) {
			$filename = 'eurocomply-dsa-transparency-report-' . gmdate( 'Ymd-His' ) . '.csv';
			header( 'Content-Type: text/csv; charset=UTF-8' );
			header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
			echo TransparencyReport::to_csv( $report ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		$filename = 'eurocomply-dsa-transparency-report-' . gmdate( 'Ymd-His' ) . '.json';
		header( 'Content-Type: application/json; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		echo TransparencyReport::to_json( $report ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
