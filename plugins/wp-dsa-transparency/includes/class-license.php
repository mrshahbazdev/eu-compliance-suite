<?php
/**
 * License stub for EuroComply DSA Transparency.
 *
 * Same pattern as the other EuroComply plugins: any key matching
 * /^EC-[A-Z0-9]{6,}$/ is accepted as a valid Pro key. Replace with a real
 * HTTPS licensing endpoint before production.
 *
 * @package EuroComply\DSA
 */

declare( strict_types = 1 );

namespace EuroComply\DSA;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class License {

	public const OPTION_KEY = 'eurocomply_dsa_license';

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
				'message' => __( 'Invalid license key format. Expected EC-XXXXXX.', 'eurocomply-dsa' ),
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
			'message' => __( 'Pro license activated. DSA Transparency Database submission, dispute workflow, and marketplace integrations unlocked.', 'eurocomply-dsa' ),
		);
	}

	public static function deactivate() : void {
		delete_option( self::OPTION_KEY );
	}
}
