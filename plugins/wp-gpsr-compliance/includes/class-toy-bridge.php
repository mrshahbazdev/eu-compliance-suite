<?php
/**
 * Bridge from EuroComply Toy Safety (#25) to EuroComply GPSR (#4).
 *
 * Listens for `eurocomply_toy_saved` and pushes the toy register's
 * Art. 11 warnings + traceability identifier (batch / lot) into the
 * matching WooCommerce product's GPSR meta. The Toy Safety Regulation
 * is lex specialis vs. GPSR but the same product-safety obligations
 * (warnings, traceability) double up under both regimes; this bridge
 * keeps a single edit propagating to both records.
 *
 * Match strategy:
 *   1. `linked_product_id` on the toy row (explicit link)
 *   2. WooCommerce SKU == normalised toy GTIN (implicit fallback)
 *
 * Degrades gracefully: no listener attaches when the Toy Safety
 * sister plugin is missing because the action never fires.
 *
 * @package EuroComply\Gpsr
 */

declare( strict_types = 1 );

namespace EuroComply\Gpsr;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ToyBridge {

	private const TOY_STORE_FQN = '\\EuroComply\\ToySafety\\ToyStore';

	public static function register() : void {
		add_action( 'eurocomply_toy_saved', array( __CLASS__, 'on_toy_saved' ), 10, 2 );
	}

	public static function toy_active() : bool {
		return class_exists( self::TOY_STORE_FQN );
	}

	/**
	 * Resolve the Woo product id this toy row should sync to.
	 *
	 * @param array<string,mixed> $row
	 */
	public static function resolve_product_id( array $row ) : int {
		$linked = isset( $row['linked_product_id'] ) ? (int) $row['linked_product_id'] : 0;
		if ( $linked > 0 && 'product' === get_post_type( $linked ) ) {
			return $linked;
		}
		$gtin = isset( $row['gtin'] ) ? preg_replace( '/[^0-9]/', '', (string) $row['gtin'] ) : '';
		if ( '' === $gtin ) {
			return 0;
		}
		global $wpdb;
		$id = (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value = %s LIMIT 1",
			$gtin
		) );
		if ( $id > 0 && 'product' === get_post_type( $id ) ) {
			return $id;
		}
		return 0;
	}

	/**
	 * Handler for eurocomply_toy_saved.
	 *
	 * @param int                 $toy_id Toy row id.
	 * @param array<string,mixed> $row    Persisted toy row.
	 */
	public static function on_toy_saved( int $toy_id, array $row ) : void {
		if ( $toy_id <= 0 || empty( $row ) ) {
			return;
		}
		$product_id = self::resolve_product_id( $row );
		if ( $product_id <= 0 ) {
			return;
		}

		$warnings = trim( (string) ( $row['warnings'] ?? '' ) );
		if ( '' !== $warnings ) {
			$existing = (string) get_post_meta( $product_id, '_gpsr_warnings', true );
			if ( $existing !== $warnings ) {
				update_post_meta( $product_id, '_gpsr_warnings', $warnings );
			}
		}

		$batch = trim( (string) ( $row['batch'] ?? '' ) );
		if ( '' !== $batch ) {
			$existing = (string) get_post_meta( $product_id, '_gpsr_batch', true );
			if ( $existing !== $batch ) {
				update_post_meta( $product_id, '_gpsr_batch', $batch );
			}
		}
	}
}
