<?php
/**
 * Settings store for EuroComply EPR Multi-Country Reporting.
 *
 * @package EuroComply\Epr
 */

declare( strict_types = 1 );

namespace EuroComply\Epr;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Settings {

	public const OPTION_KEY = 'eurocomply_epr_settings';

	/**
	 * A product is considered registered for a given country if the merchant
	 * has entered BOTH a registration number for that country AND declared at
	 * least one packaging material weight > 0.
	 *
	 * @var array<int,string>
	 */
	public const REQUIRED_PER_COUNTRY = array(
		'registration_number',
		'has_packaging_weight',
	);

	/**
	 * @return array<string,mixed>
	 */
	public static function defaults() : array {
		return array(
			'enabled_countries'   => array( 'FR', 'DE' ),
			'default_registrations' => array(
				'FR' => '',
				'DE' => '',
				'ES' => '',
				'IT' => '',
				'NL' => '',
				'AT' => '',
				'BE' => '',
			),
			'inherit_defaults'    => '1',
			'report_period'       => 'quarterly',
		);
	}

	public static function seed_defaults() : void {
		if ( false === get_option( self::OPTION_KEY ) ) {
			update_option( self::OPTION_KEY, self::defaults(), false );
		}
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function get() : array {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return array_replace_recursive( self::defaults(), $stored );
	}

	/**
	 * Persist a sanitised settings payload.
	 *
	 * @param array<string,mixed> $input Raw POST.
	 */
	public static function save( array $input ) : void {
		$current = self::get();
		$next    = $current;

		$enabled = array();
		if ( isset( $input['enabled_countries'] ) && is_array( $input['enabled_countries'] ) ) {
			foreach ( (array) $input['enabled_countries'] as $code ) {
				$code = strtoupper( sanitize_text_field( (string) wp_unslash( $code ) ) );
				if ( Countries::is_supported( $code ) ) {
					$enabled[] = $code;
				}
			}
		}
		$next['enabled_countries'] = array_values( array_unique( $enabled ) );

		if ( isset( $input['default_registrations'] ) && is_array( $input['default_registrations'] ) ) {
			$regs = $next['default_registrations'];
			foreach ( array_keys( Countries::all() ) as $code ) {
				$raw = isset( $input['default_registrations'][ $code ] )
					? sanitize_text_field( (string) wp_unslash( $input['default_registrations'][ $code ] ) )
					: '';
				$regs[ $code ] = $raw;
			}
			$next['default_registrations'] = $regs;
		}

		$next['inherit_defaults'] = ! empty( $input['inherit_defaults'] ) ? '1' : '0';

		if ( isset( $input['report_period'] ) ) {
			$period = sanitize_key( (string) wp_unslash( $input['report_period'] ) );
			if ( in_array( $period, array( 'monthly', 'quarterly', 'yearly' ), true ) ) {
				$next['report_period'] = $period;
			}
		}

		update_option( self::OPTION_KEY, $next, false );
	}
}
