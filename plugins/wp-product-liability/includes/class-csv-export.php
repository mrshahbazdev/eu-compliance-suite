<?php
/**
 * CSV export.
 *
 * @package EuroComply\ProductLiability
 */

declare( strict_types = 1 );

namespace EuroComply\ProductLiability;

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
		add_action( 'admin_post_eurocomply_pl_export', array( $this, 'export' ) );
	}

	public function export() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '' );
		}
		check_admin_referer( 'eurocomply_pl_export' );
		$dataset = sanitize_key( wp_unslash( (string) ( $_GET['dataset'] ?? 'products' ) ) );
		$cap     = License::is_pro() ? 5000 : 500;
		switch ( $dataset ) {
			case 'defects':
				$rows = array_slice( DefectStore::all(), 0, $cap );
				break;
			case 'claims':
				$rows = array_slice( ClaimStore::all(), 0, $cap );
				break;
			case 'disclosures':
				$rows = array_slice( DisclosureStore::all(), 0, $cap );
				break;
			default:
				$rows    = array_slice( ProductStore::all(), 0, $cap );
				$dataset = 'products';
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=eurocomply-pl-' . $dataset . '-' . gmdate( 'Ymd-His' ) . '.csv' );
		$out = fopen( 'php://output', 'w' );
		if ( ! empty( $rows ) ) {
			fputcsv( $out, array_keys( $rows[0] ) );
			foreach ( $rows as $r ) {
				fputcsv( $out, $r );
			}
		} else {
			fputcsv( $out, array( 'empty' ) );
		}
		fclose( $out );
		exit;
	}
}
