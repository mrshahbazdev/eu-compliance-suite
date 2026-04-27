<?php
/**
 * Settings wrapper.
 *
 * @package EuroComply\Dashboard
 */

declare( strict_types = 1 );

namespace EuroComply\Dashboard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Settings {

	public const OPTION_KEY = 'eurocomply_dashboard_settings';

	/**
	 * @return array<string,mixed>
	 */
	public static function defaults() : array {
		return array(
			'show_inactive_plugins'   => 1,
			'auto_clear_dismissed'    => 0,
			'enable_daily_snapshot'   => 1,
			'snapshot_retention_days' => 90,
			'organisation_name'       => get_bloginfo( 'name' ),
			'organisation_country'    => 'DE',
			'compliance_officer_email'=> get_bloginfo( 'admin_email' ),
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

		foreach ( array( 'show_inactive_plugins', 'auto_clear_dismissed', 'enable_daily_snapshot' ) as $flag ) {
			$out[ $flag ] = ! empty( $input[ $flag ] ) ? 1 : 0;
		}
		if ( isset( $input['snapshot_retention_days'] ) ) {
			$out['snapshot_retention_days'] = max( 7, min( 3650, (int) $input['snapshot_retention_days'] ) );
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
		if ( isset( $input['compliance_officer_email'] ) ) {
			$e = sanitize_email( (string) $input['compliance_officer_email'] );
			if ( $e && is_email( $e ) ) {
				$out['compliance_officer_email'] = $e;
			}
		}
		return $out;
	}
}
