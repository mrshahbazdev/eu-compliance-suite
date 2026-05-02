<?php
/**
 * License gate.
 *
 * @package EuroComply\ForcedLabour
 */

declare( strict_types = 1 );

namespace EuroComply\ForcedLabour;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class License {

	private const OPTION = 'eurocomply_fl_license';

	public static function get() : array {
		$opt = get_option( self::OPTION, array() );
		return is_array( $opt ) ? $opt : array();
	}

	public static function is_pro() : bool {
		$lic = self::get();
		return ! empty( $lic['status'] ) && 'active' === $lic['status'];
	}

	public static function activate( string $key ) : bool {
		$key = trim( $key );
		if ( ! preg_match( '/^EC-[A-Z0-9]{6}$/', $key ) ) {
			return false;
		}
		update_option(
			self::OPTION,
			array(
				'key'          => $key,
				'status'       => 'active',
				'activated_at' => current_time( 'mysql', true ),
			)
		);
		return true;
	}

	public static function deactivate() : void {
		delete_option( self::OPTION );
	}
}
