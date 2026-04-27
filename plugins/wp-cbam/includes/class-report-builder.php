<?php
/**
 * CBAM Q-report builder (transitional period XML envelope).
 *
 * @package EuroComply\CBAM
 */

declare( strict_types = 1 );

namespace EuroComply\CBAM;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ReportBuilder {

	/**
	 * @return array{
	 *   period:string,
	 *   imports_count:int,
	 *   total_quantity:float,
	 *   total_direct:float,
	 *   total_indirect:float,
	 *   xml:string
	 * }
	 */
	public static function build( string $period ) : array {
		$rows     = ImportStore::for_period( $period, 50000 );
		$settings = Settings::get();

		$total_q = 0.0;
		$total_d = 0.0;
		$total_i = 0.0;
		foreach ( $rows as $r ) {
			$total_q += (float) $r['quantity'];
			$total_d += (float) $r['direct_emissions']   * (float) $r['quantity'];
			$total_i += (float) $r['indirect_emissions'] * (float) $r['quantity'];
		}

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<CBAMReport xmlns="urn:cbam:eurocomply:0.1" schemaVersion="2023-1773">' . "\n";
		$xml .= '  <Header>' . "\n";
		$xml .= '    <Declarant>' . "\n";
		$xml .= '      <Name>'      . self::esc( (string) $settings['declarant_name'] ) . '</Name>' . "\n";
		$xml .= '      <EORI>'      . self::esc( (string) $settings['declarant_eori'] ) . '</EORI>' . "\n";
		$xml .= '      <Country>'   . self::esc( (string) $settings['declarant_country'] ) . '</Country>' . "\n";
		$xml .= '      <AuthorisedDeclarantId>' . self::esc( (string) $settings['authorised_declarant_id'] ) . '</AuthorisedDeclarantId>' . "\n";
		$xml .= '    </Declarant>' . "\n";
		$xml .= '    <ReportingPeriod>' . self::esc( $period ) . '</ReportingPeriod>' . "\n";
		$xml .= '    <GeneratedAt>'     . gmdate( 'Y-m-d\TH:i:s\Z' ) . '</GeneratedAt>' . "\n";
		$xml .= '  </Header>' . "\n";
		$xml .= '  <Imports count="' . (int) count( $rows ) . '">' . "\n";

		foreach ( $rows as $r ) {
			$xml .= '    <Import id="' . (int) $r['id'] . '">' . "\n";
			$xml .= '      <CN8>'              . self::esc( (string) $r['cn8'] )                . '</CN8>' . "\n";
			$xml .= '      <Category>'         . self::esc( (string) $r['category'] )           . '</Category>' . "\n";
			$xml .= '      <CountryOfOrigin>'  . self::esc( (string) $r['origin_country'] )     . '</CountryOfOrigin>' . "\n";
			$xml .= '      <Supplier>'         . self::esc( (string) $r['supplier'] )           . '</Supplier>' . "\n";
			$xml .= '      <ProductionRoute>'  . self::esc( (string) $r['production_route'] )   . '</ProductionRoute>' . "\n";
			$xml .= '      <Quantity unit="'   . self::esc( (string) $r['unit'] ) . '">'        . (float) $r['quantity']           . '</Quantity>' . "\n";
			$xml .= '      <DirectEmissions tCO2ePerUnit="'   . (float) $r['direct_emissions']   . '"/>' . "\n";
			$xml .= '      <IndirectEmissions tCO2ePerUnit="' . (float) $r['indirect_emissions'] . '"/>' . "\n";
			$xml .= '      <DataSource verified="' . ( ! empty( $r['emissions_verified'] ) ? 'true' : 'false' ) . '">' . self::esc( (string) $r['data_source'] ) . '</DataSource>' . "\n";
			$xml .= '    </Import>' . "\n";
		}

		$xml .= '  </Imports>' . "\n";
		$xml .= '  <Totals>' . "\n";
		$xml .= '    <Quantity>'        . $total_q . '</Quantity>' . "\n";
		$xml .= '    <DirectTCO2e>'     . $total_d . '</DirectTCO2e>' . "\n";
		$xml .= '    <IndirectTCO2e>'   . $total_i . '</IndirectTCO2e>' . "\n";
		$xml .= '    <TotalTCO2e>'      . ( $total_d + $total_i ) . '</TotalTCO2e>' . "\n";
		$xml .= '  </Totals>' . "\n";
		$xml .= '</CBAMReport>' . "\n";

		return array(
			'period'         => $period,
			'imports_count'  => count( $rows ),
			'total_quantity' => $total_q,
			'total_direct'   => $total_d,
			'total_indirect' => $total_i,
			'xml'            => $xml,
		);
	}

	private static function esc( string $s ) : string {
		return htmlspecialchars( $s, ENT_QUOTES | ENT_XML1, 'UTF-8' );
	}
}
