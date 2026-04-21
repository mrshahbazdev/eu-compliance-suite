<?php
/**
 * Settings wrapper.
 *
 * @package EuroComply\NIS2
 */

declare( strict_types = 1 );

namespace EuroComply\NIS2;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Settings {

	public const OPTION_KEY = 'eurocomply_nis2_settings';

	/**
	 * @return array<string,mixed>
	 */
	public static function defaults() : array {
		return array(
			'entity_type'              => 'important', // essential | important | out_of_scope.
			'sector'                   => 'digital_service_provider',
			'organisation_name'        => get_bloginfo( 'name' ),
			'csirt_country'            => 'DE',
			'csirt_email'              => '',
			'notification_emails'      => array( get_bloginfo( 'admin_email' ) ),
			'retain_events_days'       => 365, // NIS2 recommends retention sufficient for post-incident review.
			'retain_incidents_days'    => 2555, // 7 years.
			'log_failed_logins'        => 1,
			'log_successful_logins'    => 1,
			'log_user_changes'         => 1,
			'log_plugin_changes'       => 1,
			'log_theme_changes'        => 1,
			'log_option_changes'       => 0, // noisy by default.
			'log_file_changes'         => 1, // core upgrader events.
			'public_vuln_form_enabled' => 1,
			'security_contact_email'   => get_bloginfo( 'admin_email' ),
			'security_policy_url'      => '',
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
		$out = self::defaults();

		if ( isset( $input['entity_type'] ) ) {
			$val = sanitize_key( (string) $input['entity_type'] );
			if ( in_array( $val, array( 'essential', 'important', 'out_of_scope' ), true ) ) {
				$out['entity_type'] = $val;
			}
		}
		if ( isset( $input['sector'] ) ) {
			$out['sector'] = sanitize_text_field( (string) $input['sector'] );
		}
		if ( isset( $input['organisation_name'] ) ) {
			$out['organisation_name'] = sanitize_text_field( (string) $input['organisation_name'] );
		}
		if ( isset( $input['csirt_country'] ) ) {
			$cc = strtoupper( (string) $input['csirt_country'] );
			if ( preg_match( '/^[A-Z]{2}$/', $cc ) ) {
				$out['csirt_country'] = $cc;
			}
		}
		if ( isset( $input['csirt_email'] ) ) {
			$e = sanitize_email( (string) $input['csirt_email'] );
			$out['csirt_email'] = $e && is_email( $e ) ? $e : '';
		}
		if ( isset( $input['security_contact_email'] ) ) {
			$e = sanitize_email( (string) $input['security_contact_email'] );
			if ( $e && is_email( $e ) ) {
				$out['security_contact_email'] = $e;
			}
		}
		if ( isset( $input['security_policy_url'] ) ) {
			$out['security_policy_url'] = esc_url_raw( (string) $input['security_policy_url'] );
		}
		if ( isset( $input['retain_events_days'] ) ) {
			$out['retain_events_days'] = max( 30, min( 3650, (int) $input['retain_events_days'] ) );
		}
		if ( isset( $input['retain_incidents_days'] ) ) {
			$out['retain_incidents_days'] = max( 365, min( 3650, (int) $input['retain_incidents_days'] ) );
		}

		foreach ( array( 'log_failed_logins', 'log_successful_logins', 'log_user_changes', 'log_plugin_changes', 'log_theme_changes', 'log_option_changes', 'log_file_changes', 'public_vuln_form_enabled' ) as $flag ) {
			$out[ $flag ] = ! empty( $input[ $flag ] ) ? 1 : 0;
		}

		if ( isset( $input['notification_emails'] ) ) {
			$raw    = is_array( $input['notification_emails'] )
				? $input['notification_emails']
				: preg_split( '/[\s,;]+/', (string) $input['notification_emails'] );
			$emails = array();
			foreach ( (array) $raw as $candidate ) {
				$candidate = sanitize_email( (string) $candidate );
				if ( $candidate && is_email( $candidate ) ) {
					$emails[] = $candidate;
				}
			}
			if ( ! empty( $emails ) ) {
				$out['notification_emails'] = array_values( array_unique( $emails ) );
			}
		}

		return $out;
	}
}
