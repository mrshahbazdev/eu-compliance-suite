<?php
/**
 * Gender pay gap calculator (Art. 9).
 *
 * Computes mean and median gaps overall and per category. Returns a structured
 * payload suitable for storing as a snapshot in ReportStore and exporting.
 *
 * @package EuroComply\PayTransparency
 */

declare( strict_types = 1 );

namespace EuroComply\PayTransparency;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class GapCalculator {

	/**
	 * Hourly equivalent: total_comp / (hours_per_week * 52). Annualised
	 * comparison removes part-time bias per Art. 9 guidance.
	 */
	private static function hourly( array $row ) : float {
		$hours = max( 1.0, (float) $row['hours_per_week'] );
		return ( (float) $row['total_comp'] ) / ( $hours * 52.0 );
	}

	private static function median( array $values ) : float {
		if ( ! $values ) {
			return 0.0;
		}
		sort( $values );
		$n = count( $values );
		if ( $n % 2 ) {
			return (float) $values[ (int) ( $n / 2 ) ];
		}
		return ( (float) $values[ $n / 2 - 1 ] + (float) $values[ $n / 2 ] ) / 2.0;
	}

	private static function mean( array $values ) : float {
		if ( ! $values ) {
			return 0.0;
		}
		return array_sum( $values ) / count( $values );
	}

	/**
	 * Pay gap formula: ((mean_men - mean_women) / mean_men) * 100.
	 * Returns 0 when men's mean is 0 to avoid division by zero.
	 */
	private static function gap_pct( float $men, float $women ) : float {
		if ( $men <= 0 ) {
			return 0.0;
		}
		return ( ( $men - $women ) / $men ) * 100.0;
	}

	/**
	 * @return array{
	 *   year:int,
	 *   employees_count:int,
	 *   gap_overall_pct:float,
	 *   gap_overall_median_pct:float,
	 *   joint_assessment_required:bool,
	 *   payload:array<string,mixed>
	 * }
	 */
	public static function run( int $year ) : array {
		$rows  = EmployeeStore::for_year( $year, 50000 );
		$count = count( $rows );

		$by_cat   = array();
		$men_all  = array();
		$women_all = array();

		foreach ( $rows as $row ) {
			$h    = self::hourly( $row );
			$g    = (string) $row['gender'];
			$slug = (string) $row['category_slug'];
			if ( ! isset( $by_cat[ $slug ] ) ) {
				$by_cat[ $slug ] = array(
					'men'   => array(),
					'women' => array(),
					'other' => array(),
					'count' => 0,
				);
			}
			$by_cat[ $slug ]['count']++;
			if ( 'm' === $g ) {
				$by_cat[ $slug ]['men'][] = $h;
				$men_all[]                = $h;
			} elseif ( 'w' === $g ) {
				$by_cat[ $slug ]['women'][] = $h;
				$women_all[]                = $h;
			} else {
				$by_cat[ $slug ]['other'][] = $h;
			}
		}

		$overall_mean   = self::gap_pct( self::mean( $men_all ), self::mean( $women_all ) );
		$overall_median = self::gap_pct( self::median( $men_all ), self::median( $women_all ) );
		$threshold      = (float) Settings::get()['joint_assessment_threshold'];
		$joint_needed   = false;

		$cat_payload = array();
		foreach ( $by_cat as $slug => $g ) {
			$mean_men     = self::mean( $g['men'] );
			$mean_women   = self::mean( $g['women'] );
			$median_men   = self::median( $g['men'] );
			$median_women = self::median( $g['women'] );
			$mean_gap     = self::gap_pct( $mean_men, $mean_women );
			$median_gap   = self::gap_pct( $median_men, $median_women );
			if ( abs( $mean_gap ) > $threshold ) {
				$joint_needed = true;
			}
			$category = CategoryStore::get_by_slug( $slug );
			$cat_payload[] = array(
				'slug'               => $slug,
				'name'               => $category ? (string) $category['name'] : $slug,
				'count'              => (int) $g['count'],
				'men_count'          => count( $g['men'] ),
				'women_count'        => count( $g['women'] ),
				'other_count'        => count( $g['other'] ),
				'mean_pay_men'       => round( $mean_men, 2 ),
				'mean_pay_women'     => round( $mean_women, 2 ),
				'median_pay_men'     => round( $median_men, 2 ),
				'median_pay_women'   => round( $median_women, 2 ),
				'gap_mean_pct'       => round( $mean_gap, 2 ),
				'gap_median_pct'     => round( $median_gap, 2 ),
			);
		}

		return array(
			'year'                       => $year,
			'employees_count'            => $count,
			'gap_overall_pct'            => round( $overall_mean, 2 ),
			'gap_overall_median_pct'     => round( $overall_median, 2 ),
			'joint_assessment_required'  => $joint_needed,
			'payload'                    => array(
				'organisation_name'    => (string) Settings::get()['organisation_name'],
				'organisation_country' => (string) Settings::get()['organisation_country'],
				'currency'             => (string) Settings::get()['currency'],
				'threshold_pct'        => $threshold,
				'categories'           => $cat_payload,
				'overall' => array(
					'mean_pay_men'      => round( self::mean( $men_all ), 2 ),
					'mean_pay_women'    => round( self::mean( $women_all ), 2 ),
					'median_pay_men'    => round( self::median( $men_all ), 2 ),
					'median_pay_women'  => round( self::median( $women_all ), 2 ),
					'men_count'         => count( $men_all ),
					'women_count'       => count( $women_all ),
				),
			),
		);
	}
}
