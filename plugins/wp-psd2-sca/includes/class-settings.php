<?php
/**
 * Settings.
 *
 * @package EuroComply\PSD2
 */

declare( strict_types = 1 );

namespace EuroComply\PSD2;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Settings {

	public const OPTION_KEY = 'eurocomply_psd2_settings';

	/**
	 * @return array<string,mixed>
	 */
	public static function defaults() : array {
		return array(
			'psp_name'            => get_bloginfo( 'name' ),
			'psp_country'         => 'DE',
			'psp_bic'             => '',
			'reporting_officer'   => get_bloginfo( 'admin_email' ),
			'sca_provider'        => 'stripe',     // stripe|adyen|mollie|wirecard|paypal|other
			'tra_enabled'         => 1,
			'low_value_threshold' => 30,           // EUR — Art. 16 RTS
			'cumulative_cap'      => 100,          // EUR — Art. 16 RTS cumulative
			'recurring_exempt'    => 1,            // Art. 14 RTS
			'mit_exempt'          => 1,            // merchant-initiated transactions
			'trusted_beneficiary' => 1,            // Art. 13 RTS
			'tra_fraud_threshold' => 0.13,         // % unweighted reference fraud rate (per Art. 19 ETV)
			'refund_window_days'  => 1,            // Art. 73(1) PSD2 — by end of next business day
			'reporting_period'    => self::current_period(),
			'currency'            => 'EUR',
			'enable_3ds_log'      => 1,
			'enable_woo_meta'     => 1,
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

	public static function current_period() : string {
		$y = (int) gmdate( 'Y' );
		$m = (int) gmdate( 'n' );
		$q = (int) ceil( $m / 3 );
		return sprintf( '%04d-Q%d', $y, max( 1, min( 4, $q ) ) );
	}

	/**
	 * @param array<string,mixed> $input
	 * @return array<string,mixed>
	 */
	public static function sanitize( array $input ) : array {
		$out = self::defaults();
		if ( isset( $input['psp_name'] ) ) {
			$out['psp_name'] = sanitize_text_field( (string) $input['psp_name'] );
		}
		if ( isset( $input['psp_country'] ) ) {
			$cc = strtoupper( (string) $input['psp_country'] );
			if ( preg_match( '/^[A-Z]{2}$/', $cc ) ) {
				$out['psp_country'] = $cc;
			}
		}
		if ( isset( $input['psp_bic'] ) ) {
			$bic = strtoupper( preg_replace( '/[^A-Z0-9]/', '', (string) $input['psp_bic'] ) );
			if ( '' === $bic || preg_match( '/^[A-Z0-9]{8,11}$/', $bic ) ) {
				$out['psp_bic'] = $bic;
			}
		}
		if ( isset( $input['reporting_officer'] ) ) {
			$em = sanitize_email( (string) $input['reporting_officer'] );
			if ( is_email( $em ) ) {
				$out['reporting_officer'] = $em;
			}
		}
		if ( isset( $input['sca_provider'] ) ) {
			$p = sanitize_key( (string) $input['sca_provider'] );
			if ( in_array( $p, array( 'stripe', 'adyen', 'mollie', 'wirecard', 'paypal', 'other' ), true ) ) {
				$out['sca_provider'] = $p;
			}
		}
		foreach ( array( 'tra_enabled', 'recurring_exempt', 'mit_exempt', 'trusted_beneficiary', 'enable_3ds_log', 'enable_woo_meta' ) as $b ) {
			$out[ $b ] = ! empty( $input[ $b ] ) ? 1 : 0;
		}
		foreach ( array( 'low_value_threshold', 'cumulative_cap', 'refund_window_days' ) as $i ) {
			if ( isset( $input[ $i ] ) ) {
				$out[ $i ] = max( 0, (int) $input[ $i ] );
			}
		}
		if ( isset( $input['tra_fraud_threshold'] ) ) {
			$out['tra_fraud_threshold'] = max( 0.0, min( 5.0, (float) $input['tra_fraud_threshold'] ) );
		}
		if ( isset( $input['reporting_period'] ) && preg_match( '/^\d{4}-Q[1-4]$/', (string) $input['reporting_period'] ) ) {
			$out['reporting_period'] = (string) $input['reporting_period'];
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
