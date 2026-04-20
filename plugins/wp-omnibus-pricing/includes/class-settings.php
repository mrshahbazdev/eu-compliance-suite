<?php
/**
 * Settings wrapper for EuroComply Omnibus.
 *
 * @package EuroComply\Omnibus
 */

declare( strict_types = 1 );

namespace EuroComply\Omnibus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Settings {

	public const OPTION_KEY = 'eurocomply_omnibus_settings';

	/**
	 * @return array<string,mixed>
	 */
	public static function defaults() : array {
		return array(
			'reference_days'        => 30,
			'display_position'      => 'below_price',
			'display_on_cart'       => 0,
			'display_on_loop'       => 1,
			'hide_when_no_history'  => 1,
			'label_template'        => __( 'Previous lowest price (last %d days): %s', 'eurocomply-omnibus' ),
			'auto_track_on_save'    => 1,
			'exclude_introductory'  => 1,
			'introductory_days'     => 30,
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
		$defaults = self::defaults();
		$out      = $defaults;

		if ( isset( $input['reference_days'] ) ) {
			$days    = (int) $input['reference_days'];
			$allowed = License::is_pro() ? array( 30, 60, 90, 180 ) : array( 30 );
			if ( in_array( $days, $allowed, true ) ) {
				$out['reference_days'] = $days;
			}
		}

		if ( isset( $input['display_position'] ) ) {
			$pos = sanitize_key( (string) $input['display_position'] );
			if ( in_array( $pos, array( 'below_price', 'above_price', 'after_addtocart' ), true ) ) {
				$out['display_position'] = $pos;
			}
		}

		if ( isset( $input['label_template'] ) ) {
			$out['label_template'] = sanitize_text_field( (string) $input['label_template'] );
		}

		if ( isset( $input['introductory_days'] ) ) {
			$out['introductory_days'] = max( 1, min( 365, (int) $input['introductory_days'] ) );
		}

		$out['display_on_cart']      = ! empty( $input['display_on_cart'] ) ? 1 : 0;
		$out['display_on_loop']      = ! empty( $input['display_on_loop'] ) ? 1 : 0;
		$out['hide_when_no_history'] = ! empty( $input['hide_when_no_history'] ) ? 1 : 0;
		$out['auto_track_on_save']   = ! empty( $input['auto_track_on_save'] ) ? 1 : 0;
		$out['exclude_introductory'] = ! empty( $input['exclude_introductory'] ) ? 1 : 0;

		return $out;
	}
}
