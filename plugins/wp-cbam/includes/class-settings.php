<?php
/**
 * Settings.
 *
 * @package EuroComply\CBAM
 */

declare( strict_types = 1 );

namespace EuroComply\CBAM;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Settings {

	public const OPTION_KEY = 'eurocomply_cbam_settings';

	/**
	 * @return array<string,mixed>
	 */
	public static function defaults() : array {
		return array(
			'declarant_name'             => get_bloginfo( 'name' ),
			'declarant_eori'             => '',
			'declarant_country'          => 'DE',
			'authorised_declarant_id'    => '',
			'reporting_officer_email'    => get_bloginfo( 'admin_email' ),
			'reporting_period'           => self::current_period(),
			'enable_product_meta'        => 1,
			'show_emissions_on_frontend' => 1,
			'default_emissions_factor'   => 0.0,
			'currency'                   => 'EUR',
			'use_default_values'         => 1,
		);
	}

	public static function current_period() : string {
		$year    = (int) gmdate( 'Y' );
		$quarter = (int) ceil( ( (int) gmdate( 'n' ) ) / 3 );
		return sprintf( '%d-Q%d', $year, max( 1, min( 4, $quarter ) ) );
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
		if ( isset( $input['declarant_name'] ) ) {
			$out['declarant_name'] = sanitize_text_field( (string) $input['declarant_name'] );
		}
		if ( isset( $input['declarant_eori'] ) ) {
			$eori = strtoupper( preg_replace( '/[^A-Z0-9]/', '', (string) $input['declarant_eori'] ) );
			if ( '' === $eori || preg_match( '/^[A-Z]{2}[A-Z0-9]{1,15}$/', $eori ) ) {
				$out['declarant_eori'] = $eori;
			}
		}
		if ( isset( $input['declarant_country'] ) ) {
			$cc = strtoupper( (string) $input['declarant_country'] );
			if ( preg_match( '/^[A-Z]{2}$/', $cc ) ) {
				$out['declarant_country'] = $cc;
			}
		}
		if ( isset( $input['authorised_declarant_id'] ) ) {
			$out['authorised_declarant_id'] = sanitize_text_field( (string) $input['authorised_declarant_id'] );
		}
		if ( isset( $input['reporting_officer_email'] ) ) {
			$email = sanitize_email( (string) $input['reporting_officer_email'] );
			if ( is_email( $email ) ) {
				$out['reporting_officer_email'] = $email;
			}
		}
		if ( isset( $input['reporting_period'] ) ) {
			$rp = strtoupper( preg_replace( '/[^0-9Q-]/', '', (string) $input['reporting_period'] ) );
			if ( preg_match( '/^\d{4}-Q[1-4]$/', $rp ) ) {
				$out['reporting_period'] = $rp;
			}
		}
		foreach ( array( 'enable_product_meta', 'show_emissions_on_frontend', 'use_default_values' ) as $flag ) {
			$out[ $flag ] = ! empty( $input[ $flag ] ) ? 1 : 0;
		}
		if ( isset( $input['default_emissions_factor'] ) ) {
			$out['default_emissions_factor'] = max( 0.0, (float) $input['default_emissions_factor'] );
		}
		if ( isset( $input['currency'] ) ) {
			$cur = strtoupper( (string) $input['currency'] );
			if ( preg_match( '/^[A-Z]{3}$/', $cur ) ) {
				$out['currency'] = $cur;
			}
		}
		return $out;
	}
}
