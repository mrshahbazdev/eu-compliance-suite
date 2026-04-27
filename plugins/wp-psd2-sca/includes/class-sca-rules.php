<?php
/**
 * SCA decision engine + exemption catalogue (Reg. (EU) 2018/389 RTS).
 *
 * @package EuroComply\PSD2
 */

declare( strict_types = 1 );

namespace EuroComply\PSD2;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ScaRules {

	/**
	 * @return array<string,array{name:string,article:string,description:string}>
	 */
	public static function exemptions() : array {
		return array(
			'low_value' => array(
				'name'        => __( 'Low value (Art. 16 RTS)',                          'eurocomply-psd2-sca' ),
				'article'     => 'Art. 16',
				'description' => __( 'Remote electronic payment ≤ €30, cumulative cap €100 or 5 consecutive payments since last SCA.', 'eurocomply-psd2-sca' ),
			),
			'recurring' => array(
				'name'        => __( 'Recurring of same amount (Art. 14 RTS)',           'eurocomply-psd2-sca' ),
				'article'     => 'Art. 14',
				'description' => __( 'First transaction needs SCA; subsequent fixed-amount recurring exempt to same payee.',         'eurocomply-psd2-sca' ),
			),
			'mit' => array(
				'name'        => __( 'Merchant-initiated transaction (out of scope)',    'eurocomply-psd2-sca' ),
				'article'     => 'EBA Q&A 2018_4031',
				'description' => __( 'Merchant-initiated transaction following a previously-mandated payment — SCA out of scope.',   'eurocomply-psd2-sca' ),
			),
			'trusted_beneficiary' => array(
				'name'        => __( 'Trusted beneficiary (Art. 13 RTS)',                'eurocomply-psd2-sca' ),
				'article'     => 'Art. 13',
				'description' => __( 'Payer added merchant to trusted-beneficiary list at issuer; subsequent payments exempt.',      'eurocomply-psd2-sca' ),
			),
			'corporate' => array(
				'name'        => __( 'Secure corporate payment (Art. 17 RTS)',           'eurocomply-psd2-sca' ),
				'article'     => 'Art. 17',
				'description' => __( 'Initiated through dedicated payment processes/protocols only available to non-consumers.',     'eurocomply-psd2-sca' ),
			),
			'tra' => array(
				'name'        => __( 'Transaction Risk Analysis (Art. 18 RTS)',          'eurocomply-psd2-sca' ),
				'article'     => 'Art. 18',
				'description' => __( 'Real-time risk scoring keeps fraud rate below ETV-tier reference rate (€100 / €250 / €500).',  'eurocomply-psd2-sca' ),
			),
			'one_leg' => array(
				'name'        => __( 'One-leg-out (issuer or acquirer outside EEA)',     'eurocomply-psd2-sca' ),
				'article'     => 'Art. 2 PSD2',
				'description' => __( 'SCA encouraged on best-effort basis when one party is outside EEA.',                          'eurocomply-psd2-sca' ),
			),
		);
	}

	/**
	 * Decide whether SCA is required and which (if any) exemption applies.
	 *
	 * @param array{
	 *   amount:float,
	 *   currency:string,
	 *   recurring?:bool,
	 *   merchant_initiated?:bool,
	 *   trusted_beneficiary?:bool,
	 *   corporate?:bool,
	 *   tra_score?:float,
	 *   issuer_eea?:bool,
	 *   acquirer_eea?:bool,
	 *   cumulative_since_sca?:float,
	 *   payments_since_sca?:int
	 * } $tx
	 * @return array{required:bool,exemption:string,reason:string}
	 */
	public static function decide( array $tx ) : array {
		$s = Settings::get();

		if ( ! empty( $tx['merchant_initiated'] ) ) {
			return array( 'required' => false, 'exemption' => 'mit', 'reason' => __( 'Merchant-initiated, SCA out of scope.', 'eurocomply-psd2-sca' ) );
		}
		if ( isset( $tx['issuer_eea'] ) && false === $tx['issuer_eea'] ) {
			return array( 'required' => false, 'exemption' => 'one_leg', 'reason' => __( 'Issuer outside EEA — best-effort SCA.', 'eurocomply-psd2-sca' ) );
		}
		if ( isset( $tx['acquirer_eea'] ) && false === $tx['acquirer_eea'] ) {
			return array( 'required' => false, 'exemption' => 'one_leg', 'reason' => __( 'Acquirer outside EEA — best-effort SCA.', 'eurocomply-psd2-sca' ) );
		}
		if ( ! empty( $tx['corporate'] ) ) {
			return array( 'required' => false, 'exemption' => 'corporate', 'reason' => __( 'Secure corporate payment (Art. 17).', 'eurocomply-psd2-sca' ) );
		}
		if ( ! empty( $tx['trusted_beneficiary'] ) && ! empty( $s['trusted_beneficiary'] ) ) {
			return array( 'required' => false, 'exemption' => 'trusted_beneficiary', 'reason' => __( 'Trusted beneficiary at issuer (Art. 13).', 'eurocomply-psd2-sca' ) );
		}
		if ( ! empty( $tx['recurring'] ) && ! empty( $s['recurring_exempt'] ) ) {
			return array( 'required' => false, 'exemption' => 'recurring', 'reason' => __( 'Recurring of same amount (Art. 14).', 'eurocomply-psd2-sca' ) );
		}

		$amount     = (float) ( $tx['amount']               ?? 0 );
		$cumulative = (float) ( $tx['cumulative_since_sca'] ?? 0 );
		$count      = (int)   ( $tx['payments_since_sca']   ?? 0 );
		if (
			$amount <= (float) $s['low_value_threshold']
			&& $cumulative <= (float) $s['cumulative_cap']
			&& $count < 5
		) {
			return array( 'required' => false, 'exemption' => 'low_value', 'reason' => __( 'Low-value remote payment (Art. 16).', 'eurocomply-psd2-sca' ) );
		}

		if ( ! empty( $s['tra_enabled'] ) && isset( $tx['tra_score'] ) ) {
			$score = (float) $tx['tra_score'];
			$tier  = self::tra_tier( $amount );
			if ( $score <= $tier['fraud_rate'] && $amount <= $tier['etv'] ) {
				return array(
					'required'  => false,
					'exemption' => 'tra',
					'reason'    => sprintf(
						/* translators: 1: ETV ceiling, 2: fraud rate */
						__( 'TRA exempt: amount ≤ €%1$d and current fraud rate ≤ %2$s%%.', 'eurocomply-psd2-sca' ),
						(int) $tier['etv'],
						(string) ( $tier['fraud_rate'] * 100 )
					),
				);
			}
		}

		return array( 'required' => true, 'exemption' => '', 'reason' => __( 'No exemption applies — Strong Customer Authentication required.', 'eurocomply-psd2-sca' ) );
	}

	/**
	 * @return array{etv:float,fraud_rate:float}
	 */
	public static function tra_tier( float $amount ) : array {
		// Art. 19 RTS: ETV ceilings and unweighted reference fraud rate per ETV tier.
		if ( $amount <= 100.0 ) {
			return array( 'etv' => 100.0, 'fraud_rate' => 0.0013 );  // 13 bps
		}
		if ( $amount <= 250.0 ) {
			return array( 'etv' => 250.0, 'fraud_rate' => 0.0006 );  // 6 bps
		}
		return array( 'etv' => 500.0, 'fraud_rate' => 0.0001 );      // 1 bp
	}
}
