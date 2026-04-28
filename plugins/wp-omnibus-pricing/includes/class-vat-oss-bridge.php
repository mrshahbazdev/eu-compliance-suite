<?php
/**
 * Sister-plugin bridge #6: Omnibus (#8) ↔ VAT OSS (#3).
 *
 * Listens on `eurocomply_vat_oss_settings_saved` and re-records every
 * tracked product so the price-history table picks up the new tax
 * configuration (base country, prices-include-tax flag) on the next
 * row. Without this, the tax_rate / net_price columns would only
 * reflect the configuration in force when each row was first written.
 *
 * Degrades gracefully when VAT OSS is not installed: the action is
 * never fired so the listener never runs.
 *
 * @package EuroComply\Omnibus
 */

declare( strict_types = 1 );

namespace EuroComply\Omnibus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class VatOssBridge {

	private const VAT_RATES_FQN = '\\EuroComply\\VatOss\\Rates';

	public static function register() : void {
		add_action( 'eurocomply_vat_oss_settings_saved', array( __CLASS__, 'on_vat_settings_saved' ) );
	}

	public static function is_active() : bool {
		return class_exists( self::VAT_RATES_FQN );
	}

	public static function on_vat_settings_saved() : void {
		if ( ! self::is_active() ) {
			return;
		}
		PriceTracker::instance()->backfill( 'vat-settings' );
	}
}
