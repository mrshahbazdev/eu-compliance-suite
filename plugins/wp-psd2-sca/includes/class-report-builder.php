<?php
/**
 * Quarterly fraud report (Art. 96(6) PSD2 + EBA GL/2018/05) builder.
 *
 * @package EuroComply\PSD2
 */

declare( strict_types = 1 );

namespace EuroComply\PSD2;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ReportBuilder {

	/**
	 * @return array{period:string,xml:string,payload:string,fraud_count:int,fraud_value:float,fraud_rate:float}
	 */
	public static function build( string $period ) : array {
		$settings = Settings::get();
		$txns     = TransactionStore::for_period( $period, 50000 );
		$frauds   = FraudStore::for_period( $period );

		$total_count = count( $txns );
		$total_value = 0.0;
		foreach ( $txns as $t ) {
			$total_value += (float) $t['amount'];
		}
		$fraud_count = count( $frauds );
		$fraud_value = 0.0;
		foreach ( $frauds as $f ) {
			$fraud_value += (float) $f['amount'];
		}
		$fraud_rate = $total_value > 0 ? round( $fraud_value / $total_value, 6 ) : 0.0;
		$breakdown  = TransactionStore::exemption_breakdown( $period );
		$challenge_failure = TransactionStore::challenge_failure_rate( $period );
		$refund_compliance = FraudStore::refund_compliance( $period );

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<FraudReport xmlns="urn:psd2:eurocomply:0.1" framework="EBA-GL-2018-05" article="PSD2.96.6">' . "\n";
		$xml .= '  <Header>' . "\n";
		$xml .= '    <PSP>' . self::esc( (string) $settings['psp_name'] ) . '</PSP>' . "\n";
		$xml .= '    <Country>' . self::esc( (string) $settings['psp_country'] ) . '</Country>' . "\n";
		$xml .= '    <BIC>' . self::esc( (string) $settings['psp_bic'] ) . '</BIC>' . "\n";
		$xml .= '    <Period>' . self::esc( $period ) . '</Period>' . "\n";
		$xml .= '    <GeneratedAt>' . gmdate( 'Y-m-d\TH:i:s\Z' ) . '</GeneratedAt>' . "\n";
		$xml .= '  </Header>' . "\n";

		$xml .= '  <Totals txns="' . (int) $total_count . '" value="' . (float) $total_value . '" currency="' . self::esc( (string) $settings['currency'] ) . '">' . "\n";
		foreach ( $breakdown as $key => $stats ) {
			$xml .= '    <Bucket exemption="' . self::esc( $key ) . '" count="' . (int) $stats['count'] . '" value="' . (float) $stats['value'] . '"/>' . "\n";
		}
		$xml .= '    <ChallengeFailureRate>' . (float) $challenge_failure . '</ChallengeFailureRate>' . "\n";
		$xml .= '  </Totals>' . "\n";

		$xml .= '  <Fraud count="' . (int) $fraud_count . '" value="' . (float) $fraud_value . '" rate="' . (float) $fraud_rate . '">' . "\n";
		foreach ( $frauds as $f ) {
			$xml .= '    <Event category="' . self::esc( (string) $f['category'] ) . '" channel="' . self::esc( (string) $f['channel'] ) . '" amount="' . (float) $f['amount'] . '" currency="' . self::esc( (string) $f['currency'] ) . '" reimbursed="' . (int) $f['reimbursed'] . '" onTimeRefund="' . (int) $f['refunded_within_window'] . '"/>' . "\n";
		}
		$xml .= '  </Fraud>' . "\n";
		$xml .= '  <RefundCompliance art73="' . (float) $refund_compliance . '"/>' . "\n";
		$xml .= '</FraudReport>' . "\n";

		$payload = wp_json_encode(
			array(
				'schema'            => 'eurocomply-psd2-1',
				'psp'               => $settings['psp_name'],
				'country'           => $settings['psp_country'],
				'bic'               => $settings['psp_bic'],
				'period'            => $period,
				'totals' => array(
					'count'              => $total_count,
					'value'              => $total_value,
					'breakdown'          => $breakdown,
					'challenge_failure'  => $challenge_failure,
				),
				'fraud' => array(
					'count'  => $fraud_count,
					'value'  => $fraud_value,
					'rate'   => $fraud_rate,
					'events' => $frauds,
				),
				'refund_compliance' => $refund_compliance,
			),
			JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);

		return array(
			'period'      => $period,
			'xml'         => $xml,
			'payload'     => (string) $payload,
			'fraud_count' => $fraud_count,
			'fraud_value' => $fraud_value,
			'fraud_rate'  => $fraud_rate,
		);
	}

	private static function esc( string $s ) : string {
		return htmlspecialchars( $s, ENT_QUOTES | ENT_XML1, 'UTF-8' );
	}
}
