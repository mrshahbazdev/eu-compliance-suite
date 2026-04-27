<?php
/**
 * License key handling (stub — local validation only).
 *
 * Real HTTPS validation endpoint replaces verify() in a follow-up task.
 *
 * @package EuroComply\VatOss
 */

declare( strict_types = 1 );

namespace EuroComply\VatOss;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class License {

	public const OPTION_KEY = 'eurocomply_vat_license';
	public const PATTERN    = '/^EC-[A-Z0-9]{6,}$/';

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
