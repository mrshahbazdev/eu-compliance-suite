<?php
/**
 * Settings.
 *
 * @package EuroComply\DORA
 */

declare( strict_types = 1 );

namespace EuroComply\DORA;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Settings {

	public const OPTION_KEY = 'eurocomply_dora_settings';

	/**
	 * @return array<string,mixed>
	 */
	public static function defaults() : array {
		return array(
			'entity_name'        => get_bloginfo( 'name' ),
			'entity_lei'         => '',
			'entity_country'     => 'DE',
			'entity_type'        => 'investment_firm', // credit_institution|investment_firm|payment_institution|emi|crypto_asset_provider|insurance|reinsurance|crowdfunding|other
			'entity_size'        => 'standard',         // micro|small|standard|significant
			'compliance_officer' => get_bloginfo( 'admin_email' ),
			'competent_authority' => '', // e.g. BaFin, AMF, CNMV, AFM
			'csirt_email'        => '',
			'reporting_year'     => (int) gmdate( 'Y' ),
			'currency'           => 'EUR',
			'major_clients_threshold' => 10000,    // RTS clients-affected indicative threshold
			'major_duration_minutes'  => 60,        // RTS duration indicative
			'major_data_loss_flag'    => 1,
			'enable_woo_meta'    => 0,
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
		if ( isset( $input['entity_lei'] ) ) {
			$lei = strtoupper( preg_replace( '/[^A-Z0-9]/', '', (string) $input['entity_lei'] ) );
			$out['entity_lei'] = substr( $lei, 0, 20 );
		}
		if ( isset( $input['entity_country'] ) ) {
			$cc = strtoupper( (string) $input['entity_country'] );
			if ( preg_match( '/^[A-Z]{2}$/', $cc ) ) {
				$out['entity_country'] = $cc;
			}
		}
		if ( isset( $input['entity_type'] ) ) {
			$t = sanitize_key( (string) $input['entity_type'] );
			if ( in_array( $t, array_keys( self::entity_types() ), true ) ) {
				$out['entity_type'] = $t;
			}
		}
		if ( isset( $input['entity_size'] ) ) {
			$sz = sanitize_key( (string) $input['entity_size'] );
			if ( in_array( $sz, array( 'micro', 'small', 'standard', 'significant' ), true ) ) {
				$out['entity_size'] = $sz;
			}
		}
		if ( isset( $input['compliance_officer'] ) ) {
			$em = sanitize_email( (string) $input['compliance_officer'] );
			if ( is_email( $em ) ) {
				$out['compliance_officer'] = $em;
			}
		}
		if ( isset( $input['competent_authority'] ) ) {
			$out['competent_authority'] = sanitize_text_field( (string) $input['competent_authority'] );
		}
		if ( isset( $input['csirt_email'] ) ) {
			$em = sanitize_email( (string) $input['csirt_email'] );
			if ( is_email( $em ) || '' === (string) $input['csirt_email'] ) {
				$out['csirt_email'] = $em ?: '';
			}
		}
		if ( isset( $input['reporting_year'] ) ) {
			$out['reporting_year'] = max( 2024, min( 2099, (int) $input['reporting_year'] ) );
		}
		if ( isset( $input['currency'] ) ) {
			$cur = strtoupper( (string) $input['currency'] );
			if ( preg_match( '/^[A-Z]{3}$/', $cur ) ) {
				$out['currency'] = $cur;
			}
		}
		if ( isset( $input['major_clients_threshold'] ) ) {
			$out['major_clients_threshold'] = max( 0, (int) $input['major_clients_threshold'] );
		}
		if ( isset( $input['major_duration_minutes'] ) ) {
			$out['major_duration_minutes'] = max( 0, (int) $input['major_duration_minutes'] );
		}
		$out['major_data_loss_flag'] = ! empty( $input['major_data_loss_flag'] ) ? 1 : 0;
		$out['enable_woo_meta']      = ! empty( $input['enable_woo_meta'] ) ? 1 : 0;
		return $out;
	}

	/**
	 * @return array<string,string>
	 */
	public static function entity_types() : array {
		return array(
			'credit_institution'     => __( 'Credit institution',                'eurocomply-dora' ),
			'investment_firm'        => __( 'Investment firm',                    'eurocomply-dora' ),
			'payment_institution'    => __( 'Payment institution',                'eurocomply-dora' ),
			'emi'                    => __( 'E-money institution',                'eurocomply-dora' ),
			'crypto_asset_provider'  => __( 'Crypto-asset service provider',      'eurocomply-dora' ),
			'insurance'              => __( 'Insurance undertaking',              'eurocomply-dora' ),
			'reinsurance'            => __( 'Reinsurance undertaking',            'eurocomply-dora' ),
			'crowdfunding'           => __( 'Crowdfunding service provider',      'eurocomply-dora' ),
			'other'                  => __( 'Other financial entity',             'eurocomply-dora' ),
		);
	}
}
