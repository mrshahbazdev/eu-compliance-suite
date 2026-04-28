<?php
/**
 * Sister-plugin bridge #5: CSRD/ESRS (#19) ↔ CBAM (#18) ↔ EUDR (#21).
 *
 * Aggregates upstream operational data from CBAM (imports of carbon-
 * intensive goods) and EUDR (deforestation-relevant commodity shipments)
 * into ESRS datapoints so the sustainability statement does not have to
 * be hand-keyed when the same data is already maintained for those two
 * regimes.
 *
 * - CBAM imports for the year (sum of direct + indirect emissions in
 *   tCO2e) → ESRS E1-6-S3 (Scope 3 GHG emissions). Treated as a Scope 3
 *   contribution because under the Greenhouse Gas Protocol upstream
 *   purchased goods sit in Scope 3 Category 1 ("Purchased goods and
 *   services") for the EU importer.
 *
 * - EUDR shipments for the year (sum of quantities normalised to tonnes)
 *   → ESRS E5-4-INFLOW (Material resource inflows). High-risk-country
 *   shipment count is captured as a narrative annotation on the same
 *   datapoint.
 *
 * Both directions degrade gracefully when the sister plugin is not
 * installed: actions are simply not fired, and the bridge skips its
 * own listeners after a class_exists check.
 *
 * @package EuroComply\CSRD
 */

declare( strict_types = 1 );

namespace EuroComply\CSRD;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SustainabilityBridge {

	public const SOURCE_CBAM = 'bridge:cbam';
	public const SOURCE_EUDR = 'bridge:eudr';

	public const ESRS_SCOPE3 = 'E1-6-S3';
	public const ESRS_INFLOW = 'E5-4-INFLOW';

	private const CBAM_IMPORT_STORE_FQN = '\\EuroComply\\CBAM\\ImportStore';
	private const EUDR_SHIPMENT_STORE_FQN = '\\EuroComply\\EUDR\\ShipmentStore';

	public static function register() : void {
		add_action( 'eurocomply_cbam_import_recorded',   array( __CLASS__, 'on_cbam_recorded' ),   10, 2 );
		add_action( 'eurocomply_eudr_shipment_recorded', array( __CLASS__, 'on_eudr_recorded' ), 10, 2 );
	}

	public static function cbam_active() : bool {
		return class_exists( self::CBAM_IMPORT_STORE_FQN );
	}

	public static function eudr_active() : bool {
		return class_exists( self::EUDR_SHIPMENT_STORE_FQN );
	}

	/**
	 * @param int                 $import_id
	 * @param array<string,mixed> $row
	 */
	public static function on_cbam_recorded( int $import_id, array $row ) : void {
		unset( $import_id );
		$period = isset( $row['period'] ) ? (string) $row['period'] : '';
		if ( ! preg_match( '/^(\d{4})-Q[1-4]$/', $period, $m ) ) {
			return;
		}
		self::refresh_cbam_for_year( (int) $m[1] );
	}

	/**
	 * @param int                 $shipment_id
	 * @param array<string,mixed> $row
	 */
	public static function on_eudr_recorded( int $shipment_id, array $row ) : void {
		unset( $shipment_id );
		$year = isset( $row['year'] ) ? (int) $row['year'] : (int) gmdate( 'Y' );
		if ( $year < 1900 ) {
			return;
		}
		self::refresh_eudr_for_year( $year );
	}

