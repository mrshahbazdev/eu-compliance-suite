<?php
/**
 * CSV export for suppliers, repairers, and product meta.
 *
 * @package EuroComply\R2R
 */

declare( strict_types = 1 );

namespace EuroComply\R2R;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CsvExport {

	public const ACTION = 'eurocomply_r2r_csv';
	public const NONCE  = 'eurocomply_r2r_csv';

	private static ?CsvExport $instance = null;

	public static function instance() : CsvExport {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register();
		}
		return self::$instance;
	}

	private function register() : void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	public static function url( string $dataset = 'suppliers' ) : string {
		return wp_nonce_url(
			add_query_arg( 'dataset', $dataset, admin_url( 'admin-post.php?action=' . self::ACTION ) ),
			self::NONCE,
			'_wpnonce'
		);
	}

	public function handle() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-r2r' ), 403 );
		}
		check_admin_referer( self::NONCE );

		$dataset = isset( $_GET['dataset'] ) ? sanitize_key( (string) $_GET['dataset'] ) : 'suppliers';
		$limit   = License::is_pro() ? 5000 : 500;

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="eurocomply-r2r-' . $dataset . '-' . gmdate( 'Ymd-His' ) . '.csv"' );

		$fp = fopen( 'php://output', 'w' );
		if ( ! $fp ) {
			exit;
		}

		if ( 'repairers' === $dataset ) {
			fputcsv( $fp, array( 'id', 'created_at', 'name', 'product_category', 'country', 'city', 'address', 'website', 'email', 'phone', 'certification' ) );
			foreach ( RepairerStore::all( '', '', $limit ) as $r ) {
				fputcsv( $fp, array(
					(int) $r['id'],
					(string) $r['created_at'],
					(string) $r['name'],
					(string) $r['product_category'],
					(string) $r['country'],
					(string) $r['city'],
					(string) $r['address'],
					(string) $r['website'],
					(string) $r['email'],
					(string) $r['phone'],
					(string) $r['certification'],
				) );
			}
		} elseif ( 'products' === $dataset ) {
			fputcsv( $fp, array( 'product_id', 'title', 'category', 'energy_class', 'energy_kwh', 'repair_index', 'disassembly_score', 'spare_parts_years', 'warranty_years', 'eprel_id', 'spare_parts_url', 'repair_manual_url' ) );
			$products = get_posts( array(
				'post_type'      => 'product',
				'posts_per_page' => min( $limit, 500 ),
				'post_status'    => 'any',
				'fields'         => 'ids',
				'no_found_rows'  => true,
			) );
			foreach ( (array) $products as $pid ) {
				$m = ProductMeta::get_for_product( (int) $pid );
				fputcsv( $fp, array(
					(int) $pid,
					get_the_title( (int) $pid ),
					(string) $m['category'],
					(string) $m['energy_class'],
					(int) $m['energy_kwh'],
					(string) $m['repair_index'],
					(string) $m['disassembly_score'],
					(int) $m['spare_parts_years'],
					(int) $m['warranty_years'],
					(string) $m['eprel_id'],
					(string) $m['spare_parts_url'],
					(string) $m['repair_manual_url'],
				) );
			}
		} else {
			fputcsv( $fp, array( 'id', 'created_at', 'name', 'product_category', 'country', 'availability_years', 'website', 'email', 'phone' ) );
			foreach ( SparePartsStore::all( '', '', $limit ) as $r ) {
				fputcsv( $fp, array(
					(int) $r['id'],
					(string) $r['created_at'],
					(string) $r['name'],
					(string) $r['product_category'],
					(string) $r['country'],
					(int) $r['availability_years'],
					(string) $r['website'],
					(string) $r['email'],
					(string) $r['phone'],
				) );
			}
		}

		fclose( $fp );
		exit;
	}
}
