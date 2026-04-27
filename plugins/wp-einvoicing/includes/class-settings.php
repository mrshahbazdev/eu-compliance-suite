<?php
/**
 * Settings wrapper for EuroComply E-Invoicing.
 *
 * @package EuroComply\EInvoicing
 */

declare( strict_types = 1 );

namespace EuroComply\EInvoicing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Settings {

	public const OPTION_KEY = 'eurocomply_einv_settings';

	/**
	 * @return array<string,mixed>
	 */
	public static function defaults() : array {
		return array(
			'seller_name'         => (string) get_bloginfo( 'name' ),
			'seller_vat_id'       => '',
			'seller_tax_id'       => '',
			'seller_registration' => '',
			'seller_address_line' => '',
			'seller_postcode'     => '',
			'seller_city'         => '',
			'seller_country'      => 'DE',
			'seller_email'        => (string) get_bloginfo( 'admin_email' ),
			'currency'            => 'EUR',
			'invoice_prefix'      => 'INV-',
			'invoice_profile'     => 'minimum',
			'auto_generate'       => 1,
			'trigger_status'      => 'completed',
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
	 *
	 * @return array<string,mixed>
	 */
	public static function sanitize( array $input ) : array {
		$defaults = self::defaults();
		$out      = $defaults;

		foreach ( array( 'seller_name', 'seller_address_line', 'seller_city', 'seller_postcode', 'seller_registration', 'seller_tax_id', 'invoice_prefix' ) as $field ) {
			if ( isset( $input[ $field ] ) ) {
				$out[ $field ] = sanitize_text_field( (string) $input[ $field ] );
			}
		}

		if ( isset( $input['seller_vat_id'] ) ) {
			$out['seller_vat_id'] = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) $input['seller_vat_id'] ) );
		}

		if ( isset( $input['seller_email'] ) ) {
			$email = sanitize_email( (string) $input['seller_email'] );
			if ( $email ) {
				$out['seller_email'] = $email;
			}
		}

		if ( isset( $input['seller_country'] ) ) {
			$country = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $input['seller_country'] ) );
			if ( 2 === strlen( $country ) ) {
				$out['seller_country'] = $country;
			}
		}

		if ( isset( $input['currency'] ) ) {
			$currency = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $input['currency'] ) );
			if ( 3 === strlen( $currency ) ) {
				$out['currency'] = $currency;
			}
		}

		if ( isset( $input['invoice_profile'] ) ) {
			$profile = sanitize_key( (string) $input['invoice_profile'] );
			// Free tier only ships MINIMUM; BASIC/EN16931/EXTENDED are gated by License::is_pro().
			$allowed = License::is_pro()
				? array( 'minimum', 'basic', 'en16931', 'extended' )
				: array( 'minimum' );
			if ( in_array( $profile, $allowed, true ) ) {
				$out['invoice_profile'] = $profile;
			}
		}

		$out['auto_generate'] = ! empty( $input['auto_generate'] ) ? 1 : 0;

		if ( isset( $input['trigger_status'] ) ) {
			$status = sanitize_key( (string) $input['trigger_status'] );
			if ( in_array( $status, array( 'processing', 'completed' ), true ) ) {
				$out['trigger_status'] = $status;
			}
		}

		return $out;
	}
}
