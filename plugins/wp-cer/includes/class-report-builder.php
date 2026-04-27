<?php
/**
 * Per-incident report builder (Art. 15) — early warning / follow-up.
 *
 * @package EuroComply\CER
 */

declare( strict_types = 1 );

namespace EuroComply\CER;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ReportBuilder {

	/**
	 * @return array{incident_id:int,stage:string,xml:string,payload:string}|null
	 */
	public static function build( int $incident_id, string $stage ) : ?array {
		if ( ! in_array( $stage, array( 'early_warning', 'followup' ), true ) ) {
			return null;
		}
		$inc = IncidentStore::get( $incident_id );
		if ( null === $inc ) {
			return null;
		}
		$s        = Settings::get();
		$svc      = $inc['service_id'] ? ServiceStore::get( (int) $inc['service_id'] ) : null;

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<CerIncidentReport xmlns="urn:cer:eurocomply:0.1" stage="' . self::esc( $stage ) . '" framework="EU-2022-2557">' . "\n";
		$xml .= '  <Entity>' . "\n";
		$xml .= '    <Name>' . self::esc( (string) $s['entity_name'] ) . '</Name>' . "\n";
		$xml .= '    <Identifier>' . self::esc( (string) $s['entity_id'] ) . '</Identifier>' . "\n";
		$xml .= '    <Country>' . self::esc( (string) $s['entity_country'] ) . '</Country>' . "\n";
		$xml .= '    <Sector>' . self::esc( (string) $s['sector'] ) . '</Sector>' . "\n";
		$xml .= '    <SubSector>' . self::esc( (string) $s['sub_sector'] ) . '</SubSector>' . "\n";
		$xml .= '    <CrossBorder>' . ( $s['cross_border'] ? 'true' : 'false' ) . '</CrossBorder>' . "\n";
		$xml .= '    <Authority>' . self::esc( (string) $s['competent_authority'] ) . '</Authority>' . "\n";
		$xml .= '  </Entity>' . "\n";

		if ( $svc ) {
			$xml .= '  <Service id="' . (int) $svc['id'] . '">' . "\n";
			$xml .= '    <Name>' . self::esc( (string) $svc['name'] ) . '</Name>' . "\n";
			$xml .= '    <Sector>' . self::esc( (string) $svc['sector'] ) . '</Sector>' . "\n";
			$xml .= '    <SubSector>' . self::esc( (string) $svc['sub_sector'] ) . '</SubSector>' . "\n";
			$xml .= '    <PopulationServed>' . (int) $svc['population_served'] . '</PopulationServed>' . "\n";
			$xml .= '    <CrossBorder>' . ( $svc['cross_border'] ? 'true' : 'false' ) . '</CrossBorder>' . "\n";
			$xml .= '  </Service>' . "\n";
		}

		$xml .= '  <Incident id="' . (int) $inc['id'] . '" significant="' . ( $inc['significant'] ? 'true' : 'false' ) . '">' . "\n";
		$xml .= '    <OccurredAt>' . self::esc( (string) ( $inc['occurred_at'] ?? '' ) ) . '</OccurredAt>' . "\n";
		$xml .= '    <DetectedAt>' . self::esc( (string) ( $inc['detected_at'] ?? '' ) ) . '</DetectedAt>' . "\n";
		$xml .= '    <Category>' . self::esc( (string) $inc['category'] ) . '</Category>' . "\n";
		$xml .= '    <UsersAffected>' . (int) $inc['users_affected'] . '</UsersAffected>' . "\n";
		$xml .= '    <DurationMinutes>' . (int) $inc['duration_min'] . '</DurationMinutes>' . "\n";
		$xml .= '    <GeoSpread>' . (int) $inc['geo_spread'] . '</GeoSpread>' . "\n";
		$xml .= '    <CrossBorder>' . ( $inc['cross_border'] ? 'true' : 'false' ) . '</CrossBorder>' . "\n";
		if ( 'followup' === $stage ) {
			$xml .= '    <Summary>' . self::esc( (string) $inc['summary'] ) . '</Summary>' . "\n";
			$xml .= '    <RootCause>' . self::esc( (string) $inc['root_cause'] ) . '</RootCause>' . "\n";
			$xml .= '    <Mitigation>' . self::esc( (string) $inc['mitigation'] ) . '</Mitigation>' . "\n";
		}
		$xml .= '  </Incident>' . "\n";
		$xml .= '  <GeneratedAt>' . gmdate( 'Y-m-d\TH:i:s\Z' ) . '</GeneratedAt>' . "\n";
		$xml .= '</CerIncidentReport>' . "\n";

		$payload = wp_json_encode(
			array(
				'schema'   => 'eurocomply-cer-1',
				'stage'    => $stage,
				'entity'   => array(
					'name'        => $s['entity_name'],
					'identifier'  => $s['entity_id'],
					'country'     => $s['entity_country'],
					'sector'      => $s['sector'],
					'sub_sector'  => $s['sub_sector'],
					'cross_border'=> (bool) $s['cross_border'],
					'authority'   => $s['competent_authority'],
				),
				'service'  => $svc,
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
