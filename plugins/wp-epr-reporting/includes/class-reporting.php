<?php
/**
 * EPR reporting engine: scans products per enabled country and classifies them
 * as compliant / warning / missing-required for the dashboard + CSV exports.
 *
 * @package EuroComply\Epr
 */

declare( strict_types = 1 );

namespace EuroComply\Epr;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Reporting {

	public const STATUS_OK      = 'ok';
	public const STATUS_WARNING = 'warning';
	public const STATUS_ERROR   = 'error';

	/**
	 * Scan all published products and return per-product, per-country status.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function scan() : array {
		$settings  = Settings::get();
		$enabled   = (array) $settings['enabled_countries'];
		if ( empty( $enabled ) ) {
			return array();
		}

		$query = new \WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		$rows = array();
		foreach ( (array) $query->posts as $pid ) {
			$pid     = (int) $pid;
			$post    = get_post( $pid );
			$title   = $post ? $post->post_title : ( '#' . $pid );
			$weights = ProductFields::weight_breakdown( $pid );
			$total   = ProductFields::total_weight( $pid );

			$countries = array();
			foreach ( $enabled as $code ) {
				$countries[ $code ] = self::classify_country( $pid, $code, $total );
			}

			$rows[] = array(
				'id'              => $pid,
				'title'           => (string) $title,
				'total_weight_g'  => $total,
				'materials'       => $weights,
				'countries'       => $countries,
			);
		}
		return $rows;
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function classify_country( int $product_id, string $country_code, float $total_weight ) : array {
		$registration = ProductFields::registration_for( $product_id, $country_code );
		$missing      = array();

		if ( '' === $registration ) {
			$missing[] = 'registration_number';
		}
		if ( $total_weight <= 0 ) {
			$missing[] = 'packaging_weight';
		}

		$country = Countries::get( $country_code );
		if ( $country && '' !== $registration && ! preg_match( $country['reg_regex'], $registration ) ) {
			$status = self::STATUS_WARNING;
			$note   = sprintf(
				/* translators: 1: registration label, 2: expected pattern */
				__( '%1$s "%2$s" does not match expected format.', 'eurocomply-epr' ),
				$country['reg_label'],
				$country['reg_example']
			);
			return array(
				'status'       => $status,
				'registration' => $registration,
				'missing'      => $missing,
				'note'         => $note,
			);
		}

		if ( ! empty( $missing ) ) {
			return array(
				'status'       => self::STATUS_ERROR,
				'registration' => $registration,
				'missing'      => $missing,
				'note'         => __( 'Required EPR fields missing for this country.', 'eurocomply-epr' ),
			);
		}

		return array(
			'status'       => self::STATUS_OK,
			'registration' => $registration,
			'missing'      => array(),
			'note'         => '',
		);
	}

	/**
	 * @param array<int,array<string,mixed>> $rows
	 * @return array<string,int>
	 */
	public static function summary( array $rows ) : array {
		$out = array( 'compliant' => 0, 'warning' => 0, 'error' => 0 );
		foreach ( $rows as $row ) {
			$worst = self::STATUS_OK;
			foreach ( (array) $row['countries'] as $c ) {
				if ( self::STATUS_ERROR === ( $c['status'] ?? '' ) ) {
					$worst = self::STATUS_ERROR;
					break;
				}
				if ( self::STATUS_WARNING === ( $c['status'] ?? '' ) ) {
					$worst = self::STATUS_WARNING;
				}
			}
			if ( self::STATUS_ERROR === $worst ) {
				$out['error']++;
			} elseif ( self::STATUS_WARNING === $worst ) {
				$out['warning']++;
			} else {
				$out['compliant']++;
			}
		}
		return $out;
	}
}
