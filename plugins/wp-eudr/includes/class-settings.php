<?php
/**
 * Settings.
 *
 * @package EuroComply\EUDR
 */

declare( strict_types = 1 );

namespace EuroComply\EUDR;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Settings {

	public const OPTION_KEY = 'eurocomply_eudr_settings';

	/**
	 * @return array<string,mixed>
	 */
	public static function defaults() : array {
		return array(
			'operator_name'      => get_bloginfo( 'name' ),
			'operator_eori'      => '',
			'operator_country'   => 'DE',
			'operator_address'   => '',
			'operator_role'      => 'operator',  // operator|trader|sme_trader|sme_operator
			'compliance_officer' => get_bloginfo( 'admin_email' ),
			'cutoff_date'        => '2020-12-31', // Art. 2(13) — fixed deforestation cut-off
			'reporting_year'     => (int) gmdate( 'Y' ),
			'currency'           => 'EUR',
			'default_country_risk' => 'standard',  // low|standard|high
			'enable_woo_meta'    => 1,
			'enable_geo_capture' => 1,
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
		if ( isset( $input['operator_name'] ) ) {
			$out['operator_name'] = sanitize_text_field( (string) $input['operator_name'] );
		}
		if ( isset( $input['operator_eori'] ) ) {
			$eori = strtoupper( preg_replace( '/[^A-Z0-9]/', '', (string) $input['operator_eori'] ) );
			$out['operator_eori'] = $eori;
		}
		if ( isset( $input['operator_country'] ) ) {
			$cc = strtoupper( (string) $input['operator_country'] );
			if ( preg_match( '/^[A-Z]{2}$/', $cc ) ) {
				$out['operator_country'] = $cc;
			}
		}
		if ( isset( $input['operator_address'] ) ) {
			$out['operator_address'] = sanitize_textarea_field( (string) $input['operator_address'] );
		}
		if ( isset( $input['operator_role'] ) ) {
			$r = sanitize_key( (string) $input['operator_role'] );
			if ( in_array( $r, array( 'operator', 'trader', 'sme_operator', 'sme_trader' ), true ) ) {
				$out['operator_role'] = $r;
			}
		}
		if ( isset( $input['compliance_officer'] ) ) {
			$em = sanitize_email( (string) $input['compliance_officer'] );
			if ( is_email( $em ) ) {
				$out['compliance_officer'] = $em;
			}
		}
		if ( isset( $input['cutoff_date'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $input['cutoff_date'] ) ) {
			$out['cutoff_date'] = (string) $input['cutoff_date'];
		}
		if ( isset( $input['reporting_year'] ) ) {
			$out['reporting_year'] = max( 2024, min( 2099, (int) $input['reporting_year'] ) );
		}
		if ( isset( $input['currency'] ) ) {
			$cur = strtoupper( (string) $input['currency'] );
			if ( preg_match( '/^[A-Z]{3}$/', $cur ) ) {
				$out['currency'] = $cur;
			}
		}
		if ( isset( $input['default_country_risk'] ) ) {
			$risk = sanitize_key( (string) $input['default_country_risk'] );
			if ( in_array( $risk, array( 'low', 'standard', 'high' ), true ) ) {
				$out['default_country_risk'] = $risk;
			}
		}
		foreach ( array( 'enable_woo_meta', 'enable_geo_capture' ) as $b ) {
			$out[ $b ] = ! empty( $input[ $b ] ) ? 1 : 0;
		}
		return $out;
	}

	public static function is_sme() : bool {
		$s = self::get();
		return in_array( (string) $s['operator_role'], array( 'sme_operator', 'sme_trader' ), true );
	}
}
