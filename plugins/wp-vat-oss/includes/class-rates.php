<?php
/**
 * EU-27 VAT rates table.
 *
 * Source data is shipped in data/eu-vat-rates.json. The file is small enough
 * to decode on each request, but we cache the decoded array in an option for
 * faster access on hot admin paths.
 *
 * @package EuroComply\VatOss
 */

declare( strict_types = 1 );

namespace EuroComply\VatOss;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Rates {

	public const OPTION_CACHE = 'eurocomply_vat_rates_cache';

	/**
	 * List of EU-27 ISO 3166-1 alpha-2 country codes.
	 *
	 * Greece uses "GR" (not "EL" which is the VIES / VAT-number prefix).
	 * See Rates::vat_prefix_to_iso() for the translation.
	 *
	 * @var string[]
	 */
	public const EU27 = array(
		'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
		'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
		'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
	);

	/**
	 * Return decoded rates as an associative array keyed by ISO code.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function all() : array {
		$cached = get_option( self::OPTION_CACHE, null );
		if ( is_array( $cached ) && ! empty( $cached ) ) {
			return $cached;
		}

		$path = EUROCOMPLY_VAT_DIR . 'data/eu-vat-rates.json';
		if ( ! is_readable( $path ) ) {
			return array();
		}

		$raw = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $raw ) {
			return array();
		}

		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) || empty( $decoded['rates'] ) || ! is_array( $decoded['rates'] ) ) {
			return array();
		}

		update_option( self::OPTION_CACHE, $decoded['rates'], false );
		return $decoded['rates'];
	}

	public static function is_eu_country( string $iso ) : bool {
		return in_array( strtoupper( $iso ), self::EU27, true );
	}

	/**
	 * Return the standard VAT rate for a given EU country, or null if unknown.
	 */
	public static function standard_rate( string $iso ) : ?float {
		$iso   = strtoupper( $iso );
		$rates = self::all();
		if ( ! isset( $rates[ $iso ]['standard'] ) ) {
			return null;
		}
		return (float) $rates[ $iso ]['standard'];
	}

	/**
	 * Translate a VAT-number prefix (as used in VIES) to its ISO country code.
	 *
	 * Greece uses "EL" as the VAT prefix but "GR" as the ISO code.
	 */
	public static function vat_prefix_to_iso( string $prefix ) : string {
		$prefix = strtoupper( $prefix );
		return 'EL' === $prefix ? 'GR' : $prefix;
	}

	public static function iso_to_vat_prefix( string $iso ) : string {
		$iso = strtoupper( $iso );
		return 'GR' === $iso ? 'EL' : $iso;
	}
}
