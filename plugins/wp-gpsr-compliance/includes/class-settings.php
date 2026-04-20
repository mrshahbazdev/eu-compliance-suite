<?php
/**
 * Settings store for EuroComply GPSR Compliance Manager.
 *
 * @package EuroComply\Gpsr
 */

declare( strict_types = 1 );

namespace EuroComply\Gpsr;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Settings {

	public const OPTION_KEY = 'eurocomply_gpsr_settings';

	/**
	 * Required meta keys for a product to be considered GPSR-compliant at the minimum level.
	 *
	 * Note: for products manufactured in the EU, the importer/EU-Rep can be blank. For non-EU
	 * manufactured products sold into the EU, an EU-Rep is mandatory. The dashboard surfaces
	 * both so the merchant can self-assess.
	 *
	 * @var array<int,string>
	 */
	public const REQUIRED_META = array(
		'_gpsr_manufacturer_name',
		'_gpsr_manufacturer_address',
	);

	/**
	 * Optional but strongly recommended meta keys (missing → warning, not error).
	 *
	 * @var array<int,string>
	 */
	public const RECOMMENDED_META = array(
		'_gpsr_importer_name',
		'_gpsr_importer_address',
		'_gpsr_eu_rep_name',
		'_gpsr_eu_rep_address',
		'_gpsr_warnings',
		'_gpsr_batch',
	);

	/**
	 * @return array<string,mixed>
	 */
	public static function defaults() : array {
		return array(
			'render_frontend'  => '1',
			'frontend_heading' => 'Product safety information (GPSR)',
			'show_in_schema'   => '1',
			'inherit_defaults' => '1',
			'default_manufacturer_name'    => '',
			'default_manufacturer_address' => '',
			'default_importer_name'        => '',
			'default_importer_address'     => '',
			'default_eu_rep_name'          => '',
			'default_eu_rep_address'       => '',
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

		foreach ( array( 'render_frontend', 'show_in_schema', 'inherit_defaults' ) as $flag ) {
			$next[ $flag ] = ! empty( $input[ $flag ] ) ? '1' : '0';
		}

		if ( isset( $input['frontend_heading'] ) ) {
			$next['frontend_heading'] = sanitize_text_field( (string) wp_unslash( $input['frontend_heading'] ) );
		}

		$text_fields = array(
			'default_manufacturer_name',
			'default_importer_name',
			'default_eu_rep_name',
		);
		foreach ( $text_fields as $field ) {
			if ( isset( $input[ $field ] ) ) {
				$next[ $field ] = sanitize_text_field( (string) wp_unslash( $input[ $field ] ) );
			}
		}

		$textarea_fields = array(
			'default_manufacturer_address',
			'default_importer_address',
			'default_eu_rep_address',
		);
		foreach ( $textarea_fields as $field ) {
			if ( isset( $input[ $field ] ) ) {
				$next[ $field ] = sanitize_textarea_field( (string) wp_unslash( $input[ $field ] ) );
			}
		}

		update_option( self::OPTION_KEY, $next, false );
	}
}
