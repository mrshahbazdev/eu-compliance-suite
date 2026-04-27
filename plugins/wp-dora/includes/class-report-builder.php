<?php
/**
 * Per-incident report builder — initial / intermediate / final.
 *
 * @package EuroComply\DORA
 */

declare( strict_types = 1 );

namespace EuroComply\DORA;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ReportBuilder {

	/**
	 * @return array{incident_id:int,stage:string,xml:string,payload:string}|null
	 */
	public static function build( int $incident_id, string $stage ) : ?array {
		if ( ! in_array( $stage, array( 'initial', 'intermediate', 'final' ), true ) ) {
			return null;
		}
		$inc = IncidentStore::get( $incident_id );
		if ( null === $inc ) {
			return null;
		}
		$s = Settings::get();

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<DoraIncidentReport xmlns="urn:dora:eurocomply:0.1" stage="' . self::esc( $stage ) . '" framework="EU-2022-2554">' . "\n";
		$xml .= '  <Entity>' . "\n";
		$xml .= '    <Name>' . self::esc( (string) $s['entity_name'] ) . '</Name>' . "\n";
		$xml .= '    <LEI>' . self::esc( (string) $s['entity_lei'] ) . '</LEI>' . "\n";
		$xml .= '    <Country>' . self::esc( (string) $s['entity_country'] ) . '</Country>' . "\n";
		$xml .= '    <Type>' . self::esc( (string) $s['entity_type'] ) . '</Type>' . "\n";
		$xml .= '    <Size>' . self::esc( (string) $s['entity_size'] ) . '</Size>' . "\n";
		$xml .= '    <Authority>' . self::esc( (string) $s['competent_authority'] ) . '</Authority>' . "\n";
		$xml .= '  </Entity>' . "\n";

		$xml .= '  <Incident id="' . (int) $inc['id'] . '" classification="' . self::esc( (string) $inc['classification'] ) . '">' . "\n";
		$xml .= '    <OccurredAt>' . self::esc( (string) $inc['occurred_at'] ) . '</OccurredAt>' . "\n";
		$xml .= '    <DetectedAt>' . self::esc( (string) $inc['detected_at'] ) . '</DetectedAt>' . "\n";
		$xml .= '    <ClassifiedAt>' . self::esc( (string) $inc['classified_at'] ) . '</ClassifiedAt>' . "\n";
		$xml .= '    <Category>' . self::esc( (string) $inc['category'] ) . '</Category>' . "\n";
		$xml .= '    <Severity>' . self::esc( (string) $inc['severity'] ) . '</Severity>' . "\n";
		$xml .= '    <ClientsAffected>' . (int) $inc['clients_affected'] . '</ClientsAffected>' . "\n";
		$xml .= '    <DataLoss>' . ( $inc['data_loss'] ? 'true' : 'false' ) . '</DataLoss>' . "\n";
		$xml .= '    <DurationMinutes>' . (int) $inc['duration_min'] . '</DurationMinutes>' . "\n";
		$xml .= '    <GeoSpread>' . (int) $inc['geo_spread'] . '</GeoSpread>' . "\n";
		$xml .= '    <FinancialImpact currency="' . self::esc( (string) $s['currency'] ) . '">' . (float) $inc['financial_impact'] . '</FinancialImpact>' . "\n";
		$xml .= '    <Reputational>' . ( $inc['reputational'] ? 'true' : 'false' ) . '</Reputational>' . "\n";
		$xml .= '    <CriticalService>' . ( $inc['critical_service'] ? 'true' : 'false' ) . '</CriticalService>' . "\n";
		if ( 'initial' !== $stage ) {
			$xml .= '    <Summary>' . self::esc( (string) $inc['summary'] ) . '</Summary>' . "\n";
			$xml .= '    <RootCause>' . self::esc( (string) $inc['root_cause'] ) . '</RootCause>' . "\n";
			$xml .= '    <Mitigation>' . self::esc( (string) $inc['mitigation'] ) . '</Mitigation>' . "\n";
		}
		$xml .= '  </Incident>' . "\n";
		$xml .= '  <GeneratedAt>' . gmdate( 'Y-m-d\TH:i:s\Z' ) . '</GeneratedAt>' . "\n";
		$xml .= '</DoraIncidentReport>' . "\n";

		$payload = wp_json_encode(
			array(
				'schema'   => 'eurocomply-dora-1',
				'stage'    => $stage,
				'entity'   => array(
					'name'      => $s['entity_name'],
					'lei'       => $s['entity_lei'],
					'country'   => $s['entity_country'],
					'type'      => $s['entity_type'],
					'size'      => $s['entity_size'],
					'authority' => $s['competent_authority'],
				),
				'incident' => $inc,
			),
			JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);

		return array(
			'incident_id' => $incident_id,
			'stage'       => $stage,
			'xml'         => $xml,
			'payload'     => (string) $payload,
		);
	}

	private static function esc( string $s ) : string {
		return htmlspecialchars( $s, ENT_QUOTES | ENT_XML1, 'UTF-8' );
	}
}
