<?php
/**
 * License stub.
 *
 * @package EuroComply\CBAM
 */

declare( strict_types = 1 );

namespace EuroComply\CBAM;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class License {

	public const OPTION_KEY = 'eurocomply_cbam_license';

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
			return array( 'ok' => false, 'message' => __( 'Invalid license key. Expected EC-XXXXXX.', 'eurocomply-cbam' ) );
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
		return array( 'ok' => true, 'message' => __( 'Pro license active. TARIC sync, CBAM Registry API submission, signed PDF, supplier portal and 5,000-row CSV unlocked.', 'eurocomply-cbam' ) );
	}

	public static function deactivate() : void {
		delete_option( self::OPTION_KEY );
	}
}
