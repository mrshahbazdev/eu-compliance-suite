<?php
/**
 * Settings wrapper for EuroComply DSA Transparency.
 *
 * @package EuroComply\DSA
 */

declare( strict_types = 1 );

namespace EuroComply\DSA;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Settings {

	public const OPTION_KEY = 'eurocomply_dsa_settings';

	/**
	 * @return array<string,mixed>
	 */
	public static function defaults() : array {
		return array(
			'trader_form_require_login'    => 1,
			'notice_form_require_login'    => 0,
			'notice_form_honeypot'         => 1,
			'notice_form_rate_limit'       => 5,
			'notice_form_categories'       => array(
				'illegal_content',
				'counterfeit',
				'intellectual_property',
				'data_protection',
				'consumer_protection',
				'hate_speech',
				'other',
			),
			'auto_statement_on_trash'      => 1,
			'report_period'                => 'annual',
			'contact_point_email'          => '',
			'legal_representative'         => '',
			'terms_url'                    => '',
			'complaints_url'               => '',
			'transparency_page_id'         => 0,
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

		$out['trader_form_require_login']  = ! empty( $input['trader_form_require_login'] ) ? 1 : 0;
		$out['notice_form_require_login']  = ! empty( $input['notice_form_require_login'] ) ? 1 : 0;
		$out['notice_form_honeypot']       = ! empty( $input['notice_form_honeypot'] ) ? 1 : 0;
		$out['auto_statement_on_trash']    = ! empty( $input['auto_statement_on_trash'] ) ? 1 : 0;

		if ( isset( $input['notice_form_rate_limit'] ) ) {
			$out['notice_form_rate_limit'] = max( 0, min( 100, (int) $input['notice_form_rate_limit'] ) );
		}

		if ( isset( $input['notice_form_categories'] ) && is_array( $input['notice_form_categories'] ) ) {
			$cats = array();
			foreach ( $input['notice_form_categories'] as $cat ) {
				$key = sanitize_key( (string) $cat );
				if ( '' !== $key ) {
					$cats[] = $key;
				}
			}
			if ( ! empty( $cats ) ) {
				$out['notice_form_categories'] = array_values( array_unique( $cats ) );
			}
		}

		if ( isset( $input['report_period'] ) ) {
			$period = sanitize_key( (string) $input['report_period'] );
			if ( in_array( $period, array( 'annual', 'semiannual', 'quarterly' ), true ) ) {
				// 'semiannual' and 'quarterly' are Pro-gated; fall back to annual otherwise.
				if ( 'annual' === $period || License::is_pro() ) {
					$out['report_period'] = $period;
				}
			}
		}

		if ( isset( $input['contact_point_email'] ) ) {
			$email = sanitize_email( (string) $input['contact_point_email'] );
			if ( $email ) {
				$out['contact_point_email'] = $email;
			} else {
				$out['contact_point_email'] = '';
			}
		}

		if ( isset( $input['legal_representative'] ) ) {
			$out['legal_representative'] = sanitize_textarea_field( (string) $input['legal_representative'] );
		}

		if ( isset( $input['terms_url'] ) ) {
			$out['terms_url'] = esc_url_raw( (string) $input['terms_url'] );
		}

		if ( isset( $input['complaints_url'] ) ) {
			$out['complaints_url'] = esc_url_raw( (string) $input['complaints_url'] );
		}

		if ( isset( $input['transparency_page_id'] ) ) {
			$out['transparency_page_id'] = max( 0, (int) $input['transparency_page_id'] );
		}

		return $out;
	}
}
