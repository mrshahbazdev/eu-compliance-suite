<?php
/**
 * CSV / XML export.
 *
 * @package EuroComply\CBAM
 */

declare( strict_types = 1 );

namespace EuroComply\CBAM;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CsvExport {

	private const NONCE = 'eurocomply_cbam_export';

	private static ?CsvExport $instance = null;

	public static function instance() : CsvExport {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register();
		}
		return self::$instance;
	}

	private function register() : void {
		add_action( 'admin_post_eurocomply_cbam_export',     array( $this, 'handle_csv' ) );
		add_action( 'admin_post_eurocomply_cbam_export_xml', array( $this, 'handle_xml' ) );
	}

	public function handle_csv() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-cbam' ), 403 );
		}
		check_admin_referer( self::NONCE );

		$dataset = isset( $_POST['dataset'] ) ? sanitize_key( (string) $_POST['dataset'] ) : 'imports';
		$max     = License::is_pro() ? 5000 : 500;

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="eurocomply-cbam-' . $dataset . '-' . gmdate( 'Y-m-d-His' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		if ( false === $out ) {
			wp_die( esc_html__( 'Unable to open output stream.', 'eurocomply-cbam' ) );
		}

		switch ( $dataset ) {
			case 'verifiers':
				fputcsv( $out, array( 'id', 'country', 'name', 'accreditation_id', 'scope', 'contact_email', 'website' ) );
				foreach ( VerifierStore::all() as $r ) {
					fputcsv( $out, array(
						(string) $r['id'],
						(string) $r['country'],
						(string) $r['name'],
						(string) $r['accreditation_id'],
						(string) $r['scope'],
						(string) $r['contact_email'],
						(string) $r['website'],
					) );
				}
				break;

			case 'reports':
				fputcsv( $out, array( 'id', 'created_at', 'period', 'imports_count', 'total_quantity', 'total_direct', 'total_indirect' ) );
				foreach ( ReportStore::recent( $max ) as $r ) {
					fputcsv( $out, array(
						(string) $r['id'],
						(string) $r['created_at'],
						(string) $r['period'],
						(string) $r['imports_count'],
						(string) $r['total_quantity'],
						(string) $r['total_direct'],
						(string) $r['total_indirect'],
					) );
				}
				break;

			case 'imports':
			default:
				fputcsv( $out, array( 'id', 'period', 'cn8', 'category', 'origin_country', 'supplier', 'production_route', 'quantity', 'unit', 'direct_emissions', 'indirect_emissions', 'emissions_verified', 'data_source' ) );
				$rows = ImportStore::recent( $max );
				foreach ( $rows as $r ) {
					fputcsv( $out, array(
						(string) $r['id'],
						(string) $r['period'],
						(string) $r['cn8'],
						(string) $r['category'],
						(string) $r['origin_country'],
						(string) $r['supplier'],
						(string) $r['production_route'],
						(string) $r['quantity'],
						(string) $r['unit'],
						(string) $r['direct_emissions'],
						(string) $r['indirect_emissions'],
						(string) $r['emissions_verified'],
						(string) $r['data_source'],
					) );
				}
				break;
		}
		fclose( $out );
		exit;
	}

	public function handle_xml() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-cbam' ), 403 );
		}
		check_admin_referer( self::NONCE );

		$id = isset( $_POST['report_id'] ) ? (int) $_POST['report_id'] : 0;
		$report = $id > 0 ? ReportStore::get( $id ) : null;
		if ( ! $report || empty( $report['xml_envelope'] ) ) {
			wp_die( esc_html__( 'Report not found.', 'eurocomply-cbam' ), 404 );
		}

		nocache_headers();
		header( 'Content-Type: application/xml; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="eurocomply-cbam-' . $report['period'] . '-' . $id . '.xml"' );
		echo (string) $report['xml_envelope']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}
}
