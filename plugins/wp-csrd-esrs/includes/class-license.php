<?php
/**
 * License stub.
 *
 * @package EuroComply\CSRD
 */

declare( strict_types = 1 );

namespace EuroComply\CSRD;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class License {

	public const OPTION_KEY = 'eurocomply_csrd_license';

	public static function is_pro() : bool {
		$d = self::get();
		return ! empty( $d['status'] ) && 'active' === $d['status'];
	}

	public static function get() : array {
		$stored = get_option( self::OPTION_KEY, array() );
		return is_array( $stored ) ? $stored : array();
	}

	public static function activate( string $key ) : array {
		$key = trim( $key );
		if ( ! preg_match( '/^EC-[A-Z0-9]{6,}$/', $key ) ) {
			return array( 'ok' => false, 'message' => __( 'Invalid license key. Expected EC-XXXXXX.', 'eurocomply-csrd-esrs' ) );
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
		return array( 'ok' => true, 'message' => __( 'Pro license active. Full ESRS XBRL taxonomy, signed PDF, ESEF inline, REST API and supplier portal unlocked.', 'eurocomply-csrd-esrs' ) );
	}

	public static function deactivate() : void {
		delete_option( self::OPTION_KEY );
	}
}
