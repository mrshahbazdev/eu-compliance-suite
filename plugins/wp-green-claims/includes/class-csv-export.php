<?php
/**
 * CSV export.
 *
 * @package EuroComply\GreenClaims
 */

declare( strict_types = 1 );

namespace EuroComply\GreenClaims;

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
		add_action( 'admin_post_eurocomply_gc_csv', array( $this, 'export' ) );
	}

	public function export() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden', 'eurocomply-green-claims' ) );
		}
		$dataset = isset( $_GET['dataset'] ) ? sanitize_key( wp_unslash( (string) $_GET['dataset'] ) ) : 'claims';
		$cap     = License::is_pro() ? 5000 : 500;

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="eurocomply-green-claims-' . $dataset . '-' . gmdate( 'Y-m-d' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' );
		switch ( $dataset ) {
			case 'labels':
				fputcsv( $out, array( 'id', 'label_name', 'scheme_owner', 'recognized_eu', 'third_party_verified', 'scheme_url' ) );
				$rows = LabelStore::all();
				$rows = array_slice( $rows, 0, $cap );
				foreach ( $rows as $r ) {
					fputcsv( $out, array( $r['id'], $r['label_name'], $r['scheme_owner'], $r['recognized_eu'], $r['third_party_verified'], $r['scheme_url'] ) );
				}
				break;
			case 'claims':
			default:
				fputcsv( $out, array( 'id', 'product_id', 'claim_text', 'evidence_type', 'evidence_url', 'verifier', 'verified_at', 'expires_at', 'status' ) );
				$rows = ClaimStore::all( $cap );
				foreach ( $rows as $r ) {
					fputcsv( $out, array( $r['id'], $r['product_id'], $r['claim_text'], $r['evidence_type'], $r['evidence_url'], $r['verifier'], $r['verified_at'] ?? '', $r['expires_at'] ?? '', $r['status'] ) );
				}
				break;
		}
		fclose( $out );
		exit;
	}
}
