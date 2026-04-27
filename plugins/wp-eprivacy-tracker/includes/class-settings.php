<?php
/**
 * Settings wrapper.
 *
 * @package EuroComply\EPrivacy
 */

declare( strict_types = 1 );

namespace EuroComply\EPrivacy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Settings {

	public const OPTION_KEY = 'eurocomply_eprivacy_settings';

	/**
	 * @return array<string,mixed>
	 */
	public static function defaults() : array {
		return array(
			'scan_urls'                => array( '/' ),
			'enable_cookie_observer'   => 1,
			'observer_sample_rate'     => 10,
			'http_timeout'             => 15,
			'http_user_agent'          => 'EuroComply ePrivacy Scanner/0.1 (+wordpress)',
			'follow_redirects'         => 1,
			'consent_categories'       => array(
				'analytics'    => __( 'Analytics',    'eurocomply-eprivacy' ),
				'advertising'  => __( 'Advertising',  'eurocomply-eprivacy' ),
				'social'       => __( 'Social media', 'eurocomply-eprivacy' ),
				'functional'   => __( 'Functional',   'eurocomply-eprivacy' ),
				'preferences'  => __( 'Preferences',  'eurocomply-eprivacy' ),
			),
			'gap_email_recipients'     => '',
			'organisation_name'        => get_bloginfo( 'name' ),
			'organisation_country'     => 'DE',
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
		if ( isset( $input['scan_urls'] ) ) {
			if ( is_string( $input['scan_urls'] ) ) {
				$lines = preg_split( '/\r?\n/', $input['scan_urls'] );
			} else {
				$lines = (array) $input['scan_urls'];
			}
			$urls = array();
			foreach ( (array) $lines as $line ) {
				$line = trim( (string) $line );
				if ( '' === $line ) {
					continue;
				}
				if ( '/' === substr( $line, 0, 1 ) ) {
					$urls[] = $line;
				} elseif ( esc_url_raw( $line ) === $line ) {
					$urls[] = $line;
				} elseif ( filter_var( $line, FILTER_VALIDATE_URL ) ) {
					$urls[] = $line;
				}
			}
			$out['scan_urls'] = array_slice( array_values( array_unique( $urls ) ), 0, 50 );
		}
		foreach ( array( 'enable_cookie_observer', 'follow_redirects' ) as $flag ) {
			$out[ $flag ] = ! empty( $input[ $flag ] ) ? 1 : 0;
		}
		if ( isset( $input['observer_sample_rate'] ) ) {
			$out['observer_sample_rate'] = max( 1, min( 100, (int) $input['observer_sample_rate'] ) );
		}
		if ( isset( $input['http_timeout'] ) ) {
			$out['http_timeout'] = max( 3, min( 60, (int) $input['http_timeout'] ) );
		}
		if ( isset( $input['http_user_agent'] ) ) {
			$out['http_user_agent'] = sanitize_text_field( (string) $input['http_user_agent'] );
		}
		if ( isset( $input['gap_email_recipients'] ) ) {
			$emails = array_filter( array_map( 'sanitize_email', preg_split( '/[\s,;]+/', (string) $input['gap_email_recipients'] ) ) );
			$out['gap_email_recipients'] = implode( ', ', array_filter( $emails, 'is_email' ) );
		}
		if ( isset( $input['organisation_name'] ) ) {
			$out['organisation_name'] = sanitize_text_field( (string) $input['organisation_name'] );
		}
		if ( isset( $input['organisation_country'] ) ) {
			$cc = strtoupper( (string) $input['organisation_country'] );
			if ( preg_match( '/^[A-Z]{2}$/', $cc ) ) {
				$out['organisation_country'] = $cc;
			}
		}
		return $out;
	}
}
