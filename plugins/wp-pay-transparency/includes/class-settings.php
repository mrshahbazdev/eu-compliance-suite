<?php
/**
 * Settings + reporting thresholds.
 *
 * @package EuroComply\PayTransparency
 */

declare( strict_types = 1 );

namespace EuroComply\PayTransparency;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Settings {

	public const OPTION_KEY = 'eurocomply_pay_transparency_settings';

	/**
	 * @return array<string,mixed>
	 */
	public static function defaults() : array {
		return array(
			'organisation_name'      => get_bloginfo( 'name' ),
			'organisation_country'   => 'DE',
			'employees_total'        => 0,
			'currency'               => 'EUR',
			'reporting_year'         => (int) gmdate( 'Y' ) - 1,
			'enable_job_ad_filter'   => 1,
			'job_post_types'         => array( 'post' ),
			'pay_range_required'     => 1,
			'progression_criteria'   => '',
			'pay_setting_criteria'   => '',
			'request_response_days'  => 60,
			'public_request_page_id' => 0,
			'public_policy_page_id'  => 0,
			'compliance_email'       => get_bloginfo( 'admin_email' ),
			'rate_limit_per_hour'    => 5,
			'joint_assessment_threshold' => 5.0,
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
		if ( isset( $input['organisation_name'] ) ) {
			$out['organisation_name'] = sanitize_text_field( (string) $input['organisation_name'] );
		}
		if ( isset( $input['organisation_country'] ) ) {
			$cc = strtoupper( (string) $input['organisation_country'] );
			if ( preg_match( '/^[A-Z]{2}$/', $cc ) ) {
				$out['organisation_country'] = $cc;
			}
		}
		if ( isset( $input['employees_total'] ) ) {
			$out['employees_total'] = max( 0, (int) $input['employees_total'] );
		}
		if ( isset( $input['currency'] ) ) {
			$cur = strtoupper( (string) $input['currency'] );
			if ( preg_match( '/^[A-Z]{3}$/', $cur ) ) {
				$out['currency'] = $cur;
			}
		}
		if ( isset( $input['reporting_year'] ) ) {
			$y = (int) $input['reporting_year'];
			if ( $y >= 2020 && $y <= 2099 ) {
				$out['reporting_year'] = $y;
			}
		}
		foreach ( array( 'enable_job_ad_filter', 'pay_range_required' ) as $flag ) {
			$out[ $flag ] = ! empty( $input[ $flag ] ) ? 1 : 0;
		}
		if ( isset( $input['job_post_types'] ) ) {
			$types = is_array( $input['job_post_types'] ) ? $input['job_post_types'] : preg_split( '/[\s,]+/', (string) $input['job_post_types'] );
			$out['job_post_types'] = array_values( array_filter( array_map( 'sanitize_key', (array) $types ) ) );
			if ( ! $out['job_post_types'] ) {
				$out['job_post_types'] = array( 'post' );
			}
		}
		if ( isset( $input['progression_criteria'] ) ) {
			$out['progression_criteria'] = wp_kses_post( (string) $input['progression_criteria'] );
		}
		if ( isset( $input['pay_setting_criteria'] ) ) {
			$out['pay_setting_criteria'] = wp_kses_post( (string) $input['pay_setting_criteria'] );
		}
		if ( isset( $input['request_response_days'] ) ) {
			$out['request_response_days'] = max( 1, min( 120, (int) $input['request_response_days'] ) );
		}
		foreach ( array( 'public_request_page_id', 'public_policy_page_id' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$out[ $key ] = max( 0, (int) $input[ $key ] );
			}
		}
		if ( isset( $input['compliance_email'] ) ) {
			$email = sanitize_email( (string) $input['compliance_email'] );
			if ( is_email( $email ) ) {
				$out['compliance_email'] = $email;
			}
		}
		if ( isset( $input['rate_limit_per_hour'] ) ) {
			$out['rate_limit_per_hour'] = max( 1, min( 100, (int) $input['rate_limit_per_hour'] ) );
		}
		if ( isset( $input['joint_assessment_threshold'] ) ) {
			$out['joint_assessment_threshold'] = max( 0.0, min( 100.0, (float) $input['joint_assessment_threshold'] ) );
		}
		return $out;
	}

	/**
	 * Reporting cadence per Art. 9 (rolled out 7 Jun 2027 → 2031).
	 *
	 * @return array{required:bool,frequency:string,note:string}
	 */
	public static function reporting_obligation() : array {
		$count = (int) self::get()['employees_total'];
		if ( $count >= 250 ) {
			return array(
				'required'  => true,
				'frequency' => 'annual',
				'note'      => __( 'Art. 9(2): annual report required.', 'eurocomply-pay-transparency' ),
			);
		}
		if ( $count >= 150 ) {
			return array(
				'required'  => true,
				'frequency' => 'triennial',
				'note'      => __( 'Art. 9(3): report every 3 years (effective 2027 onward).', 'eurocomply-pay-transparency' ),
			);
		}
		if ( $count >= 100 ) {
			return array(
				'required'  => true,
				'frequency' => 'triennial',
				'note'      => __( 'Art. 9(4): report every 3 years (effective 2031 onward for 100–149 employers).', 'eurocomply-pay-transparency' ),
			);
		}
		return array(
			'required'  => false,
			'frequency' => 'voluntary',
			'note'      => __( 'Below the 100-employee threshold: no statutory reporting obligation, but pay-range disclosure on job ads (Art. 5) and worker information rights (Art. 7) still apply.', 'eurocomply-pay-transparency' ),
		);
	}
}
