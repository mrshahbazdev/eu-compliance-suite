<?php
/**
 * Settings.
 *
 * @package EuroComply\MiCA
 */

declare( strict_types = 1 );

namespace EuroComply\MiCA;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Settings {

	public const OPTION_KEY = 'eurocomply_mica_settings';

	/**
	 * @return array<string,mixed>
	 */
	public static function defaults() : array {
		return array(
			'entity_name'           => get_bloginfo( 'name' ),
			'entity_lei'            => '',
			'entity_country'        => 'DE',
			'entity_type'           => 'casp',
			'home_nca'              => '',
			'home_nca_email'        => '',
			'compliance_officer'    => get_bloginfo( 'admin_email' ),
			'standstill_days'       => 12,
			'ack_days'              => 10,
			'resolution_days'       => 60,
			'reporting_year'        => (int) gmdate( 'Y' ),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function get() : array {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return array_merge( self::defaults(), $stored );
	}

	/**
	 * @param array<string,mixed> $input
	 * @return array<string,mixed>
	 */
	public static function sanitize( array $input ) : array {
		$out = self::defaults();
		if ( isset( $input['entity_name'] ) ) {
			$out['entity_name'] = sanitize_text_field( (string) $input['entity_name'] );
		}
		if ( isset( $input['entity_lei'] ) ) {
			$out['entity_lei'] = strtoupper( substr( preg_replace( '/[^A-Za-z0-9]/', '', (string) $input['entity_lei'] ), 0, 20 ) );
		}
		if ( isset( $input['entity_country'] ) ) {
			$cc = strtoupper( (string) $input['entity_country'] );
			if ( preg_match( '/^[A-Z]{2}$/', $cc ) ) {
				$out['entity_country'] = $cc;
			}
		}
		if ( isset( $input['entity_type'] ) ) {
			$t = sanitize_key( (string) $input['entity_type'] );
			if ( in_array( $t, array_keys( self::entity_types() ), true ) ) {
				$out['entity_type'] = $t;
			}
		}
		foreach ( array( 'home_nca', 'home_nca_email', 'compliance_officer' ) as $f ) {
			if ( isset( $input[ $f ] ) ) {
				$v = (string) $input[ $f ];
				$out[ $f ] = ( false !== strpos( $f, 'email' ) || 'compliance_officer' === $f )
					? ( is_email( sanitize_email( $v ) ) ? sanitize_email( $v ) : $out[ $f ] )
					: sanitize_text_field( $v );
			}
		}
		foreach ( array( 'standstill_days' => array( 1, 60 ), 'ack_days' => array( 1, 30 ), 'resolution_days' => array( 7, 365 ), 'reporting_year' => array( 2024, 2099 ) ) as $f => $rng ) {
			if ( isset( $input[ $f ] ) ) {
				$out[ $f ] = max( $rng[0], min( $rng[1], (int) $input[ $f ] ) );
			}
		}
		return $out;
	}

	/**
	 * @return array<string,string>
	 */
	public static function entity_types() : array {
		return array(
			'offeror'       => __( 'Offeror (other crypto-asset, Art. 4)', 'eurocomply-mica' ),
			'art_issuer'    => __( 'Asset-referenced token issuer (Art. 16)', 'eurocomply-mica' ),
			'emt_issuer'    => __( 'E-money token issuer (Art. 48)',           'eurocomply-mica' ),
			'casp'          => __( 'Crypto-asset service provider (Art. 59)',    'eurocomply-mica' ),
			'trading_admit' => __( 'Person seeking admission to trading (Art. 5)', 'eurocomply-mica' ),
		);
	}

	public static function entity_type_label( string $key ) : string {
		$t = self::entity_types();
		return $t[ $key ] ?? $key;
	}
}
