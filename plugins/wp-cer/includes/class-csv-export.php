<?php
/**
 * CSV / XML / JSON export.
 *
 * @package EuroComply\CER
 */

declare( strict_types = 1 );

namespace EuroComply\CER;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CsvExport {

	private const NONCE = 'eurocomply_cer_export';

	private static ?CsvExport $instance = null;

	public static function instance() : CsvExport {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register();
		}
		return self::$instance;
	}

	private function register() : void {
		add_action( 'admin_post_eurocomply_cer_export',      array( $this, 'handle_csv' ) );
		add_action( 'admin_post_eurocomply_cer_export_xml',  array( $this, 'handle_xml' ) );
		add_action( 'admin_post_eurocomply_cer_export_json', array( $this, 'handle_json' ) );
	}

	public function handle_csv() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-cer' ), 403 );
		}
		check_admin_referer( self::NONCE );
		$dataset = isset( $_POST['dataset'] ) ? sanitize_key( (string) $_POST['dataset'] ) : 'incidents';
		$max     = License::is_pro() ? 5000 : 500;
		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="eurocomply-cer-' . $dataset . '-' . gmdate( 'Y-m-d-His' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		if ( false === $out ) {
			wp_die( esc_html__( 'Unable to open output stream.', 'eurocomply-cer' ) );
		}
		switch ( $dataset ) {
			case 'services':
				fputcsv( $out, array( 'id', 'name', 'sector', 'sub_sector', 'population_served', 'cross_border' ) );
				foreach ( ServiceStore::all() as $r ) {
					fputcsv( $out, array(
						(string) $r['id'],
						(string) $r['name'],
						(string) $r['sector'],
						(string) $r['sub_sector'],
						(string) $r['population_served'],
						(string) $r['cross_border'],
					) );
				}
				break;
			case 'assets':
				fputcsv( $out, array( 'id', 'service_id', 'kind', 'name', 'country', 'address', 'supplier', 'spof' ) );
				foreach ( AssetStore::all() as $r ) {
					fputcsv( $out, array(
						(string) $r['id'],
						(string) $r['service_id'],
						(string) $r['kind'],
						(string) $r['name'],
						(string) $r['country'],
						(string) $r['address'],
						(string) $r['supplier'],
						(string) $r['single_point_of_failure'],
					) );
				}
				break;
			case 'risk':
				fputcsv( $out, array( 'id', 'service_id', 'threat', 'likelihood', 'impact', 'score', 'status', 'next_review' ) );
				foreach ( RiskStore::all() as $r ) {
					fputcsv( $out, array(
						(string) $r['id'],
						(string) $r['service_id'],
						(string) $r['threat'],
						(string) $r['likelihood'],
						(string) $r['impact'],
						(string) $r['score'],
						(string) $r['status'],
						(string) ( $r['next_review'] ?? '' ),
					) );
				}
				break;
			case 'measures':
				fputcsv( $out, array( 'id', 'service_id', 'category', 'measure', 'owner', 'deadline', 'status' ) );
				foreach ( MeasureStore::all() as $r ) {
					fputcsv( $out, array(
						(string) $r['id'],
						(string) $r['service_id'],
						(string) $r['category'],
						(string) $r['measure'],
						(string) $r['owner'],
						(string) ( $r['deadline'] ?? '' ),
						(string) $r['status'],
					) );
				}
				break;
			case 'incidents':
			default:
				fputcsv( $out, array( 'id', 'service_id', 'occurred_at', 'category', 'significant', 'users_affected', 'duration_min', 'cross_border', 'status', 'early_warning_sent_at', 'followup_sent_at' ) );
				foreach ( IncidentStore::recent( $max ) as $r ) {
					fputcsv( $out, array(
						(string) $r['id'],
						(string) $r['service_id'],
						(string) ( $r['occurred_at'] ?? '' ),
						(string) $r['category'],
						(string) $r['significant'],
						(string) $r['users_affected'],
						(string) $r['duration_min'],
						(string) $r['cross_border'],
						(string) $r['status'],
						(string) ( $r['early_warning_sent_at'] ?? '' ),
						(string) ( $r['followup_sent_at'] ?? '' ),
					) );
				}
				break;
		}
		fclose( $out );
		exit;
	}

	public function handle_xml() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-cer' ), 403 );
		}
		check_admin_referer( self::NONCE );
		$id    = isset( $_POST['incident_id'] ) ? (int) $_POST['incident_id'] : 0;
		$stage = isset( $_POST['stage'] )       ? sanitize_key( (string) $_POST['stage'] ) : 'early_warning';
		$res   = ReportBuilder::build( $id, $stage );
		if ( null === $res ) {
			wp_die( esc_html__( 'Incident not found.', 'eurocomply-cer' ), 404 );
		}
		nocache_headers();
		header( 'Content-Type: application/xml; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="eurocomply-cer-' . $stage . '-' . $id . '.xml"' );
		echo (string) $res['xml']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	public function handle_json() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-cer' ), 403 );
		}
		check_admin_referer( self::NONCE );
		$id    = isset( $_POST['incident_id'] ) ? (int) $_POST['incident_id'] : 0;
		$stage = isset( $_POST['stage'] )       ? sanitize_key( (string) $_POST['stage'] ) : 'early_warning';
		$res   = ReportBuilder::build( $id, $stage );
		if ( null === $res ) {
			wp_die( esc_html__( 'Incident not found.', 'eurocomply-cer' ), 404 );
		}
		nocache_headers();
		header( 'Content-Type: application/json; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="eurocomply-cer-' . $stage . '-' . $id . '.json"' );
		echo (string) $res['payload']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}
}
