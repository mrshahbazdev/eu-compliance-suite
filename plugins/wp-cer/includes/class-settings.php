<?php
/**
 * Settings.
 *
 * @package EuroComply\CER
 */

declare( strict_types = 1 );

namespace EuroComply\CER;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Settings {

	public const OPTION_KEY = 'eurocomply_cer_settings';

	/**
	 * @return array<string,mixed>
	 */
	public static function defaults() : array {
		return array(
			'entity_name'         => get_bloginfo( 'name' ),
			'entity_id'           => '',
			'entity_country'      => 'DE',
			'sector'              => 'digital_infrastructure',
			'sub_sector'          => '',
			'cross_border'        => 0,
			'compliance_officer'  => get_bloginfo( 'admin_email' ),
			'competent_authority' => '',
			'reporting_year'      => (int) gmdate( 'Y' ),
			'risk_review_years'   => 4,
			'enable_woo_meta'     => 0,
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
	 * @return array<string,mixed>
	 */
	public static function sanitize( array $input ) : array {
		$out = self::defaults();
		if ( isset( $input['entity_name'] ) ) {
			$out['entity_name'] = sanitize_text_field( (string) $input['entity_name'] );
		}
		if ( isset( $input['entity_id'] ) ) {
			$out['entity_id'] = sanitize_text_field( (string) $input['entity_id'] );
		}
		if ( isset( $input['entity_country'] ) ) {
			$cc = strtoupper( (string) $input['entity_country'] );
			if ( preg_match( '/^[A-Z]{2}$/', $cc ) ) {
				$out['entity_country'] = $cc;
			}
		}
		if ( isset( $input['sector'] ) ) {
			$s = sanitize_key( (string) $input['sector'] );
			if ( in_array( $s, array_keys( self::sectors() ), true ) ) {
				$out['sector'] = $s;
			}
		}
		if ( isset( $input['sub_sector'] ) ) {
			$out['sub_sector'] = sanitize_text_field( (string) $input['sub_sector'] );
		}
		$out['cross_border']    = ! empty( $input['cross_border'] )    ? 1 : 0;
		$out['enable_woo_meta'] = ! empty( $input['enable_woo_meta'] ) ? 1 : 0;
		if ( isset( $input['compliance_officer'] ) ) {
			$em = sanitize_email( (string) $input['compliance_officer'] );
			if ( is_email( $em ) ) {
				$out['compliance_officer'] = $em;
			}
		}
		if ( isset( $input['competent_authority'] ) ) {
			$out['competent_authority'] = sanitize_text_field( (string) $input['competent_authority'] );
		}
		if ( isset( $input['reporting_year'] ) ) {
			$out['reporting_year'] = max( 2024, min( 2099, (int) $input['reporting_year'] ) );
		}
		if ( isset( $input['risk_review_years'] ) ) {
			$out['risk_review_years'] = max( 1, min( 10, (int) $input['risk_review_years'] ) );
		}
		return $out;
	}

	/**
	 * Annex CER sectors with representative sub-sectors.
	 *
	 * @return array<string,array{name:string,sub:array<int,string>}>
	 */
	public static function sectors() : array {
		return array(
			'energy' => array(
				'name' => __( 'Energy', 'eurocomply-cer' ),
				'sub'  => array( 'electricity', 'district_heating', 'oil', 'gas', 'hydrogen' ),
			),
			'transport' => array(
				'name' => __( 'Transport', 'eurocomply-cer' ),
				'sub'  => array( 'air', 'rail', 'water', 'road', 'public_transport' ),
			),
			'banking' => array(
				'name' => __( 'Banking', 'eurocomply-cer' ),
				'sub'  => array( 'credit_institution' ),
			),
			'fmi' => array(
				'name' => __( 'Financial market infrastructure', 'eurocomply-cer' ),
				'sub'  => array( 'trading_venue', 'ccp', 'csd' ),
			),
			'health' => array(
				'name' => __( 'Health', 'eurocomply-cer' ),
				'sub'  => array( 'hospital', 'reference_lab', 'medical_devices', 'pharmaceutical' ),
			),
			'drinking_water' => array(
				'name' => __( 'Drinking water', 'eurocomply-cer' ),
				'sub'  => array( 'supplier', 'distribution' ),
			),
			'waste_water' => array(
				'name' => __( 'Waste water', 'eurocomply-cer' ),
				'sub'  => array( 'collection', 'treatment' ),
			),
			'digital_infrastructure' => array(
				'name' => __( 'Digital infrastructure', 'eurocomply-cer' ),
				'sub'  => array( 'ixp', 'dns', 'tld', 'cloud', 'datacentre', 'cdn', 'tsp', 'edsp' ),
			),
			'public_administration' => array(
				'name' => __( 'Public administration', 'eurocomply-cer' ),
				'sub'  => array( 'central', 'regional' ),
			),
			'space' => array(
				'name' => __( 'Space', 'eurocomply-cer' ),
				'sub'  => array( 'ground_segment' ),
			),
			'food' => array(
				'name' => __( 'Production, processing & distribution of food', 'eurocomply-cer' ),
				'sub'  => array( 'production', 'processing', 'wholesale_distribution' ),
			),
		);
	}

	public static function sector_name( string $key ) : string {
		$s = self::sectors();
		return isset( $s[ $key ] ) ? (string) $s[ $key ]['name'] : (string) $key;
	}
}
