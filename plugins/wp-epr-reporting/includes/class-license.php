<?php
/**
 * License stub for EuroComply EPR Multi-Country Reporting.
 *
 * Mirrors the pattern used by the other EuroComply plugins: any key matching /^EC-[A-Z0-9]{6,}$/
 * is accepted as a valid Pro key. Replace with a real HTTPS licensing endpoint before production.
 *
 * @package EuroComply\Epr
 */

declare( strict_types = 1 );

namespace EuroComply\Epr;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class License {

	public const OPTION_KEY = 'eurocomply_epr_license';

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
				'message' => __( 'Invalid license key format. Expected EC-XXXXXX.', 'eurocomply-epr' ),
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
			'message' => __( 'License activated. Pro features are now available.', 'eurocomply-epr' ),
		);
	}

	public static function deactivate() : void {
		delete_option( self::OPTION_KEY );
	}
}
