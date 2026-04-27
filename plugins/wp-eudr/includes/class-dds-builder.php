<?php
/**
 * DDS builder — XML envelope + JSON payload for TRACES NT pre-filing.
 *
 * @package EuroComply\EUDR
 */

declare( strict_types = 1 );

namespace EuroComply\EUDR;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DdsBuilder {

	/**
	 * @return array{shipment_id:int,xml:string,payload:string}|null
	 */
	public static function build( int $shipment_id ) : ?array {
		$shp = ShipmentStore::get( $shipment_id );
		if ( null === $shp ) {
			return null;
		}
		$s         = Settings::get();
		$supplier  = SupplierStore::get( (int) $shp['supplier_id'] );
		$plot_ids  = array_filter( array_map( 'absint', explode( ',', (string) $shp['plot_ids'] ) ) );
		$plots     = array();
		foreach ( $plot_ids as $pid ) {
			$row = PlotStore::all();
			foreach ( $row as $r ) {
				if ( (int) $r['id'] === $pid ) {
					$plots[] = $r;
					break;
				}
			}
		}
		$risks = RiskStore::for_shipment( $shipment_id );
		$comm  = CommodityRegistry::get( (string) $shp['commodity'] );

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<DueDiligenceStatement xmlns="urn:eudr:eurocomply:0.1" framework="EU-2023-1115">' . "\n";
		$xml .= '  <Operator>' . "\n";
		$xml .= '    <Name>' . self::esc( (string) $s['operator_name'] ) . '</Name>' . "\n";
		$xml .= '    <EORI>' . self::esc( (string) $s['operator_eori'] ) . '</EORI>' . "\n";
		$xml .= '    <Country>' . self::esc( (string) $s['operator_country'] ) . '</Country>' . "\n";
		$xml .= '    <Address>' . self::esc( (string) $s['operator_address'] ) . '</Address>' . "\n";
		$xml .= '    <Role>' . self::esc( (string) $s['operator_role'] ) . '</Role>' . "\n";
		$xml .= '    <CutoffDate>' . self::esc( (string) $s['cutoff_date'] ) . '</CutoffDate>' . "\n";
		$xml .= '  </Operator>' . "\n";

		$xml .= '  <Goods>' . "\n";
		$xml .= '    <Commodity>' . self::esc( (string) $shp['commodity'] ) . '</Commodity>' . "\n";
		if ( $comm ) {
			$xml .= '    <CommodityName>' . self::esc( (string) $comm['name'] ) . '</CommodityName>' . "\n";
		}
		$xml .= '    <HSCode>' . self::esc( (string) $shp['hs_code'] ) . '</HSCode>' . "\n";
		$xml .= '    <Description>' . self::esc( (string) $shp['description'] ) . '</Description>' . "\n";
		$xml .= '    <Quantity unit="' . self::esc( (string) $shp['unit'] ) . '">' . (float) $shp['quantity'] . '</Quantity>' . "\n";
		$xml .= '    <CountryOfProduction>' . self::esc( (string) $shp['country_origin'] ) . '</CountryOfProduction>' . "\n";
		$xml .= '    <CountryRiskLevel>' . self::esc( (string) ( $shp['risk_level'] ?: CountryRisk::level( (string) $shp['country_origin'], (string) $shp['commodity'] ) ) ) . '</CountryRiskLevel>' . "\n";
		$xml .= '  </Goods>' . "\n";

		if ( $supplier ) {
			$xml .= '  <Supplier>' . "\n";
			$xml .= '    <Name>' . self::esc( (string) $supplier['name'] ) . '</Name>' . "\n";
			$xml .= '    <Country>' . self::esc( (string) $supplier['country'] ) . '</Country>' . "\n";
			$xml .= '    <Address>' . self::esc( (string) $supplier['address'] ) . '</Address>' . "\n";
			$xml .= '    <TaxId>' . self::esc( (string) $supplier['tax_id'] ) . '</TaxId>' . "\n";
			$xml .= '    <Role>' . self::esc( (string) $supplier['role'] ) . '</Role>' . "\n";
			$xml .= '  </Supplier>' . "\n";
		}

		$xml .= '  <Geolocation count="' . count( $plots ) . '">' . "\n";
		foreach ( $plots as $p ) {
			$xml .= '    <Plot id="' . (int) $p['id'] . '" type="' . self::esc( (string) $p['geom_type'] ) . '" areaHa="' . (float) $p['area_ha'] . '" check="' . self::esc( (string) $p['deforestation_check'] ) . '">' . "\n";
			$xml .= '      <Country>' . self::esc( (string) $p['country'] ) . '</Country>' . "\n";
			$xml .= '      <Label>' . self::esc( (string) $p['label'] ) . '</Label>' . "\n";
			if ( 'polygon' === (string) $p['geom_type'] && '' !== (string) $p['polygon'] ) {
				$xml .= '      <Polygon><![CDATA[' . (string) $p['polygon'] . ']]></Polygon>' . "\n";
			} else {
				$xml .= '      <Point lat="' . (float) $p['lat'] . '" lng="' . (float) $p['lng'] . '"/>' . "\n";
			}
			$xml .= '      <Production from="' . self::esc( (string) ( $p['production_from'] ?? '' ) ) . '" to="' . self::esc( (string) ( $p['production_to'] ?? '' ) ) . '"/>' . "\n";
			$xml .= '    </Plot>' . "\n";
		}
		$xml .= '  </Geolocation>' . "\n";

		$xml .= '  <RiskAssessment count="' . count( $risks ) . '">' . "\n";
		foreach ( $risks as $r ) {
			$xml .= '    <Item factor="' . self::esc( (string) $r['factor'] ) . '" level="' . self::esc( (string) $r['level'] ) . '" conclusion="' . self::esc( (string) $r['conclusion'] ) . '">' . "\n";
			$xml .= '      <Finding>' . self::esc( (string) $r['finding'] ) . '</Finding>' . "\n";
			$xml .= '      <Mitigation>' . self::esc( (string) $r['mitigation'] ) . '</Mitigation>' . "\n";
			$xml .= '    </Item>' . "\n";
		}
		$xml .= '  </RiskAssessment>' . "\n";

		if ( '' !== (string) $shp['upstream_dds'] ) {
			$xml .= '  <UpstreamDDS>' . self::esc( (string) $shp['upstream_dds'] ) . '</UpstreamDDS>' . "\n";
		}
		$xml .= '  <Reference>' . self::esc( (string) $shp['dds_reference'] ) . '</Reference>' . "\n";
		$xml .= '  <Status>' . self::esc( (string) $shp['dds_status'] ) . '</Status>' . "\n";
		$xml .= '  <GeneratedAt>' . gmdate( 'Y-m-d\TH:i:s\Z' ) . '</GeneratedAt>' . "\n";
		$xml .= '</DueDiligenceStatement>' . "\n";

		$payload = wp_json_encode(
			array(
				'schema'   => 'eurocomply-eudr-1',
				'operator' => array(
					'name'    => $s['operator_name'],
					'eori'    => $s['operator_eori'],
					'country' => $s['operator_country'],
					'role'    => $s['operator_role'],
				),
				'cutoff'   => $s['cutoff_date'],
				'shipment' => $shp,
				'supplier' => $supplier,
				'plots'    => $plots,
				'risks'    => $risks,
			),
			JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);

		return array(
			'shipment_id' => $shipment_id,
			'xml'         => $xml,
			'payload'     => (string) $payload,
		);
	}

	private static function esc( string $s ) : string {
		return htmlspecialchars( $s, ENT_QUOTES | ENT_XML1, 'UTF-8' );
	}
}
