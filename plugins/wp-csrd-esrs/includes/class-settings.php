<?php
/**
 * Settings.
 *
 * @package EuroComply\CSRD
 */

declare( strict_types = 1 );

namespace EuroComply\CSRD;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Settings {

	public const OPTION_KEY = 'eurocomply_csrd_settings';

	/**
	 * @return array<string,mixed>
	 */
	public static function defaults() : array {
		return array(
			'company_name'         => get_bloginfo( 'name' ),
			'lei'                  => '',
			'country'              => 'DE',
			'company_size'         => 'large',     // large|listed_sme|micro|non_eu
			'employees'            => 0,
			'net_turnover_eur'     => 0,
			'balance_sheet_eur'    => 0,
			'is_listed'            => 0,
			'first_reporting_year' => (int) gmdate( 'Y' ),
			'reporting_year'       => (int) gmdate( 'Y' ),
			'assurance_level'      => 'limited',   // limited|reasonable|none
			'assurance_provider'   => '',
			'currency'             => 'EUR',
			'sustainability_officer_email' => get_bloginfo( 'admin_email' ),
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
		if ( isset( $input['company_name'] ) ) {
			$out['company_name'] = sanitize_text_field( (string) $input['company_name'] );
		}
		if ( isset( $input['lei'] ) ) {
			$lei = strtoupper( preg_replace( '/[^A-Z0-9]/', '', (string) $input['lei'] ) );
			if ( '' === $lei || 20 === strlen( $lei ) ) {
				$out['lei'] = $lei;
			}
		}
		if ( isset( $input['country'] ) ) {
			$cc = strtoupper( (string) $input['country'] );
			if ( preg_match( '/^[A-Z]{2}$/', $cc ) ) {
				$out['country'] = $cc;
			}
		}
		if ( isset( $input['company_size'] ) ) {
			$cs = sanitize_key( (string) $input['company_size'] );
			if ( in_array( $cs, array( 'large', 'listed_sme', 'micro', 'non_eu' ), true ) ) {
				$out['company_size'] = $cs;
			}
		}
		foreach ( array( 'employees', 'net_turnover_eur', 'balance_sheet_eur', 'first_reporting_year', 'reporting_year' ) as $intk ) {
			if ( isset( $input[ $intk ] ) ) {
				$out[ $intk ] = max( 0, (int) $input[ $intk ] );
			}
		}
		$out['is_listed'] = ! empty( $input['is_listed'] ) ? 1 : 0;
		if ( isset( $input['assurance_level'] ) ) {
			$al = sanitize_key( (string) $input['assurance_level'] );
			if ( in_array( $al, array( 'none', 'limited', 'reasonable' ), true ) ) {
				$out['assurance_level'] = $al;
			}
		}
		if ( isset( $input['assurance_provider'] ) ) {
			$out['assurance_provider'] = sanitize_text_field( (string) $input['assurance_provider'] );
		}
		if ( isset( $input['currency'] ) ) {
			$cur = strtoupper( (string) $input['currency'] );
			if ( preg_match( '/^[A-Z]{3}$/', $cur ) ) {
				$out['currency'] = $cur;
			}
		}
		if ( isset( $input['sustainability_officer_email'] ) ) {
			$em = sanitize_email( (string) $input['sustainability_officer_email'] );
			if ( is_email( $em ) ) {
				$out['sustainability_officer_email'] = $em;
			}
		}
		return $out;
	}

	/**
	 * Determine CSRD applicability and earliest reporting year per Directive 2022/2464.
	 *
	 * @return array{required:bool,first_year:int,note:string}
	 */
	public static function applicability() : array {
		$s = self::get();
		// PIE / large listed > 500 employees → first FY 2024 (report 2025).
		if ( ! empty( $s['is_listed'] ) && (int) $s['employees'] >= 500 ) {
			return array(
				'required'   => true,
				'first_year' => 2024,
				'note'       => __( 'PIE / large listed > 500 employees: report from FY 2024 (publish 2025).', 'eurocomply-csrd-esrs' ),
			);
		}
		// Other large undertakings (>250 employees OR >€50m turnover OR >€25m balance sheet).
		if ( 'large' === (string) $s['company_size']
			|| (int) $s['employees'] >= 250
			|| (int) $s['net_turnover_eur'] >= 50000000
			|| (int) $s['balance_sheet_eur'] >= 25000000 ) {
			return array(
				'required'   => true,
				'first_year' => 2025,
				'note'       => __( 'Large undertaking: report from FY 2025 (publish 2026).', 'eurocomply-csrd-esrs' ),
			);
		}
		// Listed SMEs.
		if ( 'listed_sme' === (string) $s['company_size'] || ! empty( $s['is_listed'] ) ) {
			return array(
				'required'   => true,
				'first_year' => 2026,
				'note'       => __( 'Listed SME: report from FY 2026 (publish 2027), with opt-out option until 2028.', 'eurocomply-csrd-esrs' ),
			);
		}
		// Non-EU parent with €150m EU turnover + EU branch/subsidiary.
		if ( 'non_eu' === (string) $s['company_size'] && (int) $s['net_turnover_eur'] >= 150000000 ) {
			return array(
				'required'   => true,
				'first_year' => 2028,
				'note'       => __( 'Third-country parent with > €150m EU turnover: report from FY 2028 (publish 2029).', 'eurocomply-csrd-esrs' ),
			);
		}
		return array(
			'required'   => false,
			'first_year' => 0,
			'note'       => __( 'Out of scope under current thresholds. Voluntary VSME standard available for unlisted SMEs.', 'eurocomply-csrd-esrs' ),
		);
	}
}
