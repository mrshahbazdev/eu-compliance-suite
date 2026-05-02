<?php
/**
 * Settings + taxonomies.
 *
 * @package EuroComply\ForcedLabour
 */

declare( strict_types = 1 );

namespace EuroComply\ForcedLabour;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Settings {

	private const OPTION = 'eurocomply_fl_settings';

	public static function defaults() : array {
		return array(
			'company_name'        => '',
			'compliance_officer'  => '',
			'submission_email'    => '',
			'reporting_year'      => (int) gmdate( 'Y' ),
			'high_risk_threshold' => 70,
			'log_changes'         => 1,
		);
	}

	public static function get() : array {
		$opt = get_option( self::OPTION, self::defaults() );
		return wp_parse_args( is_array( $opt ) ? $opt : array(), self::defaults() );
	}

	public static function update( array $changes ) : void {
		$current = self::get();
		$next    = wp_parse_args( $changes, $current );
		update_option( self::OPTION, $next );
	}

	/**
	 * Forced-labour risk indicator categories (ILO 11 indicators).
	 */
	public static function indicators() : array {
		return array(
			'abuse_of_vulnerability'    => __( 'Abuse of vulnerability', 'eurocomply-forced-labour' ),
			'deception'                 => __( 'Deception', 'eurocomply-forced-labour' ),
			'restriction_of_movement'   => __( 'Restriction of movement', 'eurocomply-forced-labour' ),
			'isolation'                 => __( 'Isolation', 'eurocomply-forced-labour' ),
			'physical_or_sexual_violence' => __( 'Physical or sexual violence', 'eurocomply-forced-labour' ),
			'intimidation_and_threats'  => __( 'Intimidation and threats', 'eurocomply-forced-labour' ),
			'retention_of_documents'    => __( 'Retention of identity documents', 'eurocomply-forced-labour' ),
			'withholding_of_wages'      => __( 'Withholding of wages', 'eurocomply-forced-labour' ),
			'debt_bondage'              => __( 'Debt bondage', 'eurocomply-forced-labour' ),
			'abusive_working_conditions'=> __( 'Abusive working / living conditions', 'eurocomply-forced-labour' ),
			'excessive_overtime'        => __( 'Excessive overtime', 'eurocomply-forced-labour' ),
		);
	}

	public static function severity_levels() : array {
		return array(
			'low'      => __( 'Low', 'eurocomply-forced-labour' ),
			'medium'   => __( 'Medium', 'eurocomply-forced-labour' ),
			'high'     => __( 'High', 'eurocomply-forced-labour' ),
			'critical' => __( 'Critical', 'eurocomply-forced-labour' ),
		);
	}

	public static function risk_status() : array {
		return array(
			'identified'  => __( 'Identified', 'eurocomply-forced-labour' ),
			'investigating' => __( 'Investigating', 'eurocomply-forced-labour' ),
			'mitigating'  => __( 'Mitigating', 'eurocomply-forced-labour' ),
			'resolved'    => __( 'Resolved', 'eurocomply-forced-labour' ),
			'unresolved'  => __( 'Unresolved', 'eurocomply-forced-labour' ),
			'banned'      => __( 'Banned product', 'eurocomply-forced-labour' ),
		);
	}

	public static function audit_schemes() : array {
		return array(
			'sa8000'              => 'SA8000 (SAI)',
			'amfori_bsci'         => 'amfori BSCI',
			'sedex_smeta'         => 'Sedex SMETA 4-pillar',
			'fla'                 => 'Fair Labor Association',
			'wrap'                => 'WRAP',
			'iso_45001'           => 'ISO 45001',
			'iso_26000'           => 'ISO 26000',
			'rba_vap'              => 'RBA Validated Assessment',
			'fairtrade'           => 'Fairtrade',
			'rainforest_alliance' => 'Rainforest Alliance',
			'utz'                 => 'UTZ',
			'rspo'                => 'RSPO',
			'gots'                => 'GOTS',
			'oeko_tex'            => 'OEKO-TEX',
			'first_party'         => __( 'First-party (self) audit', 'eurocomply-forced-labour' ),
			'second_party'        => __( 'Second-party (customer) audit', 'eurocomply-forced-labour' ),
			'other'               => __( 'Other', 'eurocomply-forced-labour' ),
		);
	}