	/**
	 * Re-aggregate CBAM emissions for one calendar year and upsert the
	 * Scope 3 datapoint. Returns the new value in tCO2e.
	 */
	public static function refresh_cbam_for_year( int $year ) : ?float {
		if ( ! self::cbam_active() ) {
			return null;
		}
		$store    = self::CBAM_IMPORT_STORE_FQN;
		$total    = 0.0;
		$rows     = 0;
		$verified = 0;
		foreach ( array( 'Q1', 'Q2', 'Q3', 'Q4' ) as $q ) {
			$period = sprintf( '%04d-%s', $year, $q );
			/** @var array<int,array<string,mixed>> $period_rows */
			$period_rows = (array) call_user_func( array( $store, 'for_period' ), $period, 5000 );
			foreach ( $period_rows as $r ) {
				$direct   = isset( $r['direct_emissions'] )   ? (float) $r['direct_emissions']   : 0.0;
				$indirect = isset( $r['indirect_emissions'] ) ? (float) $r['indirect_emissions'] : 0.0;
				$total   += max( 0.0, $direct ) + max( 0.0, $indirect );
				$rows++;
				if ( ! empty( $r['emissions_verified'] ) ) {
					$verified++;
				}
			}
		}
		if ( 0 === $rows ) {
			return 0.0;
		}
		DatapointStore::upsert(
			array(
				'year'          => $year,
				'datapoint_id'  => self::ESRS_SCOPE3,
				'value_numeric' => $total,
				'value_text'    => self::cbam_narrative( $rows, $verified, $year ),
				'source'        => self::SOURCE_CBAM,
				'notes'         => sprintf(
					/* translators: 1: rows aggregated, 2: year */
					__( 'Auto-aggregated from %1$d CBAM import row(s) for FY %2$d.', 'eurocomply-csrd-esrs' ),
					$rows,
					$year
				),
			)
		);
		return $total;
	}

	/**
	 * Re-aggregate EUDR shipments for one calendar year and upsert the
	 * material-inflow datapoint. Returns the new value in tonnes.
	 */
	public static function refresh_eudr_for_year( int $year ) : ?float {
		if ( ! self::eudr_active() ) {
			return null;
		}
		$store    = self::EUDR_SHIPMENT_STORE_FQN;
		/** @var array<int,array<string,mixed>> $shipments */
		$shipments = (array) call_user_func( array( $store, 'for_year' ), $year );
		$tonnes    = 0.0;
		$rows      = 0;
		$high_risk = 0;
		foreach ( $shipments as $s ) {
			$qty  = isset( $s['quantity'] ) ? (float) $s['quantity'] : 0.0;
			$unit = isset( $s['unit'] ) ? (string) $s['unit'] : 'kg';
			$tonnes += self::to_tonnes( $qty, $unit );
			$rows++;
			if ( 'high' === ( $s['risk_level'] ?? '' ) ) {
				$high_risk++;
			}
		}
		if ( 0 === $rows ) {
			return 0.0;
		}
		DatapointStore::upsert(
			array(
				'year'          => $year,
				'datapoint_id'  => self::ESRS_INFLOW,
				'value_numeric' => $tonnes,
				'value_text'    => self::eudr_narrative( $rows, $high_risk, $year ),
				'source'        => self::SOURCE_EUDR,
				'notes'         => sprintf(
					/* translators: 1: rows aggregated, 2: year */
					__( 'Auto-aggregated from %1$d EUDR shipment row(s) for FY %2$d.', 'eurocomply-csrd-esrs' ),
					$rows,
					$year
				),
			)
		);
		return $tonnes;
	}

	private static function to_tonnes( float $qty, string $unit ) : float {
		$unit = strtolower( $unit );
		switch ( $unit ) {
			case 't':
			case 'tonne':
			case 'tonnes':
			case 'mt':
				return max( 0.0, $qty );
			case 'kg':
				return max( 0.0, $qty / 1000.0 );
			case 'g':
				return max( 0.0, $qty / 1000000.0 );
			case 'lb':
			case 'lbs':
				return max( 0.0, $qty * 0.000453592 );
			default:
				return max( 0.0, $qty / 1000.0 );
		}
	}

	private static function cbam_narrative( int $rows, int $verified, int $year ) : string {
		return wp_kses_post(
			sprintf(
				/* translators: 1: rows, 2: verified rows, 3: year */
				__( '<p>Sourced via the EuroComply CBAM bridge. %1$d import row(s) aggregated for FY %3$d, of which %2$d carry verified emissions data; the remainder use Reg. (EU) 2023/1773 default values.</p>', 'eurocomply-csrd-esrs' ),
				$rows,
				$verified,
				$year
			)
		);
	}

	private static function eudr_narrative( int $rows, int $high_risk, int $year ) : string {
		return wp_kses_post(
			sprintf(
				/* translators: 1: rows, 2: high-risk count, 3: year */
				__( '<p>Sourced via the EuroComply EUDR bridge. %1$d shipment row(s) aggregated for FY %3$d, of which %2$d originate from countries currently benchmarked as high-risk under Reg. (EU) 2023/1115 Art. 29.</p>', 'eurocomply-csrd-esrs' ),
				$rows,
				$high_risk,
				$year
			)
		);
	}
}
