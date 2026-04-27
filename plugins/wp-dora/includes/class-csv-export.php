<?php
/**
 * CSV / XML / JSON export.
 *
 * @package EuroComply\DORA
 */

declare( strict_types = 1 );

namespace EuroComply\DORA;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CsvExport {

	private const NONCE = 'eurocomply_dora_export';

	private static ?CsvExport $instance = null;

	public static function instance() : CsvExport {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register();
		}
		return self::$instance;
	}

	private function register() : void {
		add_action( 'admin_post_eurocomply_dora_export',      array( $this, 'handle_csv' ) );
		add_action( 'admin_post_eurocomply_dora_export_xml',  array( $this, 'handle_xml' ) );
		add_action( 'admin_post_eurocomply_dora_export_json', array( $this, 'handle_json' ) );
	}

	public function handle_csv() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-dora' ), 403 );
		}
		check_admin_referer( self::NONCE );
		$dataset = isset( $_POST['dataset'] ) ? sanitize_key( (string) $_POST['dataset'] ) : 'incidents';
		$max     = License::is_pro() ? 5000 : 500;
		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="eurocomply-dora-' . $dataset . '-' . gmdate( 'Y-m-d-His' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		if ( false === $out ) {
			wp_die( esc_html__( 'Unable to open output stream.', 'eurocomply-dora' ) );
		}
		switch ( $dataset ) {
			case 'third_parties':
				fputcsv( $out, array( 'id', 'name', 'lei', 'country', 'tier', 'critical', 'services', 'contract_ref', 'next_review' ) );
				foreach ( ThirdPartyStore::all() as $r ) {
					fputcsv( $out, array(
						(string) $r['id'],
						(string) $r['name'],
						(string) $r['lei'],
						(string) $r['country'],
						(string) $r['criticality_tier'],
						(string) $r['supports_critical'],
						(string) $r['services'],
						(string) $r['contract_ref'],
						(string) ( $r['next_review'] ?? '' ),
					) );
				}
				break;

			case 'tests':
				fputcsv( $out, array( 'id', 'type', 'scope', 'conducted_at', 'finding_count', 'critical_findings', 'status' ) );
				foreach ( TestStore::all() as $r ) {
					fputcsv( $out, array(
						(string) $r['id'],
						(string) $r['type'],
						(string) $r['scope'],
						(string) ( $r['conducted_at'] ?? '' ),
						(string) $r['finding_count'],
						(string) $r['critical_findings'],
						(string) $r['status'],
					) );
				}
				break;

			case 'policies':
				fputcsv( $out, array( 'id', 'control_area', 'policy_name', 'version', 'owner', 'last_review', 'next_review', 'status' ) );
				foreach ( PolicyStore::all() as $r ) {
					fputcsv( $out, array(
						(string) $r['id'],
						(string) $r['control_area'],
						(string) $r['policy_name'],
						(string) $r['version'],
						(string) $r['owner'],
						(string) ( $r['last_review'] ?? '' ),
						(string) ( $r['next_review'] ?? '' ),
						(string) $r['status'],
					) );
				}
				break;

			case 'intel':
				fputcsv( $out, array( 'id', 'created_at', 'direction', 'source', 'tlp', 'summary' ) );
				foreach ( IntelStore::recent( $max ) as $r ) {
					fputcsv( $out, array(
						(string) $r['id'],
						(string) $r['created_at'],
						(string) $r['direction'],
						(string) $r['source'],
						(string) $r['tlp'],
						(string) $r['summary'],
					) );
				}
				break;

			case 'incidents':
			default:
				fputcsv( $out, array( 'id', 'occurred_at', 'classification', 'category', 'severity', 'clients_affected', 'data_loss', 'duration_min', 'geo_spread', 'financial_impact', 'critical_service', 'status', 'initial_sent_at', 'intermediate_sent_at', 'final_sent_at' ) );
				foreach ( IncidentStore::recent( $max ) as $r ) {
					fputcsv( $out, array(
						(string) $r['id'],
						(string) ( $r['occurred_at'] ?? '' ),
						(string) $r['classification'],
						(string) $r['category'],
						(string) $r['severity'],
						(string) $r['clients_affected'],
						(string) $r['data_loss'],
						(string) $r['duration_min'],
						(string) $r['geo_spread'],
						(string) $r['financial_impact'],
						(string) $r['critical_service'],
						(string) $r['status'],
						(string) ( $r['initial_sent_at'] ?? '' ),
						(string) ( $r['intermediate_sent_at'] ?? '' ),
						(string) ( $r['final_sent_at'] ?? '' ),
					) );
				}
				break;
		}
		fclose( $out );
		exit;
	}

	public function handle_xml() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-dora' ), 403 );
		}
		check_admin_referer( self::NONCE );
		$id    = isset( $_POST['incident_id'] ) ? (int) $_POST['incident_id'] : 0;
		$stage = isset( $_POST['stage'] )       ? sanitize_key( (string) $_POST['stage'] ) : 'initial';
		$res   = ReportBuilder::build( $id, $stage );
		if ( null === $res ) {
			wp_die( esc_html__( 'Incident not found.', 'eurocomply-dora' ), 404 );
		}
		nocache_headers();
		header( 'Content-Type: application/xml; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="eurocomply-dora-' . $stage . '-' . $id . '.xml"' );
		echo (string) $res['xml']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	public function handle_json() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-dora' ), 403 );
		}
		check_admin_referer( self::NONCE );
		$id    = isset( $_POST['incident_id'] ) ? (int) $_POST['incident_id'] : 0;
		$stage = isset( $_POST['stage'] )       ? sanitize_key( (string) $_POST['stage'] ) : 'initial';
		$res   = ReportBuilder::build( $id, $stage );
		if ( null === $res ) {
			wp_die( esc_html__( 'Incident not found.', 'eurocomply-dora' ), 404 );
		}
		nocache_headers();
		header( 'Content-Type: application/json; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="eurocomply-dora-' . $stage . '-' . $id . '.json"' );
		echo (string) $res['payload']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}
}
