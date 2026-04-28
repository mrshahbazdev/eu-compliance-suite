<?php
/**
 * Bridge from EuroComply GPSR (#4) to EuroComply Toy Safety (#25).
 *
 * Listens for `eurocomply_gpsr_product_saved` and pushes the relevant
 * GPSR meta (warnings, batch/lot, manufacturer name) into the matching
 * toy register row so a toy that is also a WooCommerce product keeps
 * its Art. 11 warnings + traceability identifier in lock-step with
 * the GPSR (Reg. (EU) 2023/988) source of truth.
 *
 * Match strategy:
 *   1. `linked_product_id` column on the toy row (explicit link)
 *   2. WooCommerce product SKU == normalised GTIN (implicit fallback)
 *
 * Degrades gracefully: when GPSR is missing the action never fires.
 *
 * @package EuroComply\ToySafety
 */

declare( strict_types = 1 );

namespace EuroComply\ToySafety;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class GpsrBridge {

	private const GPSR_FQN = '\\EuroComply\\Gpsr\\ProductFields';

	public static function register() : void {
		add_action( 'eurocomply_gpsr_product_saved', array( __CLASS__, 'on_gpsr_product_saved' ), 10, 2 );
	}

	public static function gpsr_active() : bool {
		return class_exists( self::GPSR_FQN );
	}

	/**
	 * Pull the WooCommerce SKU for a given product id without requiring
	 * Woo to be loaded. Falls back to `_sku` post meta which is what
	 * Woo writes anyway.
	 */
	public static function product_sku( int $product_id ) : string {
		if ( $product_id <= 0 ) {
			return '';
		}
		return (string) get_post_meta( $product_id, '_sku', true );
	}

	/**
	 * Handler for eurocomply_gpsr_product_saved.
	 *
	 * @param int                  $product_id WooCommerce product id.
	 * @param array<string,string> $payload    GPSR field values keyed by meta key.
	 */
	public static function on_gpsr_product_saved( int $product_id, array $payload ) : void {
		if ( $product_id <= 0 ) {
			return;
		}
		$sku = self::product_sku( $product_id );
		$toy = ToyStore::find_by_product_or_gtin( $product_id, $sku );
		if ( null === $toy ) {
			return;
		}

		$updates = array(
			'gpsr_synced_at' => current_time( 'mysql' ),
		);

		$warnings = isset( $payload['_gpsr_warnings'] ) ? trim( (string) $payload['_gpsr_warnings'] ) : '';
		if ( '' !== $warnings && (string) ( $toy['warnings'] ?? '' ) !== $warnings ) {
			$updates['warnings'] = $warnings;
		}

		$batch = isset( $payload['_gpsr_batch'] ) ? trim( (string) $payload['_gpsr_batch'] ) : '';
		if ( '' !== $batch && (string) ( $toy['batch'] ?? '' ) !== $batch ) {
			$updates['batch'] = $batch;
		}

		$linked = (int) ( $toy['linked_product_id'] ?? 0 );
		if ( 0 === $linked ) {
			$updates['linked_product_id'] = $product_id;
		}

		if ( count( $updates ) > 1 ) {
			ToyStore::update( (int) $toy['id'], $updates );
		} else {
			// Always stamp the sync timestamp even if no field changed.
			ToyStore::update( (int) $toy['id'], $updates );
		}
	}
}
