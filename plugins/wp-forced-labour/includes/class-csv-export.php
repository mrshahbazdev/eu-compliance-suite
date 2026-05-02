<?php
/**
 * CSV export.
 *
 * @package EuroComply\ForcedLabour
 */

declare( strict_types = 1 );

namespace EuroComply\ForcedLabour;

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
		add_action( 'admin_post_eurocomply_fl_csv', array( $this, 'export' ) );
	}

	public function export() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden', 'eurocomply-forced-labour' ) );
		}
		$dataset = isset( $_GET['dataset'] ) ? sanitize_key( wp_unslash( (string) $_GET['dataset'] ) ) : 'suppliers';
		$cap     = License::is_pro() ? 5000 : 500;
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="eurocomply-forced-labour-' . $dataset . '-' . gmdate( 'Y-m-d' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		switch ( $dataset ) {
			case 'risks':
				fputcsv( $out, array( 'id', 'supplier_id', 'indicator', 'severity', 'status', 'country', 'sector', 'identified_at', 'resolved_at' ) );
				foreach ( array_slice( RiskStore::all(), 0, $cap ) as $r ) {
					fputcsv( $out, array( $r['id'], $r['supplier_id'], $r['indicator'], $r['severity'], $r['status'], $r['country'], $r['sector'], $r['identified_at'], $r['resolved_at'] ) );
				}
				break;
			case 'audits':
				fputcsv( $out, array( 'id', 'supplier_id', 'scheme', 'audit_date', 'expires_at', 'certificate_no' ) );
				foreach ( array_slice( AuditStore::all(), 0, $cap ) as $r ) {
					fputcsv( $out, array( $r['id'], $r['supplier_id'], $r['scheme'], $r['audit_date'], $r['expires_at'], $r['certificate_no'] ) );
				}
				break;
			case 'submissions':
				fputcsv( $out, array( 'id', 'created_at', 'anonymous', 'country', 'sector', 'indicator', 'status' ) );
				foreach ( array_slice( SubmissionStore::all(), 0, $cap ) as $r ) {
					fputcsv( $out, array( $r['id'], $r['created_at'], $r['submitter_anonymous'], $r['country'], $r['sector'], $r['indicator'], $r['status'] ) );
				}
				break;
			case 'withdrawals':
				fputcsv( $out, array( 'id', 'risk_id', 'supplier_id', 'decision_ref', 'decision_date', 'status', 'units_recalled', 'completed_at' ) );
				foreach ( array_slice( WithdrawalStore::all(), 0, $cap ) as $r ) {
					fputcsv( $out, array( $r['id'], $r['risk_id'], $r['supplier_id'], $r['decision_ref'], $r['decision_date'], $r['status'], $r['units_recalled'], $r['completed_at'] ) );
				}
				break;
			case 'suppliers':
			default:
				fputcsv( $out, array( 'id', 'name', 'country', 'region', 'sector', 'tier', 'risk_score', 'last_audited' ) );
				foreach ( array_slice( SupplierStore::all(), 0, $cap ) as $r ) {
					fputcsv( $out, array( $r['id'], $r['name'], $r['country'], $r['region'], $r['sector'], $r['tier'], $r['risk_score'], $r['last_audited'] ) );
				}
		}
		fclose( $out );
		exit;
	}
}
