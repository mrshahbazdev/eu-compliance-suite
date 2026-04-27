<?php
/**
 * Settings.
 *
 * @package EuroComply\ToySafety
 */

declare( strict_types = 1 );

namespace EuroComply\ToySafety;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Settings {

	public const OPTION_KEY = 'eurocomply_toy_settings';

	public static function defaults() : array {
		return array(
			'entity_name'          => get_bloginfo( 'name' ),
			'entity_country'       => 'DE',
			'role'                 => 'manufacturer',
			'eori'                 => '',
			'safety_gate_email'    => '',
			'compliance_officer'   => get_bloginfo( 'admin_email' ),
			'reporting_year'       => (int) gmdate( 'Y' ),
			'incident_initial_h'   => 24,
			'incident_followup_d'  => 30,
		);
	}

	public static function get() : array {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return array_merge( self::defaults(), $stored );
	}

	public static function sanitize( array $input ) : array {
		$out = self::defaults();
		if ( isset( $input['entity_name'] ) ) {
			$out['entity_name'] = sanitize_text_field( (string) $input['entity_name'] );
		}
		if ( isset( $input['entity_country'] ) ) {
			$cc = strtoupper( (string) $input['entity_country'] );
			if ( preg_match( '/^[A-Z]{2}$/', $cc ) ) {
				$out['entity_country'] = $cc;
			}
		}
		if ( isset( $input['role'] ) ) {
			$r = sanitize_key( (string) $input['role'] );
			if ( in_array( $r, array_keys( self::roles() ), true ) ) {
				$out['role'] = $r;
			}
		}
		if ( isset( $input['eori'] ) ) {
			$out['eori'] = strtoupper( substr( preg_replace( '/[^A-Za-z0-9]/', '', (string) $input['eori'] ), 0, 17 ) );
		}
		foreach ( array( 'safety_gate_email', 'compliance_officer' ) as $f ) {
			if ( isset( $input[ $f ] ) ) {
				$v = sanitize_email( (string) $input[ $f ] );
				if ( is_email( $v ) ) {
					$out[ $f ] = $v;
				}
			}
		}
		foreach ( array( 'reporting_year' => array( 2024, 2099 ), 'incident_initial_h' => array( 1, 168 ), 'incident_followup_d' => array( 1, 365 ) ) as $f => $rng ) {
			if ( isset( $input[ $f ] ) ) {
				$out[ $f ] = max( $rng[0], min( $rng[1], (int) $input[ $f ] ) );
			}
		}
		return $out;
	}

	/** @return array<string,string> */
	public static function roles() : array {
		return array(
			'manufacturer'    => __( 'Manufacturer',                    'eurocomply-toy-safety' ),
			'authorised_rep'  => __( 'Authorised representative',           'eurocomply-toy-safety' ),
			'importer'        => __( 'Importer',                                'eurocomply-toy-safety' ),
			'distributor'     => __( 'Distributor',                                'eurocomply-toy-safety' ),
			'fulfilment'      => __( 'Fulfilment service provider (Art. 4 EU 2019/1020)', 'eurocomply-toy-safety' ),
			'online_market'   => __( 'Online marketplace',                                'eurocomply-toy-safety' ),
		);
	}

	public static function role_label( string $key ) : string {
		$r = self::roles();
		return $r[ $key ] ?? $key;
	}

	/** @return array<string,string> */
	public static function modules() : array {
		return array(
			'A'  => __( 'Module A — internal production control',          'eurocomply-toy-safety' ),
			'Aa' => __( 'Module A2 — internal production control + tests',   'eurocomply-toy-safety' ),
			'B'  => __( 'Module B — EU-type examination',                      'eurocomply-toy-safety' ),
			'BC' => __( 'Module B + C — EU-type + conformity to type',          'eurocomply-toy-safety' ),
			'BE' => __( 'Module B + E — EU-type + product QA',                    'eurocomply-toy-safety' ),
		);
	}

	/** @return array<string,string> */
	public static function age_ranges() : array {
		return array(
			'0-12'   => '0–12 months',
			'12-36'  => '12–36 months',
			'36-72'  => '3–6 years',
			'72-168' => '6–14 years',
			'14+'    => '14+ years',
		);
	}
}
