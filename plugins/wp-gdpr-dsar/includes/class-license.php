<?php
/**
 * License stub.
 *
 * @package EuroComply\DSAR
 */

declare( strict_types = 1 );

namespace EuroComply\DSAR;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class License {

	public const OPTION_KEY = 'eurocomply_dsar_license';

	public static function is_pro() : bool {
		$data = self::get();
		return ! empty( $data['status'] ) && 'active' === $data['status'];
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function get() : array {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return $stored;
	}

	/**
	 * @return array{ok:bool,message:string}
	 */
	public static function activate( string $key ) : array {
		$key = trim( $key );
		if ( ! preg_match( '/^EC-[A-Z0-9]{6,}$/', $key ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Invalid license key format. Expected EC-XXXXXX.', 'eurocomply-dsar' ),
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
			'message' => __( 'Pro license activated. CRM erasers, SFTP delivery, signed PDFs, MFA verify unlocked.', 'eurocomply-dsar' ),
		);
	}

	public static function deactivate() : void {
		delete_option( self::OPTION_KEY );
	}
}
