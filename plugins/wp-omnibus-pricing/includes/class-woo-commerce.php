<?php
/**
 * WooCommerce integration for EuroComply Omnibus — wires the price tracker
 * and the price display filter.
 *
 * @package EuroComply\Omnibus
 */

declare( strict_types = 1 );

namespace EuroComply\Omnibus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WooCommerce {

	private static ?WooCommerce $instance = null;

	public static function instance() : WooCommerce {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register();
		}
		return self::$instance;
	}

	private function register() : void {
		PriceTracker::instance();
		PriceDisplay::instance();
	}
}
