<?php
/**
 * CSV / XML / JSON export.
 *
 * @package EuroComply\EUDR
 */

declare( strict_types = 1 );

namespace EuroComply\EUDR;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CsvExport {

	private const NONCE = 'eurocomply_eudr_export';

	private static ?CsvExport $instance = null;

	public static function instance() : CsvExport {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register();
		}
		return self::$instance;
	}

	private function register() : void {
		add_action( 'admin_post_eurocomply_eudr_export',      array( $this, 'handle_csv' ) );
		add_action( 'admin_post_eurocomply_eudr_export_xml',  array( $this, 'handle_xml' ) );
		add_action( 'admin_post_eurocomply_eudr_export_json', array( $this, 'handle_json' ) );
	}

	public function handle_csv() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-eudr' ), 403 );
		}
		check_admin_referer( self::NONCE );

		$dataset = isset( $_POST['dataset'] ) ? sanitize_key( (string) $_POST['dataset'] ) : 'shipments';
		$max     = License::is_pro() ? 5000 : 500;

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="eurocomply-eudr-' . $dataset . '-' . gmdate( 'Y-m-d-His' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		if ( false === $out ) {
			wp_die( esc_html__( 'Unable to open output stream.', 'eurocomply-eudr' ) );
		}

		switch ( $dataset ) {
			case 'suppliers':
				fputcsv( $out, array( 'id', 'name', 'country', 'role', 'address', 'tax_id', 'contact_email' ) );
				foreach ( SupplierStore::all() as $r ) {
					fputcsv( $out, array(
						(string) $r['id'],
						(string) $r['name'],
						(string) $r['country'],
						(string) $r['role'],
						(string) $r['address'],
						(string) $r['tax_id'],
						(string) $r['contact_email'],
					) );
				}
				break;

			case 'plots':
				fputcsv( $out, array( 'id', 'supplier_id', 'country', 'label', 'geom_type', 'lat', 'lng', 'area_ha', 'production_from', 'production_to', 'check' ) );
				foreach ( PlotStore::all() as $r ) {
					fputcsv( $out, array(
						(string) $r['id'],
						(string) $r['supplier_id'],
						(string) $r['country'],
						(string) $r['label'],
						(string) $r['geom_type'],
						(string) $r['lat'],
						(string) $r['lng'],
						(string) $r['area_ha'],
						(string) ( $r['production_from'] ?? '' ),
						(string) ( $r['production_to'] ?? '' ),
						(string) $r['deforestation_check'],
					) );
				}
				break;

			case 'risk':
				fputcsv( $out, array( 'id', 'shipment_id', 'factor', 'level', 'conclusion', 'finding', 'mitigation' ) );
				foreach ( RiskStore::recent( $max ) as $r ) {
					fputcsv( $out, array(
						(string) $r['id'],
						(string) $r['shipment_id'],
						(string) $r['factor'],
						(string) $r['level'],
						(string) $r['conclusion'],
						(string) $r['finding'],
						(string) $r['mitigation'],
					) );
				}
				break;

			case 'shipments':
			default:
				fputcsv( $out, array( 'id', 'year', 'commodity', 'hs_code', 'description', 'quantity', 'unit', 'country_origin', 'risk_level', 'dds_reference', 'dds_status', 'plot_ids', 'upstream_dds' ) );
				foreach ( ShipmentStore::all( $max ) as $r ) {
					fputcsv( $out, array(
						(string) $r['id'],
						(string) $r['year'],
						(string) $r['commodity'],
						(string) $r['hs_code'],
						(string) $r['description'],
						(string) $r['quantity'],
						(string) $r['unit'],
						(string) $r['country_origin'],
						(string) $r['risk_level'],
						(string) $r['dds_reference'],
						(string) $r['dds_status'],
						(string) $r['plot_ids'],
						(string) $r['upstream_dds'],
					) );
				}
				break;
		}
		fclose( $out );
		exit;
	}

	public function handle_xml() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-eudr' ), 403 );
		}
		check_admin_referer( self::NONCE );
		$id  = isset( $_POST['shipment_id'] ) ? (int) $_POST['shipment_id'] : 0;
		$res = DdsBuilder::build( $id );
		if ( null === $res ) {
			wp_die( esc_html__( 'Shipment not found.', 'eurocomply-eudr' ), 404 );
		}
		nocache_headers();
		header( 'Content-Type: application/xml; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="eurocomply-eudr-dds-' . $id . '.xml"' );
		echo (string) $res['xml']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	public function handle_json() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-eudr' ), 403 );
		}
		check_admin_referer( self::NONCE );
		$id  = isset( $_POST['shipment_id'] ) ? (int) $_POST['shipment_id'] : 0;
		$res = DdsBuilder::build( $id );
		if ( null === $res ) {
			wp_die( esc_html__( 'Shipment not found.', 'eurocomply-eudr' ), 404 );
		}
		nocache_headers();
		header( 'Content-Type: application/json; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="eurocomply-eudr-dds-' . $id . '.json"' );
		echo (string) $res['payload']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}
}
