<?php
/**
 * Settings wrapper.
 *
 * @package EuroComply\DSAR
 */

declare( strict_types = 1 );

namespace EuroComply\DSAR;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Settings {

	public const OPTION_KEY = 'eurocomply_dsar_settings';

	/**
	 * @return array<string,mixed>
	 */
	public static function defaults() : array {
		return array(
			'response_deadline_days'   => 30, // Art. 12(3): one month, extensible +2 months (Pro).
			'verification_required'    => 1,
			'verification_token_ttl_h' => 48,
			'allow_anonymous_requests' => 1,
			'rate_limit_per_hour'      => 5,
			'from_email'               => get_bloginfo( 'admin_email' ),
			'from_name'                => get_bloginfo( 'name' ),
			'notification_emails'      => array( get_bloginfo( 'admin_email' ) ),
			'auto_ack_email'           => 1,
			'include_wc_orders'        => 1,
			'include_comments'         => 1,
			'include_user_meta'        => 1,
			'include_post_authorship'  => 1,
			'erasure_grace_days'       => 7,
			'retain_completed_days'    => 90,
			'page_id'                  => 0,
			'dpo_contact'              => '',
			'privacy_policy_url'       => get_privacy_policy_url(),
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

		if ( isset( $input['response_deadline_days'] ) ) {
			$out['response_deadline_days'] = max( 7, min( 90, (int) $input['response_deadline_days'] ) );
		}
		if ( isset( $input['verification_token_ttl_h'] ) ) {
			$out['verification_token_ttl_h'] = max( 1, min( 720, (int) $input['verification_token_ttl_h'] ) );
		}
		if ( isset( $input['rate_limit_per_hour'] ) ) {
			$out['rate_limit_per_hour'] = max( 0, min( 100, (int) $input['rate_limit_per_hour'] ) );
		}
		if ( isset( $input['erasure_grace_days'] ) ) {
			$out['erasure_grace_days'] = max( 0, min( 30, (int) $input['erasure_grace_days'] ) );
		}
		if ( isset( $input['retain_completed_days'] ) ) {
			$out['retain_completed_days'] = max( 7, min( 3650, (int) $input['retain_completed_days'] ) );
		}
		if ( isset( $input['page_id'] ) ) {
			$out['page_id'] = max( 0, (int) $input['page_id'] );
		}

		foreach ( array( 'verification_required', 'allow_anonymous_requests', 'auto_ack_email', 'include_wc_orders', 'include_comments', 'include_user_meta', 'include_post_authorship' ) as $flag ) {
			$out[ $flag ] = ! empty( $input[ $flag ] ) ? 1 : 0;
		}

		if ( isset( $input['from_email'] ) ) {
			$email = sanitize_email( (string) $input['from_email'] );
			if ( $email && is_email( $email ) ) {
				$out['from_email'] = $email;
			}
		}
		if ( isset( $input['from_name'] ) ) {
			$out['from_name'] = sanitize_text_field( (string) $input['from_name'] );
		}
		if ( isset( $input['dpo_contact'] ) ) {
			$out['dpo_contact'] = sanitize_textarea_field( (string) $input['dpo_contact'] );
		}
		if ( isset( $input['privacy_policy_url'] ) ) {
			$out['privacy_policy_url'] = esc_url_raw( (string) $input['privacy_policy_url'] );
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
