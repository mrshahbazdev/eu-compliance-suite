<?php
/**
 * CSRD report builder — produces an XBRL-style XML envelope plus JSON payload.
 *
 * @package EuroComply\CSRD
 */

declare( strict_types = 1 );

namespace EuroComply\CSRD;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ReportBuilder {

	/**
	 * @return array{
	 *   year:int,
	 *   datapoints_count:int,
	 *   material_topics:int,
	 *   coverage_pct:float,
	 *   xbrl:string,
	 *   payload:string
	 * }
	 */
	public static function build( int $year ) : array {
		$settings = Settings::get();
		$dps      = DatapointStore::for_year( $year );
		$registry = EsrsRegistry::datapoints();
		$count    = count( $dps );
		$total    = max( 1, count( $registry ) );
		$coverage = round( ( $count / $total ) * 100, 2 );
		$material = MaterialityStore::count_material_for_year( $year );
		$assurance = AssuranceStore::latest_for_year( $year );

		// XBRL-style envelope (plugin-internal namespace; Pro replaces with EFRAG XBRL taxonomy).
		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<xbrl xmlns="urn:csrd:eurocomply:0.1" xmlns:esrs="urn:efrag:esrs:set1" schemaVersion="2024-set1">' . "\n";
		$xml .= '  <Header>' . "\n";
		$xml .= '    <Issuer>' . self::esc( (string) $settings['company_name'] ) . '</Issuer>' . "\n";
		$xml .= '    <LEI>'    . self::esc( (string) $settings['lei'] )          . '</LEI>' . "\n";
		$xml .= '    <Country>' . self::esc( (string) $settings['country'] )     . '</Country>' . "\n";
		$xml .= '    <ReportingYear>' . (int) $year . '</ReportingYear>' . "\n";
		$xml .= '    <GeneratedAt>' . gmdate( 'Y-m-d\TH:i:s\Z' ) . '</GeneratedAt>' . "\n";
		$xml .= '    <AssuranceLevel>' . self::esc( (string) ( $assurance['level'] ?? $settings['assurance_level'] ) ) . '</AssuranceLevel>' . "\n";
		$xml .= '    <AssuranceProvider>' . self::esc( (string) ( $assurance['provider'] ?? $settings['assurance_provider'] ) ) . '</AssuranceProvider>' . "\n";
		$xml .= '  </Header>' . "\n";

		$xml .= '  <Datapoints count="' . (int) $count . '" coveragePct="' . (float) $coverage . '">' . "\n";
		foreach ( $dps as $dp ) {
			$id  = (string) $dp['datapoint_id'];
			$def = $registry[ $id ] ?? null;
			if ( ! $def ) {
				continue;
			}
			$xml .= '    <DP id="' . self::esc( $id ) . '" standard="' . self::esc( (string) $def['standard'] ) . '" disclosure="' . self::esc( (string) $def['disclosure'] ) . '" unit="' . self::esc( (string) $def['unit'] ) . '">' . "\n";
			if ( null !== $dp['value_numeric'] ) {
				$xml .= '      <Numeric>' . (float) $dp['value_numeric'] . '</Numeric>' . "\n";
			}
			if ( null !== $dp['value_text'] && '' !== (string) $dp['value_text'] ) {
				$xml .= '      <Narrative>' . self::esc( (string) $dp['value_text'] ) . '</Narrative>' . "\n";
			}
			$xml .= '    </DP>' . "\n";
		}
		$xml .= '  </Datapoints>' . "\n";

		$xml .= '  <Materiality topics="' . (int) $material . '">' . "\n";
		foreach ( MaterialityStore::for_year( $year ) as $m ) {
			$xml .= '    <Topic standard="' . self::esc( (string) $m['topic'] ) . '" sub="' . self::esc( (string) $m['subtopic'] ) . '" impact="' . (int) $m['impact_score'] . '" financial="' . (int) $m['financial_score'] . '" horizon="' . self::esc( (string) $m['horizon'] ) . '" valueChain="' . self::esc( (string) $m['value_chain'] ) . '"/>' . "\n";
		}
		$xml .= '  </Materiality>' . "\n";
		$xml .= '</xbrl>' . "\n";

		$payload = wp_json_encode(
			array(
				'schema'  => 'eurocomply-csrd-1',
				'issuer'  => $settings['company_name'],
				'lei'     => $settings['lei'],
				'year'    => $year,
				'datapoints'  => array_map(
					static function ( array $r ) : array {
						return array(
							'id'      => (string) $r['datapoint_id'],
							'numeric' => null !== $r['value_numeric'] ? (float) $r['value_numeric'] : null,
							'text'    => (string) ( $r['value_text'] ?? '' ),
							'unit'    => (string) ( $r['unit'] ?? '' ),
							'source'  => (string) ( $r['source'] ?? '' ),
						);
					},
					$dps
				),
				'materiality_topics' => MaterialityStore::for_year( $year ),
				'assurance' => $assurance,
			),
			JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);

		return array(
			'year'             => $year,
			'datapoints_count' => $count,
			'material_topics'  => $material,
			'coverage_pct'     => $coverage,
			'xbrl'             => $xml,
			'payload'          => (string) $payload,
		);
	}

	private static function esc( string $s ) : string {
		return htmlspecialchars( $s, ENT_QUOTES | ENT_XML1, 'UTF-8' );
	}
}
