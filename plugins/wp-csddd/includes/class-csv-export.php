<?php
/**
 * CSV export.
 *
 * @package EuroComply\CSDDD
 */

declare( strict_types = 1 );

namespace EuroComply\CSDDD;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CsvExport {

	private static ?CsvExport $instance = null;

	public static function instance() : CsvExport {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register();
		}
		return self::$instance;
	}

	private function register() : void {
		add_action( 'admin_post_eurocomply_csddd_csv', array( $this, 'export' ) );
	}

	public function export() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden', 'eurocomply-csddd' ) );
		}
		$dataset = isset( $_GET['dataset'] ) ? sanitize_key( wp_unslash( (string) $_GET['dataset'] ) ) : 'suppliers';
		$cap     = License::is_pro() ? 5000 : 500;

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="eurocomply-csddd-' . $dataset . '-' . gmdate( 'Y-m-d' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' );
		switch ( $dataset ) {
			case 'risks':
				$rows = RiskStore::all( $cap );
				fputcsv( $out, array( 'id', 'supplier_id', 'category', 'annex', 'severity', 'likelihood', 'status', 'identified_at', 'resolved_at' ) );
				foreach ( $rows as $r ) {
					fputcsv( $out, array( $r['id'], $r['supplier_id'], $r['category'], $r['annex'], $r['severity'], $r['likelihood'], $r['status'], $r['identified_at'], $r['resolved_at'] ?? '' ) );
				}
				break;
			case 'actions':
				$rows = ActionStore::all( $cap );
				fputcsv( $out, array( 'id', 'risk_id', 'action_type', 'article', 'deadline', 'completed_at', 'owner' ) );
				foreach ( $rows as $r ) {
					fputcsv( $out, array( $r['id'], $r['risk_id'], $r['action_type'], $r['article'], $r['deadline'] ?? '', $r['completed_at'] ?? '', $r['owner'] ) );
				}
				break;
			case 'complaints':
				$rows = ComplaintStore::all( $cap );
				fputcsv( $out, array( 'id', 'created_at', 'anonymous', 'supplier_id', 'category', 'country', 'status' ) );
				foreach ( $rows as $r ) {
					fputcsv( $out, array( $r['id'], $r['created_at'], $r['complainant_anonymous'], $r['supplier_id'], $r['category'], $r['country'], $r['status'] ) );
				}
				break;
			case 'suppliers':
			default:
				$rows = SupplierStore::all( $cap );
				fputcsv( $out, array( 'id', 'external_ref', 'name', 'country', 'tier', 'sector_nace', 'risk_score', 'last_assessed' ) );
				foreach ( $rows as $r ) {
					fputcsv( $out, array( $r['id'], $r['external_ref'], $r['name'], $r['country'], $r['tier'], $r['sector_nace'], $r['risk_score'], $r['last_assessed'] ?? '' ) );
				}
				break;
		}
		fclose( $out );
		exit;
	}
}
