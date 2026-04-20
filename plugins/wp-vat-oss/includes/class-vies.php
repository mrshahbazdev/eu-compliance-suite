<?php
/**
 * EU VIES VAT-number validator.
 *
 * Uses the European Commission's public REST endpoint:
 *   https://ec.europa.eu/taxation_customs/vies/rest-api/ms/{country}/vat/{number}
 *
 * Validation runs in three steps:
 *   1. Local format pre-check against per-country regex (cheap, catches typos).
 *   2. Live VIES REST request when validate_via_vies setting is enabled.
 *   3. Transient cache of valid + invalid responses to reduce load on VIES.
 *
 * @package EuroComply\VatOss
 */

declare( strict_types = 1 );

namespace EuroComply\VatOss;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Vies {

	public const ENDPOINT = 'https://ec.europa.eu/taxation_customs/vies/rest-api/ms/%s/vat/%s';

	public const TRANSIENT_PREFIX = 'eurocomply_vat_vies_';
	public const TRANSIENT_TTL    = DAY_IN_SECONDS;

	/**
	 * Per-country VAT number pattern (digits only after 2-letter prefix).
	 *
	 * Based on the European Commission VIES "format rules" — see
	 * https://taxation-customs.ec.europa.eu/vies-vat-information-exchange-system_en
	 *
	 * Patterns here are intentionally permissive: VIES itself is the
	 * authoritative check. We only reject obviously malformed input.
	 *
	 * @var array<string,string>
	 */
	public const PATTERNS = array(
		'AT' => '/^U[0-9]{8}$/',
		'BE' => '/^[0-9]{10}$/',
		'BG' => '/^[0-9]{9,10}$/',
		'HR' => '/^[0-9]{11}$/',
		'CY' => '/^[0-9]{8}[A-Z]$/',
		'CZ' => '/^[0-9]{8,10}$/',
		'DK' => '/^[0-9]{8}$/',
		'EE' => '/^[0-9]{9}$/',
		'FI' => '/^[0-9]{8}$/',
		'FR' => '/^[A-HJ-NP-Z0-9]{2}[0-9]{9}$/',
		'DE' => '/^[0-9]{9}$/',
		'EL' => '/^[0-9]{9}$/',
		'HU' => '/^[0-9]{8}$/',
		'IE' => '/^[0-9][A-Z0-9+*][0-9]{5}[A-Z]{1,2}$/',
		'IT' => '/^[0-9]{11}$/',
		'LV' => '/^[0-9]{11}$/',
		'LT' => '/^([0-9]{9}|[0-9]{12})$/',
		'LU' => '/^[0-9]{8}$/',
		'MT' => '/^[0-9]{8}$/',
		'NL' => '/^[0-9]{9}B[0-9]{2}$/',
		'PL' => '/^[0-9]{10}$/',
		'PT' => '/^[0-9]{9}$/',
		'RO' => '/^[0-9]{2,10}$/',
		'SK' => '/^[0-9]{10}$/',
		'SI' => '/^[0-9]{8}$/',
		'ES' => '/^[A-Z0-9][0-9]{7}[A-Z0-9]$/',
		'SE' => '/^[0-9]{12}$/',
	);

	/**
	 * Normalise a raw VAT input: strip spaces, dots, dashes; uppercase.
	 */
	public static function normalise( string $vat ) : string {
		$vat = strtoupper( preg_replace( '/[\s\.\-]+/', '', $vat ) ?? '' );
		return $vat;
	}

	/**
	 * Split "DE123456789" into ["DE", "123456789"]. Returns null if no 2-letter prefix.
	 *
	 * @return array{0:string,1:string}|null
	 */
	public static function split( string $vat ) : ?array {
		$vat = self::normalise( $vat );
		if ( strlen( $vat ) < 3 || ! preg_match( '/^[A-Z]{2}/', $vat ) ) {
			return null;
		}
		return array( substr( $vat, 0, 2 ), substr( $vat, 2 ) );
	}

	/**
	 * Local format pre-check. Returns true iff pattern matches for the given prefix.
	 */
	public static function local_format_ok( string $vat ) : bool {
		$split = self::split( $vat );
		if ( null === $split ) {
			return false;
		}
		list( $prefix, $number ) = $split;
		if ( ! isset( self::PATTERNS[ $prefix ] ) ) {
			return false;
		}
		return 1 === preg_match( self::PATTERNS[ $prefix ], $number );
	}

	/**
	 * Validate against the live VIES REST endpoint.
	 *
	 * @return array{valid:bool,name:string,address:string,prefix:string,number:string,source:string,raw:mixed}
	 */
	public static function validate( string $vat, int $timeout = 8 ) : array {
		$split = self::split( $vat );
		if ( null === $split || ! self::local_format_ok( $vat ) ) {
			return array(
				'valid'   => false,
				'name'    => '',
				'address' => '',
				'prefix'  => $split[0] ?? '',
				'number'  => $split[1] ?? '',
				'source'  => 'local',
				'raw'     => null,
			);
		}
		list( $prefix, $number ) = $split;

		$cache_key = self::TRANSIENT_PREFIX . md5( $prefix . $number );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) && isset( $cached['valid'] ) ) {
			$cached['source'] = 'cache';
			return $cached;
		}

		$url = sprintf( self::ENDPOINT, rawurlencode( $prefix ), rawurlencode( $number ) );

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => $timeout,
				'headers' => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'valid'   => false,
				'name'    => '',
				'address' => '',
				'prefix'  => $prefix,
				'number'  => $number,
				'source'  => 'error',
				'raw'     => $response->get_error_message(),
			);
		}

		$body   = (string) wp_remote_retrieve_body( $response );
		$status = (int) wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( $body, true );

		if ( 200 !== $status || ! is_array( $decoded ) ) {
			return array(
				'valid'   => false,
				'name'    => '',
				'address' => '',
				'prefix'  => $prefix,
				'number'  => $number,
				'source'  => 'vies_http_' . $status,
				'raw'     => $body,
			);
		}

		$is_valid = ! empty( $decoded['isValid'] );
		$result   = array(
			'valid'   => (bool) $is_valid,
			'name'    => isset( $decoded['name'] ) ? (string) $decoded['name'] : '',
			'address' => isset( $decoded['address'] ) ? (string) $decoded['address'] : '',
			'prefix'  => $prefix,
			'number'  => $number,
			'source'  => 'vies',
			'raw'     => $decoded,
		);

		set_transient( $cache_key, $result, self::TRANSIENT_TTL );

		return $result;
	}
}
