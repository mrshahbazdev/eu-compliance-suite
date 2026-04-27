<?php
/**
 * License stub.
 *
 * @package EuroComply\Dashboard
 */

declare( strict_types = 1 );

namespace EuroComply\Dashboard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class License {

	public const OPTION_KEY = 'eurocomply_dashboard_license';

	public static function is_pro() : bool {
		$d = self::get();
		return ! empty( $d['status'] ) && 'active' === $d['status'];
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function get() : array {
		$stored = get_option( self::OPTION_KEY, array() );
		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * @return array{ok:bool,message:string}
	 */
	public static function activate( string $key ) : array {
		$key = trim( $key );
		if ( ! preg_match( '/^EC-[A-Z0-9]{6,}$/', $key ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Invalid license key. Expected EC-XXXXXX.', 'eurocomply-dashboard' ),
			);
		}
		update_option(
			self::OPTION_KEY,
			array(
				'key'       => $key,
				'status'    => 'active',
				'activated' => time(),
			),
			false
		);
		if ( ! wp_next_scheduled( 'eurocomply_dashboard_daily_snapshot' ) ) {
			wp_schedule_event( time() + 600, 'daily', 'eurocomply_dashboard_daily_snapshot' );
		}
		return array(
			'ok'      => true,
			'message' => __( 'Pro license active. Daily snapshots, REST API and SIEM webhook unlocked.', 'eurocomply-dashboard' ),
		);
	}

	public static function deactivate() : void {
		delete_option( self::OPTION_KEY );
		$ts = wp_next_scheduled( 'eurocomply_dashboard_daily_snapshot' );
		if ( $ts ) {
			wp_unschedule_event( $ts, 'eurocomply_dashboard_daily_snapshot' );
		}
	}
}
