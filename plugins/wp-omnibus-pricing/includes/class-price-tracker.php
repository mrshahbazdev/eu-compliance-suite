<?php
/**
 * Captures WooCommerce product price changes and writes them to the history
 * store. Fires on save_post_product + save_post_product_variation so every
 * admin save (UI or REST / Action Scheduler) records a row.
 *
 * A compact "last known state" meta is kept per product to avoid recording a
 * duplicate row when another unrelated meta is saved.
 *
 * @package EuroComply\Omnibus
 */

declare( strict_types = 1 );

namespace EuroComply\Omnibus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PriceTracker {

	public const LAST_META = '_eurocomply_omnibus_last';

	private static ?PriceTracker $instance = null;

	public static function instance() : PriceTracker {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register();
		}
		return self::$instance;
	}

	private function register() : void {
		add_action( 'save_post_product', array( $this, 'on_save' ), 50, 1 );
		add_action( 'save_post_product_variation', array( $this, 'on_save' ), 50, 1 );
	}

	public function on_save( int $post_id ) : void {
		$settings = Settings::get();
		if ( empty( $settings['auto_track_on_save'] ) ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		$this->track( $post_id, 'save' );
	}

	/**
	 * @return array{recorded:bool,reason:string,id:int}
	 */
	public function track( int $post_id, string $source = 'save' ) : array {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array(
				'recorded' => false,
				'reason'   => 'missing_post',
				'id'       => 0,
			);
		}
		if ( ! in_array( $post->post_type, array( 'product', 'product_variation' ), true ) ) {
			return array(
				'recorded' => false,
				'reason'   => 'wrong_type',
				'id'       => 0,
			);
		}

		$regular = self::read_price( $post_id, '_regular_price' );
		$sale    = self::read_price( $post_id, '_sale_price' );

		// Skip empty prices (drafts without pricing) to avoid zero-floor history.
		if ( null === $regular && null === $sale ) {
			return array(
				'recorded' => false,
				'reason'   => 'empty_prices',
				'id'       => 0,
			);
		}

		$effective = null !== $sale ? $sale : ( null !== $regular ? $regular : 0.0 );

		$current = array(
			'regular'   => $regular,
			'sale'      => $sale,
			'effective' => $effective,
		);
		$last    = get_post_meta( $post_id, self::LAST_META, true );
		if ( is_array( $last ) && self::equal_snapshot( $last, $current ) ) {
			return array(
				'recorded' => false,
				'reason'   => 'unchanged',
				'id'       => 0,
			);
		}

		$parent_id = 'product_variation' === $post->post_type ? (int) $post->post_parent : 0;

		$id = PriceStore::record(
			array(
				'product_id'      => $post_id,
				'parent_id'       => $parent_id,
				'regular_price'   => null !== $regular ? $regular : 0.0,
				'sale_price'      => $sale,
				'effective_price' => $effective,
				'currency'        => PriceStore::default_currency(),
				'trigger_source'  => $source,
			)
		);

		update_post_meta( $post_id, self::LAST_META, $current );

		return array(
			'recorded' => $id > 0,
			'reason'   => 'ok',
			'id'       => $id,
		);
	}

	/**
	 * Bulk backfill: record current price for every published product /
	 * variation that does not yet have a history row. Returns counts for
	 * the admin notice.
	 *
	 * @return array{scanned:int,recorded:int,skipped:int}
	 */
	public function backfill() : array {
		$scanned  = 0;
		$recorded = 0;
		$skipped  = 0;

		$ids = get_posts(
			array(
				'post_type'      => array( 'product', 'product_variation' ),
				'post_status'    => array( 'publish', 'private' ),
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
			)
		);
		foreach ( (array) $ids as $id ) {
			$scanned++;
			$result = $this->track( (int) $id, 'backfill' );
			if ( $result['recorded'] ) {
				$recorded++;
			} else {
				$skipped++;
			}
		}

		return array(
			'scanned'  => $scanned,
			'recorded' => $recorded,
			'skipped'  => $skipped,
		);
	}

	private static function read_price( int $post_id, string $meta_key ) : ?float {
		$raw = get_post_meta( $post_id, $meta_key, true );
		if ( '' === $raw || null === $raw ) {
			return null;
		}
		$normalised = str_replace( ',', '.', (string) $raw );
		if ( ! is_numeric( $normalised ) ) {
			return null;
		}
		return (float) $normalised;
	}

	/**
	 * @param array<string,mixed> $a
	 * @param array<string,mixed> $b
	 */
	private static function equal_snapshot( array $a, array $b ) : bool {
		$keys = array( 'regular', 'sale', 'effective' );
		foreach ( $keys as $k ) {
			$av = isset( $a[ $k ] ) ? $a[ $k ] : null;
			$bv = isset( $b[ $k ] ) ? $b[ $k ] : null;
			if ( null === $av && null === $bv ) {
				continue;
			}
			if ( null === $av || null === $bv ) {
				return false;
			}
			if ( abs( (float) $av - (float) $bv ) > 0.00001 ) {
				return false;
			}
		}
		return true;
	}
}
