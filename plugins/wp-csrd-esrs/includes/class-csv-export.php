<?php
/**
 * CSV / XBRL / JSON export.
 *
 * @package EuroComply\CSRD
 */

declare( strict_types = 1 );

namespace EuroComply\CSRD;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CsvExport {

	private const NONCE = 'eurocomply_csrd_export';

	private static ?CsvExport $instance = null;

	public static function instance() : CsvExport {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register();
		}
		return self::$instance;
	}

	private function register() : void {
		add_action( 'admin_post_eurocomply_csrd_export',      array( $this, 'handle_csv' ) );
		add_action( 'admin_post_eurocomply_csrd_export_xbrl', array( $this, 'handle_xbrl' ) );
		add_action( 'admin_post_eurocomply_csrd_export_json', array( $this, 'handle_json' ) );
	}

	public function handle_csv() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-csrd-esrs' ), 403 );
		}
		check_admin_referer( self::NONCE );

		$dataset = isset( $_POST['dataset'] ) ? sanitize_key( (string) $_POST['dataset'] ) : 'datapoints';
		$max     = License::is_pro() ? 5000 : 500;

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="eurocomply-csrd-' . $dataset . '-' . gmdate( 'Y-m-d-His' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		if ( false === $out ) {
			wp_die( esc_html__( 'Unable to open output stream.', 'eurocomply-csrd-esrs' ) );
		}

		switch ( $dataset ) {
			case 'materiality':
				fputcsv( $out, array( 'id', 'year', 'topic', 'subtopic', 'impact_score', 'financial_score', 'impact_material', 'financial_material', 'horizon', 'value_chain' ) );
				foreach ( MaterialityStore::recent( $max ) as $r ) {
					fputcsv( $out, array(
						(string) $r['id'],
						(string) $r['year'],
						(string) $r['topic'],
						(string) $r['subtopic'],
						(string) $r['impact_score'],
						(string) $r['financial_score'],
						(string) $r['impact_material'],
						(string) $r['financial_material'],
						(string) $r['horizon'],
						(string) $r['value_chain'],
					) );
				}
				break;

			case 'assurance':
				fputcsv( $out, array( 'id', 'year', 'provider', 'level', 'signed_at', 'signatory', 'report_url' ) );
				foreach ( AssuranceStore::recent( $max ) as $r ) {
					fputcsv( $out, array(
						(string) $r['id'],
						(string) $r['year'],
						(string) $r['provider'],
						(string) $r['level'],
						(string) ( $r['signed_at'] ?? '' ),
						(string) $r['signatory'],
						(string) $r['report_url'],
					) );
				}
				break;

			case 'reports':
				fputcsv( $out, array( 'id', 'created_at', 'year', 'datapoints_count', 'material_topics', 'coverage_pct' ) );
				foreach ( ReportStore::recent( $max ) as $r ) {
					fputcsv( $out, array(
						(string) $r['id'],
						(string) $r['created_at'],
						(string) $r['year'],
						(string) $r['datapoints_count'],
						(string) $r['material_topics'],
						(string) $r['coverage_pct'],
					) );
				}
				break;

			case 'datapoints':
			default:
				fputcsv( $out, array( 'id', 'updated_at', 'year', 'datapoint_id', 'value_numeric', 'value_text', 'unit', 'source' ) );
				$rows = DatapointStore::recent( $max );
				foreach ( $rows as $r ) {
					fputcsv( $out, array(
						(string) $r['id'],
						(string) $r['updated_at'],
						(string) $r['year'],
						(string) $r['datapoint_id'],
						null === $r['value_numeric'] ? '' : (string) $r['value_numeric'],
						(string) ( $r['value_text'] ?? '' ),
						(string) $r['unit'],
						(string) $r['source'],
					) );
				}
				break;
		}
		fclose( $out );
		exit;
	}

	public function handle_xbrl() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-csrd-esrs' ), 403 );
		}
		check_admin_referer( self::NONCE );

		$id     = isset( $_POST['report_id'] ) ? (int) $_POST['report_id'] : 0;
		$report = $id > 0 ? ReportStore::get( $id ) : null;
		if ( ! $report || empty( $report['xbrl_envelope'] ) ) {
			wp_die( esc_html__( 'Report not found.', 'eurocomply-csrd-esrs' ), 404 );
		}
		nocache_headers();
		header( 'Content-Type: application/xml; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="eurocomply-csrd-' . (int) $report['year'] . '-' . $id . '.xml"' );
		echo (string) $report['xbrl_envelope']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	public function handle_json() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-csrd-esrs' ), 403 );
		}
		check_admin_referer( self::NONCE );

		$id     = isset( $_POST['report_id'] ) ? (int) $_POST['report_id'] : 0;
		$report = $id > 0 ? ReportStore::get( $id ) : null;
		if ( ! $report || empty( $report['payload'] ) ) {
			wp_die( esc_html__( 'Report not found.', 'eurocomply-csrd-esrs' ), 404 );
		}
		nocache_headers();
		header( 'Content-Type: application/json; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="eurocomply-csrd-' . (int) $report['year'] . '-' . $id . '.json"' );
		echo (string) $report['payload']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}
}
