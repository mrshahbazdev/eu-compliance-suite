<?php
/**
 * Settings store for EuroComply VAT & OSS.
 *
 * @package EuroComply\VatOss
 */

declare( strict_types = 1 );

namespace EuroComply\VatOss;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Settings {

	public const OPTION_KEY = 'eurocomply_vat_settings';

	/**
	 * @return array<string,mixed>
	 */
	public static function defaults() : array {
		return array(
			'shop_country'        => self::guess_shop_country(),
			'oss_enabled'         => '1',
			'reverse_charge_b2b'  => '1',
			'require_vat_for_b2b' => '0',
			'validate_via_vies'   => '1',
			'vies_timeout'        => 8,
			'show_rates_in_cart'  => '1',
			'checkout_label_en'   => 'VAT / Tax ID (for B2B purchases)',
			'checkout_label_de'   => 'USt-IdNr. (für B2B-Käufe)',
			'checkout_help_en'    => 'Enter your VAT number in the format DE123456789 to apply EU reverse charge.',
			'checkout_help_de'    => 'Geben Sie Ihre USt-IdNr. im Format DE123456789 ein, um das Reverse-Charge-Verfahren anzuwenden.',
		);
	}

	public static function seed_defaults() : void {
		if ( false === get_option( self::OPTION_KEY ) ) {
			update_option( self::OPTION_KEY, self::defaults(), false );
		}
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function get() : array {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return array_replace_recursive( self::defaults(), $stored );
	}

	/**
	 * Persist a sanitised settings payload.
	 *
	 * @param array<string,mixed> $input Raw POST.
	 */
	public static function save( array $input ) : void {
		$current = self::get();
		$next    = $current;

		if ( isset( $input['shop_country'] ) ) {
			$iso = strtoupper( sanitize_text_field( (string) $input['shop_country'] ) );
			if ( Rates::is_eu_country( $iso ) ) {
				$next['shop_country'] = $iso;
			}
		}

		foreach ( array( 'oss_enabled', 'reverse_charge_b2b', 'require_vat_for_b2b', 'validate_via_vies', 'show_rates_in_cart' ) as $flag ) {
			$next[ $flag ] = ! empty( $input[ $flag ] ) ? '1' : '0';
		}

		if ( isset( $input['vies_timeout'] ) ) {
			$timeout             = (int) $input['vies_timeout'];
			$next['vies_timeout'] = max( 2, min( 30, $timeout ) );
		}

		foreach ( array( 'checkout_label_en', 'checkout_label_de', 'checkout_help_en', 'checkout_help_de' ) as $text ) {
			if ( isset( $input[ $text ] ) ) {
				$next[ $text ] = sanitize_text_field( (string) wp_unslash( $input[ $text ] ) );
			}
		}

		update_option( self::OPTION_KEY, $next, false );
	}

	/**
	 * Guess shop country from WooCommerce base location, or WP locale.
	 */
	public static function guess_shop_country() : string {
		if ( function_exists( 'wc_get_base_location' ) ) {
			$base = wc_get_base_location();
			if ( is_array( $base ) && ! empty( $base['country'] ) ) {
				$iso = strtoupper( (string) $base['country'] );
				if ( Rates::is_eu_country( $iso ) ) {
					return $iso;
				}
			}
		}
		$locale = function_exists( 'get_locale' ) ? (string) get_locale() : '';
		if ( preg_match( '/_([A-Z]{2})/', $locale, $m ) ) {
			$iso = $m[1];
			if ( Rates::is_eu_country( $iso ) ) {
				return $iso;
			}
		}
		return 'DE';
	}
}
