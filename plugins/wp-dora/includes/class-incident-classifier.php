<?php
/**
 * DORA incident classifier (Art. 18 + RTS / Commission Delegated Reg. on
 * classification of major ICT-related incidents).
 *
 * @package EuroComply\DORA
 */

declare( strict_types = 1 );

namespace EuroComply\DORA;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class IncidentClassifier {

	/**
	 * Classify an incident as major / significant / none using the configured
	 * RTS-aligned thresholds. Returns reasoning so operators understand why.
	 *
	 * @param array{
	 *   clients_affected?:int,
	 *   data_loss?:bool,
	 *   duration_min?:int,
	 *   geo_spread?:int,
	 *   financial_impact?:float,
	 *   reputational?:bool,
	 *   critical_service?:bool
	 * } $tx
	 *
	 * @return array{class:string, reasons:array<int,string>}
	 */
	public static function classify( array $tx ) : array {
		$s        = Settings::get();
		$reasons  = array();
		$score    = 0;

		$clients_threshold = (int) ( $s['major_clients_threshold'] ?? 10000 );
		$duration_threshold = (int) ( $s['major_duration_minutes'] ?? 60 );

		if ( ! empty( $tx['critical_service'] ) ) {
			$reasons[] = __( 'Affects a critical or important function (Art. 19(1)(a)).', 'eurocomply-dora' );
			$score    += 3;
		}
		if ( isset( $tx['clients_affected'] ) && (int) $tx['clients_affected'] >= $clients_threshold ) {
			$reasons[] = sprintf( /* translators: %d: count */ __( 'Clients / counterparties affected (%d) ≥ threshold.', 'eurocomply-dora' ), (int) $tx['clients_affected'] );
			$score    += 3;
		} elseif ( isset( $tx['clients_affected'] ) && (int) $tx['clients_affected'] >= (int) ( $clients_threshold / 10 ) ) {
			$reasons[] = sprintf( /* translators: %d: count */ __( 'Clients / counterparties affected (%d) ≥ 10%% of threshold.', 'eurocomply-dora' ), (int) $tx['clients_affected'] );
			$score    += 1;
		}
		if ( ! empty( $tx['data_loss'] ) && ! empty( $s['major_data_loss_flag'] ) ) {
			$reasons[] = __( 'Data losses, including personal data, occurred.', 'eurocomply-dora' );
			$score    += 2;
		}
		if ( isset( $tx['duration_min'] ) && (int) $tx['duration_min'] >= $duration_threshold ) {
			$reasons[] = sprintf( /* translators: %d: minutes */ __( 'Duration (%d min) ≥ threshold.', 'eurocomply-dora' ), (int) $tx['duration_min'] );
			$score    += 2;
		}
		if ( isset( $tx['geo_spread'] ) && (int) $tx['geo_spread'] >= 2 ) {
			$reasons[] = sprintf( /* translators: %d: count */ __( 'Geographical spread: affects %d Member States.', 'eurocomply-dora' ), (int) $tx['geo_spread'] );
			$score    += 2;
		}
		if ( isset( $tx['financial_impact'] ) && (float) $tx['financial_impact'] > 0 ) {
			$reasons[] = sprintf( /* translators: %s: amount */ __( 'Economic impact: %s.', 'eurocomply-dora' ), number_format_i18n( (float) $tx['financial_impact'], 2 ) );
			$score    += 1;
		}
		if ( ! empty( $tx['reputational'] ) ) {
			$reasons[] = __( 'Reputational impact identified.', 'eurocomply-dora' );
			$score    += 1;
		}

		if ( $score >= 5 ) {
			return array( 'class' => 'major', 'reasons' => $reasons );
		}
		if ( $score >= 2 ) {
			return array( 'class' => 'significant', 'reasons' => $reasons );
		}
		return array( 'class' => 'none', 'reasons' => $reasons );
	}

	/**
	 * Initial / intermediate / final reporting deadlines for major incidents
	 * (4 hours / 72 hours / 1 month — Art. 19 timelines + RTS).
	 *
	 * @return array{initial:string, intermediate:string, final:string}
	 */
	public static function deadlines( int $classified_at_ts ) : array {
		return array(
			'initial'      => gmdate( 'Y-m-d H:i:s', $classified_at_ts + 4 * 3600 ),
			'intermediate' => gmdate( 'Y-m-d H:i:s', $classified_at_ts + 72 * 3600 ),
			'final'        => gmdate( 'Y-m-d H:i:s', $classified_at_ts + 30 * 24 * 3600 ),
		);
	}
}
