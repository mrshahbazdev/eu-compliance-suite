<?php
/**
 * Sister-plugin bridge #6: VAT OSS (#3) ↔ Omnibus (#8) shared
 * price-history table.
 *
 * Reads gross-price snapshots from the canonical Omnibus price-history
 * table so VAT OSS can answer "what was the gross price on the date
 * this order was placed?" — useful for credit-note / refund
 * calculations under Council Implementing Reg. (EU) 282/2011 Art. 219.
 *
 * Degrades gracefully when Omnibus is not installed: every accessor
 * guards with class_exists() against an FQN string and returns null.
 *
 * @package EuroComply\VatOss
 */

declare( strict_types = 1 );

namespace EuroComply\VatOss;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class OmnibusBridge {

	private const OMNIBUS_PRICE_STORE_FQN = '\\EuroComply\\Omnibus\\PriceStore';

	public static function is_active() : bool {
		return class_exists( self::OMNIBUS_PRICE_STORE_FQN );
	}

	/**
	 * Most-recent price-history row for a product as of (or before)
	 * a specific timestamp.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function gross_at( int $product_id, ?int $timestamp = null ) : ?array {
		if ( ! self::is_active() ) {
			return null;
		}
		$row = call_user_func(
			array( self::OMNIBUS_PRICE_STORE_FQN, 'at_or_before' ),
			$product_id,
			$timestamp
		);
		return is_array( $row ) ? $row : null;
	}

	/**
	 * For a given WooCommerce order, walk every line item and return
	 * the gross-price snapshot recorded in Omnibus at order time.
	 * Each row carries:
	 *   product_id, line_total (gross paid by customer),
	 *   recorded_price, recorded_at, status (ok|no_history|drift),
	 *   drift_amount.
	 *
	 * `drift` flags any line where the order line-total deviates from
	 * the recorded price by more than one currency unit (one cent for
	 * EUR/USD/GBP) — typically a manual override that was never saved
	 * back into the canonical price history.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function verify_order( int $order_id, float $tolerance = 0.01 ) : array {
		if ( ! self::is_active() ) {
			return array();
		}
		if ( ! function_exists( 'wc_get_order' ) ) {
			return array();
		}
		$order = call_user_func( 'wc_get_order', $order_id );
		if ( ! is_object( $order ) || ! method_exists( $order, 'get_items' ) ) {
			return array();
		}
		$placed_ts = self::order_timestamp( $order );
		$findings  = array();
		/** @var array<int,object> $items */
		$items = (array) $order->get_items();
		foreach ( $items as $item ) {
			if ( ! is_object( $item ) || ! method_exists( $item, 'get_product_id' ) ) {
				continue;
			}
			$pid     = (int) $item->get_product_id();
			$variant = method_exists( $item, 'get_variation_id' ) ? (int) $item->get_variation_id() : 0;
			$lookup  = $variant > 0 ? $variant : $pid;
			$qty     = method_exists( $item, 'get_quantity' ) ? max( 1, (int) $item->get_quantity() ) : 1;
			$total   = method_exists( $item, 'get_total' ) ? (float) $item->get_total() : 0.0;
			$unit    = $qty > 0 ? $total / $qty : $total;

			$row = self::gross_at( $lookup, $placed_ts );
			if ( null === $row ) {
				$findings[] = array(
					'product_id'     => $lookup,
					'line_total'     => $total,
					'unit_paid'      => $unit,
					'recorded_price' => null,
					'recorded_at'    => null,
					'status'         => 'no_history',
					'drift_amount'   => null,
				);
				continue;
			}
			$recorded = (float) ( $row['effective_price'] ?? 0.0 );
			$drift    = $unit - $recorded;
			$findings[] = array(
				'product_id'     => $lookup,
				'line_total'     => $total,
				'unit_paid'      => $unit,
				'recorded_price' => $recorded,
				'recorded_at'    => (string) ( $row['recorded_at'] ?? '' ),
				'tax_rate'       => isset( $row['tax_rate'] ) ? (float) $row['tax_rate'] : null,
				'tax_country'    => (string) ( $row['tax_country'] ?? '' ),
				'status'         => abs( $drift ) > $tolerance ? 'drift' : 'ok',
				'drift_amount'   => round( $drift, 4 ),
			);
		}
		return $findings;
	}

	private static function order_timestamp( object $order ) : ?int {
		if ( method_exists( $order, 'get_date_created' ) ) {
			$dt = $order->get_date_created();
			if ( is_object( $dt ) && method_exists( $dt, 'getTimestamp' ) ) {
				return (int) $dt->getTimestamp();
			}
		}
		return null;
	}
}
