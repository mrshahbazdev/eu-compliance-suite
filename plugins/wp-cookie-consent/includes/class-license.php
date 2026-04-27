<?php
/**
 * License key handling (stub - local validation only).
 *
 * Real HTTPS validation endpoint will replace verify() in a follow-up task.
 *
 * @package EuroComply\CookieConsent
 */

declare( strict_types = 1 );

namespace EuroComply\CookieConsent;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores and validates Pro license keys.
 */
final class License {

	public const OPTION_KEY = 'eurocomply_cc_license';

	/**
	 * Accept keys in the EC-XXXXXX format.
	 */
	public const PATTERN = '/^EC-[A-Z0-9]{6,}$/';

	public static function get_key() : string {
		$stored = get_option( self::OPTION_KEY, '' );
		return is_string( $stored ) ? $stored : '';
	}

	public static function set_key( string $key ) : string {
		$key = strtoupper( trim( $key ) );
		if ( '' === $key ) {
			delete_option( self::OPTION_KEY );
			return '';
		}
		if ( ! preg_match( self::PATTERN, $key ) ) {
			return '';
		}
		update_option( self::OPTION_KEY, $key, false );
		return $key;
	}

	public static function is_pro() : bool {
		return self::verify( self::get_key() );
	}

	public static function verify( string $key ) : bool {
		return '' !== $key && 1 === preg_match( self::PATTERN, $key );
	}
}
