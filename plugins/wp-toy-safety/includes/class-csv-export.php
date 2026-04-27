<?php
/**
 * CSV / DPP export.
 *
 * @package EuroComply\ToySafety
 */

declare( strict_types = 1 );

namespace EuroComply\ToySafety;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CsvExport {

	private const NONCE = 'eurocomply_toy_export';

	private static ?CsvExport $instance = null;

	public static function instance() : CsvExport {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register();
		}
		return self::$instance;
	}

	private function register() : void {
		add_action( 'admin_post_eurocomply_toy_export',      array( $this, 'handle_csv' ) );
		add_action( 'admin_post_eurocomply_toy_dpp_xml',     array( $this, 'handle_dpp_xml' ) );
		add_action( 'admin_post_eurocomply_toy_dpp_json',    array( $this, 'handle_dpp_json' ) );
	}

	public function handle_csv() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-toy-safety' ), 403 );
		}
		check_admin_referer( self::NONCE );
		$dataset = isset( $_POST['dataset'] ) ? sanitize_key( (string) $_POST['dataset'] ) : 'toys';
		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="eurocomply-toy-' . $dataset . '-' . gmdate( 'Y-m-d-His' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		if ( false === $out ) {
			wp_die( esc_html__( 'Unable to open output stream.', 'eurocomply-toy-safety' ) );
		}
		switch ( $dataset ) {
			case 'substances':
				fputcsv( $out, array( 'id', 'toy_id', 'name', 'cas', 'classification', 'limit_value', 'measured_value', 'pass_fail' ) );
				foreach ( SubstanceStore::all() as $r ) {
					fputcsv( $out, array(
						(string) $r['id'],
						(string) $r['toy_id'],
						(string) $r['name'],
						(string) $r['cas'],
						(string) $r['classification'],
						(string) $r['limit_value'],
						(string) $r['measured_value'],
						(string) $r['pass_fail'],
					) );
				}
				break;
			case 'assessments':
				fputcsv( $out, array( 'id', 'toy_id', 'module', 'notified_body', 'notified_body_id', 'certificate_no', 'issued_at', 'valid_until' ) );
				foreach ( AssessmentStore::all() as $r ) {
					fputcsv( $out, array(
						(string) $r['id'],
						(string) $r['toy_id'],
						(string) $r['module'],
						(string) $r['notified_body'],
						(string) $r['notified_body_id'],
						(string) $r['certificate_no'],
						(string) ( $r['issued_at']   ?? '' ),
						(string) ( $r['valid_until'] ?? '' ),
					) );
				}
				break;
			case 'incidents':
				fputcsv( $out, array( 'id', 'toy_id', 'occurred_at', 'detected_at', 'hazard', 'severity', 'country', 'injuries', 'fatalities', 'notified_at', 'followup_at', 'status' ) );
				foreach ( IncidentStore::all() as $r ) {
					fputcsv( $out, array(
						(string) $r['id'],
						(string) $r['toy_id'],
						(string) ( $r['occurred_at'] ?? '' ),
						(string) ( $r['detected_at'] ?? '' ),
						(string) $r['hazard'],
						(string) $r['severity'],
						(string) $r['country'],
						(string) $r['injuries'],
						(string) $r['fatalities'],
						(string) ( $r['notified_at'] ?? '' ),
						(string) ( $r['followup_at'] ?? '' ),
						(string) $r['status'],
					) );
				}
				break;
			case 'operators':
				fputcsv( $out, array( 'id', 'toy_id', 'role', 'name', 'country', 'address', 'email', 'eori', 'vat' ) );
				foreach ( OperatorStore::all() as $r ) {
					fputcsv( $out, array(
						(string) $r['id'],
						(string) $r['toy_id'],
						(string) $r['role'],
						(string) $r['name'],
						(string) $r['country'],
						(string) $r['address'],
						(string) $r['email'],
						(string) $r['eori'],
						(string) $r['vat'],
					) );
				}
				break;
			case 'toys':
			default:
				fputcsv( $out, array( 'id', 'name', 'model', 'gtin', 'batch', 'age_range', 'under_36', 'origin_country', 'ce_marked', 'status' ) );
				foreach ( ToyStore::all() as $r ) {
					fputcsv( $out, array(
						(string) $r['id'],
						(string) $r['name'],
						(string) $r['model'],
						(string) $r['gtin'],
						(string) $r['batch'],
						(string) $r['age_range'],
						(string) $r['under_36'],
						(string) $r['origin_country'],
						(string) $r['ce_marked'],
						(string) $r['status'],
					) );
				}
				break;
		}
		fclose( $out );
		exit;
	}

	public function handle_dpp_xml() : void {
		$this->dpp( 'xml' );
	}

	public function handle_dpp_json() : void {
		$this->dpp( 'json' );
	}

	private function dpp( string $kind ) : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-toy-safety' ), 403 );
		}
		check_admin_referer( self::NONCE );
		$id = isset( $_POST['toy_id'] ) ? (int) $_POST['toy_id'] : 0;
		$d  = DppBuilder::build( $id );
		if ( null === $d['toy'] ) {
			wp_die( esc_html__( 'Toy not found.', 'eurocomply-toy-safety' ), 404 );
		}
		nocache_headers();
		if ( 'xml' === $kind ) {
			header( 'Content-Type: application/xml; charset=UTF-8' );
			header( 'Content-Disposition: attachment; filename="eurocomply-toy-dpp-' . $id . '.xml"' );
			echo $d['xml']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			header( 'Content-Type: application/json; charset=UTF-8' );
			header( 'Content-Disposition: attachment; filename="eurocomply-toy-dpp-' . $id . '.json"' );
			echo $d['json']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		exit;
	}
}
