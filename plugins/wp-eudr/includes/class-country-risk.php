<?php
/**
 * Country risk classifier (Art. 29 Reg. 2023/1115).
 *
 * The Commission publishes the official low / standard / high country list via
 * implementing acts (Art. 29(1)). Free tier ships a heuristic seed list per
 * commodity that operators can override per-country in Settings; Pro syncs the
 * official list when published.
 *
 * @package EuroComply\EUDR
 */

declare( strict_types = 1 );

namespace EuroComply\EUDR;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CountryRisk {

	public const OPTION_KEY = 'eurocomply_eudr_country_risk';

	/**
	 * Heuristic seed of high-risk producer countries for each commodity.
	 * Operators MUST verify against the Commission's published list.
	 *
	 * @return array<string,array<int,string>>
	 */
	public static function high_risk_seed() : array {
		return array(
			'cattle'    => array( 'BR', 'PY', 'BO', 'AR' ),
			'cocoa'     => array( 'CI', 'GH', 'NG', 'CM', 'ID' ),
			'coffee'    => array( 'BR', 'VN', 'CO', 'ID', 'HN', 'ET', 'UG' ),
			'oil_palm'  => array( 'ID', 'MY', 'TH', 'CO', 'NG', 'PG', 'GT', 'HN' ),
			'rubber'    => array( 'TH', 'ID', 'VN', 'MY', 'CI', 'CN', 'IN' ),
			'soya'      => array( 'BR', 'AR', 'PY', 'BO', 'US', 'UY' ),
			'wood'      => array( 'BR', 'ID', 'MY', 'PG', 'CD', 'CG', 'GA', 'PE', 'BO', 'RU' ),
		);
	}

	public static function overrides() : array {
		$stored = get_option( self::OPTION_KEY, array() );
		return is_array( $stored ) ? $stored : array();
	}

	public static function set_override( string $country, string $level ) : void {
		$overrides              = self::overrides();
		$overrides[ strtoupper( $country ) ] = sanitize_key( $level );
		update_option( self::OPTION_KEY, $overrides, false );
	}

	public static function clear_override( string $country ) : void {
		$overrides = self::overrides();
		unset( $overrides[ strtoupper( $country ) ] );
		update_option( self::OPTION_KEY, $overrides, false );
	}

	/**
	 * Resolve country-level risk for a commodity.
	 *
	 * @return string low|standard|high
	 */
	public static function level( string $country, string $commodity = '' ) : string {
		$cc        = strtoupper( $country );
		$overrides = self::overrides();
		if ( isset( $overrides[ $cc ] ) ) {
			return (string) $overrides[ $cc ];
		}
		if ( '' !== $commodity ) {
			$seed = self::high_risk_seed();
			if ( isset( $seed[ $commodity ] ) && in_array( $cc, $seed[ $commodity ], true ) ) {
				return 'high';
			}
		}
		$default = (string) ( Settings::get()['default_country_risk'] ?? 'standard' );
		return in_array( $default, array( 'low', 'standard', 'high' ), true ) ? $default : 'standard';
	}
}