	public static function submission_status() : array {
		return array(
			'received'      => __( 'Received', 'eurocomply-forced-labour' ),
			'acknowledged'  => __( 'Acknowledged', 'eurocomply-forced-labour' ),
			'investigating' => __( 'Investigating', 'eurocomply-forced-labour' ),
			'forwarded'     => __( 'Forwarded to authority', 'eurocomply-forced-labour' ),
			'closed'        => __( 'Closed', 'eurocomply-forced-labour' ),
			'rejected'      => __( 'Rejected', 'eurocomply-forced-labour' ),
		);
	}

	public static function withdrawal_status() : array {
		return array(
			'planned'   => __( 'Planned', 'eurocomply-forced-labour' ),
			'in_progress' => __( 'In progress', 'eurocomply-forced-labour' ),
			'completed' => __( 'Completed', 'eurocomply-forced-labour' ),
			'disputed'  => __( 'Disputed', 'eurocomply-forced-labour' ),
		);
	}

	/**
	 * Country risk seed list (ILO + Walk Free Global Slavery Index 2023 indicative tiers).
	 */
	public static function country_risk_seed() : array {
		return array(
			// High-risk regions per public indices.
			'CN-XJ' => 90, 'KP' => 100, 'ER' => 95, 'MR' => 85, 'TM' => 85, 'AF' => 90,
			'PK'    => 70, 'IN' => 60, 'BD' => 60, 'KH' => 60, 'MM' => 80, 'UZ' => 50,
			'TH'    => 55, 'VN' => 50, 'CN' => 65, 'TR' => 50, 'BR' => 45, 'CO' => 50,
			'CD'    => 75, 'NG' => 60, 'GH' => 55, 'CI' => 60, 'ET' => 65, 'YE' => 80,
			'SY'    => 80, 'IR' => 70, 'IQ' => 70, 'LY' => 75, 'SD' => 75, 'SS' => 80,
			'SO'    => 80, 'VE' => 65, 'NI' => 55, 'HN' => 50, 'MY' => 50,
		);
	}

	/**
	 * High-risk product/sector seed list.
	 */
	public static function high_risk_sectors() : array {
		return array(
			'cotton'      => __( 'Cotton & textiles', 'eurocomply-forced-labour' ),
			'apparel'     => __( 'Apparel & footwear', 'eurocomply-forced-labour' ),
			'fisheries'   => __( 'Seafood & fisheries', 'eurocomply-forced-labour' ),
			'electronics' => __( 'Electronics components', 'eurocomply-forced-labour' ),
			'polysilicon' => __( 'Polysilicon & solar panels', 'eurocomply-forced-labour' ),
			'cocoa'       => __( 'Cocoa', 'eurocomply-forced-labour' ),
			'coffee'      => __( 'Coffee', 'eurocomply-forced-labour' ),
			'sugar'       => __( 'Sugar', 'eurocomply-forced-labour' ),
			'palm_oil'    => __( 'Palm oil', 'eurocomply-forced-labour' ),
			'rubber'      => __( 'Natural rubber', 'eurocomply-forced-labour' ),
			'mining'      => __( 'Mining (cobalt, mica, gold)', 'eurocomply-forced-labour' ),
			'bricks'      => __( 'Bricks', 'eurocomply-forced-labour' ),
			'tobacco'     => __( 'Tobacco', 'eurocomply-forced-labour' ),
			'construction'=> __( 'Construction', 'eurocomply-forced-labour' ),
			'agriculture' => __( 'Agriculture (general)', 'eurocomply-forced-labour' ),
			'other'       => __( 'Other', 'eurocomply-forced-labour' ),
		);
	}
}
