<?php
/**
 * Settings wrapper for EuroComply Age Verification.
 *
 * @package EuroComply\AgeVerification
 */

declare( strict_types = 1 );

namespace EuroComply\AgeVerification;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Settings {

	public const OPTION_KEY = 'eurocomply_av_settings';

	/**
	 * @return array<string,mixed>
	 */
	public static function defaults() : array {
		return array(
			'gate_mode'               => 'category', // 'site' | 'category' | 'shortcode_only'
			'default_min_age'         => 18,
			'verification_method'     => 'dob',      // 'dob' | 'checkbox' (checkbox = weaker, not JMStV-compliant)
			'cookie_days'              => 30,
			'blocked_redirect_url'    => '',
			'modal_title'             => __( 'Please verify your age', 'eurocomply-age-verification' ),
			'modal_body'              => __( 'To access this site, we need to confirm that you meet the minimum legal age under applicable EU / national law.', 'eurocomply-age-verification' ),
			'blocked_message'         => __( 'Sorry, you do not meet the minimum age to access this content.', 'eurocomply-age-verification' ),
			'pass_message'            => __( 'Thank you — your age has been verified.', 'eurocomply-age-verification' ),
			'restricted_categories'   => array(), // term_ids of product categories requiring verification
			'restricted_min_ages'     => array(), // term_id => min_age (per-category override)
			'country_rules'           => self::default_country_rules(),
			'log_blocked_attempts'    => 1,
			'exclude_admin_users'     => 1,
			'show_checkout_block'     => 1,
		);
	}

	/**
	 * Default per-country minimum-age rules for the "alcohol" class.
	 *
	 * Keyed by ISO-3166-1 alpha-2. Unknown countries fall back to default_min_age.
	 *
	 * @return array<string,int>
	 */
	public static function default_country_rules() : array {
		return array(
			'DE' => 16, // JMStV: beer/wine 16, spirits 18 (spirits handled via category override)
			'AT' => 16,
			'FR' => 18,
			'IT' => 18,
			'ES' => 18,
			'NL' => 18,
			'BE' => 18,
			'PT' => 18,
			'PL' => 18,
			'IE' => 18,
			'SE' => 20, // Systembolaget retail age
			'FI' => 18,
			'DK' => 18,
			'CZ' => 18,
			'GR' => 18,
			'RO' => 18,
			'HU' => 18,
			'SK' => 18,
			'SI' => 18,
			'HR' => 18,
			'BG' => 18,
			'LT' => 20,
			'LV' => 18,
			'EE' => 18,
			'CY' => 17,
			'MT' => 17,
			'LU' => 16,
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

		if ( isset( $input['gate_mode'] ) ) {
			$mode = sanitize_key( (string) $input['gate_mode'] );
			if ( in_array( $mode, array( 'site', 'category', 'shortcode_only' ), true ) ) {
				$out['gate_mode'] = $mode;
			}
		}

		if ( isset( $input['default_min_age'] ) ) {
			$out['default_min_age'] = max( 13, min( 21, (int) $input['default_min_age'] ) );
		}

		if ( isset( $input['verification_method'] ) ) {
			$method = sanitize_key( (string) $input['verification_method'] );
			if ( in_array( $method, array( 'dob', 'checkbox' ), true ) ) {
				// DOB is the default JMStV-compliant mode; checkbox is permitted on Free but flagged as weaker.
				$out['verification_method'] = $method;
			}
		}

		if ( isset( $input['cookie_days'] ) ) {
			$out['cookie_days'] = max( 0, min( 365, (int) $input['cookie_days'] ) );
		}

		if ( isset( $input['blocked_redirect_url'] ) ) {
			$out['blocked_redirect_url'] = esc_url_raw( (string) $input['blocked_redirect_url'] );
		}

		if ( isset( $input['modal_title'] ) ) {
			$out['modal_title'] = sanitize_text_field( (string) $input['modal_title'] );
		}
		if ( isset( $input['modal_body'] ) ) {
			$out['modal_body'] = sanitize_textarea_field( (string) $input['modal_body'] );
		}
		if ( isset( $input['blocked_message'] ) ) {
			$out['blocked_message'] = sanitize_text_field( (string) $input['blocked_message'] );
		}
		if ( isset( $input['pass_message'] ) ) {
			$out['pass_message'] = sanitize_text_field( (string) $input['pass_message'] );
		}

		if ( isset( $input['restricted_categories'] ) && is_array( $input['restricted_categories'] ) ) {
			$ids = array();
			foreach ( $input['restricted_categories'] as $raw ) {
				$id = (int) $raw;
				if ( $id > 0 ) {
					$ids[] = $id;
				}
			}
			$out['restricted_categories'] = array_values( array_unique( $ids ) );
		}

		if ( isset( $input['restricted_min_ages'] ) && is_array( $input['restricted_min_ages'] ) ) {
			$map = array();
			foreach ( $input['restricted_min_ages'] as $term_id => $age ) {
				$tid = (int) $term_id;
				$age = max( 13, min( 21, (int) $age ) );
				if ( $tid > 0 ) {
					$map[ $tid ] = $age;
				}
			}
			$out['restricted_min_ages'] = $map;
		}

		if ( isset( $input['country_rules'] ) && is_array( $input['country_rules'] ) ) {
			$rules = array();
			foreach ( $input['country_rules'] as $cc => $age ) {
				$cc  = strtoupper( sanitize_text_field( (string) $cc ) );
				$age = max( 13, min( 21, (int) $age ) );
				if ( strlen( $cc ) === 2 ) {
					$rules[ $cc ] = $age;
				}
			}
			if ( ! empty( $rules ) ) {
				$out['country_rules'] = $rules;
			}
		}

		$out['log_blocked_attempts'] = ! empty( $input['log_blocked_attempts'] ) ? 1 : 0;
		$out['exclude_admin_users']  = ! empty( $input['exclude_admin_users'] ) ? 1 : 0;
		$out['show_checkout_block']  = ! empty( $input['show_checkout_block'] ) ? 1 : 0;

		return $out;
	}

	public static function min_age_for_term( int $term_id ) : int {
		$s = self::get();
		if ( isset( $s['restricted_min_ages'][ $term_id ] ) ) {
			return (int) $s['restricted_min_ages'][ $term_id ];
		}
		return (int) $s['default_min_age'];
	}

	public static function min_age_for_country( string $cc ) : int {
		$s  = self::get();
		$cc = strtoupper( $cc );
		if ( isset( $s['country_rules'][ $cc ] ) ) {
			return (int) $s['country_rules'][ $cc ];
		}
		return (int) $s['default_min_age'];
	}
}
