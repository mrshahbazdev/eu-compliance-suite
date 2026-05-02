<?php
/**
 * Settings + taxonomy registry.
 *
 * @package EuroComply\CSDDD
 */

declare( strict_types = 1 );

namespace EuroComply\CSDDD;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Settings {

	public const OPTION_KEY = 'eurocomply_csddd_settings';

	/**
	 * @return array<string,mixed>
	 */
	public static function defaults() : array {
		return array(
			'company_name'           => (string) get_bloginfo( 'name' ),
			'employees_in_scope'     => 1000,
			'turnover_million_eur'   => 450,
			'reporting_year'         => (int) gmdate( 'Y' ),
			'climate_target_year'    => 2050,
			'climate_target_celsius' => '1.5',
			'compliance_officer'     => '',
			'complaint_email'        => (string) get_option( 'admin_email' ),
			'log_changes'            => 1,
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
	 */
	public static function save( array $input ) : void {
		$current  = self::get();
		$sanitised = array(
			'company_name'           => isset( $input['company_name'] ) ? sanitize_text_field( (string) $input['company_name'] ) : $current['company_name'],
			'employees_in_scope'     => isset( $input['employees_in_scope'] ) ? max( 0, (int) $input['employees_in_scope'] ) : $current['employees_in_scope'],
			'turnover_million_eur'   => isset( $input['turnover_million_eur'] ) ? max( 0, (int) $input['turnover_million_eur'] ) : $current['turnover_million_eur'],
			'reporting_year'         => isset( $input['reporting_year'] ) ? max( 2024, min( 2100, (int) $input['reporting_year'] ) ) : $current['reporting_year'],
			'climate_target_year'    => isset( $input['climate_target_year'] ) ? max( 2025, min( 2100, (int) $input['climate_target_year'] ) ) : $current['climate_target_year'],
			'climate_target_celsius' => isset( $input['climate_target_celsius'] ) ? sanitize_text_field( (string) $input['climate_target_celsius'] ) : $current['climate_target_celsius'],
			'compliance_officer'     => isset( $input['compliance_officer'] ) ? sanitize_text_field( (string) $input['compliance_officer'] ) : $current['compliance_officer'],
			'complaint_email'        => isset( $input['complaint_email'] ) ? sanitize_email( (string) $input['complaint_email'] ) : $current['complaint_email'],
			'log_changes'            => empty( $input['log_changes'] ) ? 0 : 1,
		);
		update_option( self::OPTION_KEY, $sanitised, false );
	}

	/**
	 * In-scope test per Art. 2 (post-Omnibus simplification): >1000 employees AND >€450m turnover.
	 *
	 * @return array{in_scope:bool,reason:string}
	 */
	public static function in_scope() : array {
		$d = self::get();
		if ( $d['employees_in_scope'] >= 1000 && $d['turnover_million_eur'] >= 450 ) {
			return array(
				'in_scope' => true,
				'reason'   => __( 'In scope: ≥1,000 employees AND ≥€450m worldwide net turnover (Art. 2(1)(a)).', 'eurocomply-csddd' ),
			);
		}
		return array(
			'in_scope' => false,
			'reason'   => __( 'Not in mandatory scope. Voluntary adoption still recommended for downstream EU customers.', 'eurocomply-csddd' ),
		);
	}

	/**
	 * @return array<string,string>
	 */
	public static function tier_levels() : array {
		return array(
			'tier_1'        => __( 'Tier 1 (direct supplier)', 'eurocomply-csddd' ),
			'tier_2'        => __( 'Tier 2 (sub-supplier of direct supplier)', 'eurocomply-csddd' ),
			'tier_3_plus'   => __( 'Tier 3+ (deeper in chain of activities)', 'eurocomply-csddd' ),
			'downstream'    => __( 'Downstream (distribution / disposal)', 'eurocomply-csddd' ),
			'business_partner' => __( 'Business partner (non-supply, e.g. lender)', 'eurocomply-csddd' ),
		);
	}

	/**
	 * Per Annex Part I (human rights) + Part II (environment).
	 *
	 * @return array<string,array{label:string,annex:string}>
	 */
	public static function risk_categories() : array {
		return array(
			// Part I — human rights.
			'forced_labour'        => array( 'label' => __( 'Forced or compulsory labour (ILO C29)', 'eurocomply-csddd' ),        'annex' => 'I.1' ),
			'child_labour'         => array( 'label' => __( 'Child labour (ILO C138 / C182)', 'eurocomply-csddd' ),               'annex' => 'I.2' ),
			'slavery'              => array( 'label' => __( 'Slavery / human trafficking', 'eurocomply-csddd' ),                  'annex' => 'I.3' ),
			'discrimination'       => array( 'label' => __( 'Discrimination in employment / occupation', 'eurocomply-csddd' ),    'annex' => 'I.4' ),
			'occupational_safety'  => array( 'label' => __( 'Occupational health & safety violations', 'eurocomply-csddd' ),      'annex' => 'I.5' ),
			'fair_wages'           => array( 'label' => __( 'Below-living-wage / withheld wages', 'eurocomply-csddd' ),            'annex' => 'I.6' ),
			'freedom_association'  => array( 'label' => __( 'Restrictions on freedom of association', 'eurocomply-csddd' ),       'annex' => 'I.7' ),
			'collective_bargaining' => array( 'label' => __( 'Restrictions on collective bargaining', 'eurocomply-csddd' ),        'annex' => 'I.8' ),
			'land_grabbing'        => array( 'label' => __( 'Unlawful eviction / land grabbing', 'eurocomply-csddd' ),             'annex' => 'I.9' ),
			'indigenous_rights'    => array( 'label' => __( 'Violation of indigenous peoples\' rights', 'eurocomply-csddd' ),     'annex' => 'I.10' ),
			'security_personnel'   => array( 'label' => __( 'Disproportionate use of security personnel', 'eurocomply-csddd' ),    'annex' => 'I.11' ),
			'living_environment'   => array( 'label' => __( 'Right to a healthy living environment', 'eurocomply-csddd' ),         'annex' => 'I.12' ),
			// Part II — environment.
			'biodiversity'         => array( 'label' => __( 'Biodiversity loss (CBD)', 'eurocomply-csddd' ),                       'annex' => 'II.1' ),
			'pollution_water'      => array( 'label' => __( 'Water pollution / depletion', 'eurocomply-csddd' ),                   'annex' => 'II.2' ),
			'pollution_air'        => array( 'label' => __( 'Air pollution exceeding limits', 'eurocomply-csddd' ),                'annex' => 'II.3' ),
			'pollution_soil'       => array( 'label' => __( 'Soil contamination / hazardous waste', 'eurocomply-csddd' ),          'annex' => 'II.4' ),
			'climate_emissions'    => array( 'label' => __( 'Greenhouse-gas emissions (Paris)', 'eurocomply-csddd' ),              'annex' => 'II.5' ),
			'cites_trade'          => array( 'label' => __( 'Illegal trade in endangered species (CITES)', 'eurocomply-csddd' ),  'annex' => 'II.6' ),
		);
	}

	/**
	 * @return array<string,string>
	 */
	public static function severity_levels() : array {
		return array(
			'low'      => __( 'Low', 'eurocomply-csddd' ),
			'medium'   => __( 'Medium', 'eurocomply-csddd' ),
			'high'     => __( 'High', 'eurocomply-csddd' ),
			'critical' => __( 'Critical', 'eurocomply-csddd' ),
		);
	}

	/**
	 * @return array<string,string>
	 */
	public static function risk_status() : array {
		return array(
			'identified'  => __( 'Identified', 'eurocomply-csddd' ),
			'mitigating'  => __( 'Mitigating (Art. 10)', 'eurocomply-csddd' ),
			'remediating' => __( 'Remediating (Art. 11)', 'eurocomply-csddd' ),
			'resolved'    => __( 'Resolved', 'eurocomply-csddd' ),
			'unresolved'  => __( 'Unresolved (escalation)', 'eurocomply-csddd' ),
		);
	}

	/**
	 * @return array<string,string>
	 */
	public static function action_types() : array {
		return array(
			'contract_clause'    => __( 'Contractual cascading clause', 'eurocomply-csddd' ),
			'audit'              => __( 'Third-party audit / verification', 'eurocomply-csddd' ),
			'training'           => __( 'Supplier training', 'eurocomply-csddd' ),
			'investment'         => __( 'Investment / capacity building', 'eurocomply-csddd' ),
			'corrective_plan'    => __( 'Corrective action plan with deadlines', 'eurocomply-csddd' ),
			'remediation'        => __( 'Remediation (compensation / restoration)', 'eurocomply-csddd' ),
			'temporary_suspend'  => __( 'Temporary suspension of business relationship', 'eurocomply-csddd' ),
			'terminate'          => __( 'Termination of business relationship (last resort)', 'eurocomply-csddd' ),
		);
	}

	/**
	 * @return array<string,string>
	 */
	public static function complaint_status() : array {
		return array(
			'received'       => __( 'Received', 'eurocomply-csddd' ),
			'acknowledged'   => __( 'Acknowledged', 'eurocomply-csddd' ),
			'investigating'  => __( 'Investigating', 'eurocomply-csddd' ),
			'action_taken'   => __( 'Action taken', 'eurocomply-csddd' ),
			'closed'         => __( 'Closed', 'eurocomply-csddd' ),
			'rejected'       => __( 'Rejected (out of scope)', 'eurocomply-csddd' ),
		);
	}
}
