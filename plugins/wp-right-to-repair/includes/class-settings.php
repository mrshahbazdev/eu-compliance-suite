<?php
/**
 * Settings wrapper.
 *
 * @package EuroComply\R2R
 */

declare( strict_types = 1 );

namespace EuroComply\R2R;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Settings {

	public const OPTION_KEY = 'eurocomply_r2r_settings';

	/**
	 * Statutory minimum spare-parts availability per product category.
	 * Source: EU ecodesign regulations (washers/dryers 10y, dishwashers 10y, fridges 7y,
	 * TVs 7y, vacuums 7y, welding 10y, servers 7y, phones/tablets from 2025 — 7y).
	 *
	 * @return array<string,array{label:string,spare_parts_years:int}>
	 */
	public static function product_categories() : array {
		return array(
			'washing_machine'  => array( 'label' => __( 'Washing machine / washer-dryer', 'eurocomply-r2r' ),        'spare_parts_years' => 10 ),
			'dishwasher'       => array( 'label' => __( 'Dishwasher', 'eurocomply-r2r' ),                             'spare_parts_years' => 10 ),
			'refrigerator'     => array( 'label' => __( 'Refrigerator / freezer', 'eurocomply-r2r' ),                 'spare_parts_years' => 7  ),
			'display'          => array( 'label' => __( 'Television / display', 'eurocomply-r2r' ),                   'spare_parts_years' => 7  ),
			'vacuum_cleaner'   => array( 'label' => __( 'Vacuum cleaner', 'eurocomply-r2r' ),                         'spare_parts_years' => 7  ),
			'smartphone'       => array( 'label' => __( 'Smartphone / cordless phone', 'eurocomply-r2r' ),            'spare_parts_years' => 7  ),
			'tablet'           => array( 'label' => __( 'Slate tablet', 'eurocomply-r2r' ),                           'spare_parts_years' => 7  ),
			'welding'          => array( 'label' => __( 'Welding equipment', 'eurocomply-r2r' ),                      'spare_parts_years' => 10 ),
			'server'           => array( 'label' => __( 'Server / data-storage', 'eurocomply-r2r' ),                  'spare_parts_years' => 7  ),
			'light_source'     => array( 'label' => __( 'Light source / separate control gear', 'eurocomply-r2r' ),   'spare_parts_years' => 6  ),
			'power_supply'     => array( 'label' => __( 'External power supply', 'eurocomply-r2r' ),                  'spare_parts_years' => 5  ),
			'not_applicable'   => array( 'label' => __( 'Not covered by ESPR spare-parts rules', 'eurocomply-r2r' ),  'spare_parts_years' => 0  ),
		);
	}

	/**
	 * @return array<string,string>
	 */
	public static function energy_classes() : array {
		return array(
			'A' => 'A',
			'B' => 'B',
			'C' => 'C',
			'D' => 'D',
			'E' => 'E',
			'F' => 'F',
			'G' => 'G',
			'NA' => __( 'Not applicable', 'eurocomply-r2r' ),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function defaults() : array {
		return array(
			'show_energy_badge'         => 1,
			'show_repair_score_badge'   => 1,
			'show_spare_parts_years'    => 1,
			'show_repair_tab'           => 1,
			'show_on_shop_grid'         => 1,
			'default_warranty_years'    => 2,
			'commercial_warranty_years' => 0,
			'dpo_contact_url'           => '',
			'policy_url'                => '',
			'repair_contact_email'      => get_bloginfo( 'admin_email' ),
			'default_product_category'  => 'not_applicable',
			'country'                   => 'DE',
			'notify_on_missing_score'   => 0,
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
		$out = self::defaults();

		foreach ( array( 'show_energy_badge', 'show_repair_score_badge', 'show_spare_parts_years', 'show_repair_tab', 'show_on_shop_grid', 'notify_on_missing_score' ) as $flag ) {
			$out[ $flag ] = ! empty( $input[ $flag ] ) ? 1 : 0;
		}

		if ( isset( $input['default_warranty_years'] ) ) {
			$out['default_warranty_years'] = max( 0, min( 10, (int) $input['default_warranty_years'] ) );
		}
		if ( isset( $input['commercial_warranty_years'] ) ) {
			$out['commercial_warranty_years'] = max( 0, min( 10, (int) $input['commercial_warranty_years'] ) );
		}
		if ( isset( $input['dpo_contact_url'] ) ) {
			$out['dpo_contact_url'] = esc_url_raw( (string) $input['dpo_contact_url'] );
		}
		if ( isset( $input['policy_url'] ) ) {
			$out['policy_url'] = esc_url_raw( (string) $input['policy_url'] );
		}
		if ( isset( $input['repair_contact_email'] ) ) {
			$e = sanitize_email( (string) $input['repair_contact_email'] );
			if ( $e && is_email( $e ) ) {
				$out['repair_contact_email'] = $e;
			}
		}
		if ( isset( $input['default_product_category'] ) ) {
			$cat = sanitize_key( (string) $input['default_product_category'] );
			if ( isset( self::product_categories()[ $cat ] ) ) {
				$out['default_product_category'] = $cat;
			}
		}
		if ( isset( $input['country'] ) ) {
			$cc = strtoupper( (string) $input['country'] );
			if ( preg_match( '/^[A-Z]{2}$/', $cc ) ) {
				$out['country'] = $cc;
			}
		}

		return $out;
	}
}
