<?php
/**
 * License stub.
 *
 * @package EuroComply\Whistleblower
 */

declare( strict_types = 1 );

namespace EuroComply\Whistleblower;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class License {

	public const OPTION_KEY = 'eurocomply_wb_license';

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
				'message' => __( 'Invalid license key. Expected EC-XXXXXX.', 'eurocomply-whistleblower' ),
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
		return array(
			'ok'      => true,
			'message' => __( 'Pro license active. PGP encryption, off-site storage, 2FA, REST API and signed PDF case bundle unlocked.', 'eurocomply-whistleblower' ),
		);
	}

	public static function deactivate() : void {
		delete_option( self::OPTION_KEY );
	}
}
