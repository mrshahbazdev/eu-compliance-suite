<?php
/**
 * Settings storage.
 *
 * @package EuroComply\Eaa
 */

declare( strict_types = 1 );

namespace EuroComply\Eaa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Settings {

	public const OPTION = 'eurocomply_eaa_settings';

	public static function defaults() : array {
		return array(
			'inject_skip_link'      => 1,
			'focus_outline_polyfill'=> 0,
			'scan_on_save'          => 1,
			'statement_entity_name' => '',
			'statement_contact_email' => '',
			'statement_conformance' => 'partial',
			'statement_last_review' => '',
			'enabled_rules'         => array_keys( Rules::all() ),
		);
	}

	public static function seed_defaults() : void {
		if ( false === get_option( self::OPTION ) ) {
			add_option( self::OPTION, self::defaults() );
		}
	}

	public static function get() : array {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return array_merge( self::defaults(), $stored );
	}

	public static function value( string $key, $default = null ) {
		$s = self::get();
		return array_key_exists( $key, $s ) ? $s[ $key ] : $default;
	}

	public static function save( array $input ) : void {
		$current = self::get();
		$clean   = $current;

		$clean['inject_skip_link']        = empty( $input['inject_skip_link'] ) ? 0 : 1;
		$clean['focus_outline_polyfill']  = empty( $input['focus_outline_polyfill'] ) ? 0 : 1;
		$clean['scan_on_save']            = empty( $input['scan_on_save'] ) ? 0 : 1;
		$clean['statement_entity_name']   = isset( $input['statement_entity_name'] ) ? sanitize_text_field( (string) $input['statement_entity_name'] ) : '';
		$clean['statement_contact_email'] = isset( $input['statement_contact_email'] ) ? sanitize_email( (string) $input['statement_contact_email'] ) : '';
		$allowed_conf                     = array( 'full', 'partial', 'non' );
		$conf                             = isset( $input['statement_conformance'] ) ? (string) $input['statement_conformance'] : 'partial';
		$clean['statement_conformance']   = in_array( $conf, $allowed_conf, true ) ? $conf : 'partial';
		$clean['statement_last_review']   = isset( $input['statement_last_review'] ) ? sanitize_text_field( (string) $input['statement_last_review'] ) : '';

		if ( isset( $input['enabled_rules'] ) && is_array( $input['enabled_rules'] ) ) {
			$valid                   = array_keys( Rules::all() );
			$clean['enabled_rules']  = array_values( array_intersect( $valid, array_map( 'sanitize_key', $input['enabled_rules'] ) ) );
		} else {
			$clean['enabled_rules'] = array();
		}

		update_option( self::OPTION, $clean );
	}
}
