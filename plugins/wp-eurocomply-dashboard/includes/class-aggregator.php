<?php
/**
 * Aggregator — runs all connectors, computes overall compliance score.
 *
 * @package EuroComply\Dashboard
 */

declare( strict_types = 1 );

namespace EuroComply\Dashboard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Aggregator {

	/**
	 * @return array{
	 *   overall:int,
	 *   active_count:int,
	 *   total_count:int,
	 *   alert_count:int,
	 *   plugins: array<int, array<string,mixed>>,
	 *   alerts: array<int, array<string,mixed>>,
	 * }
	 */
	public static function snapshot_payload() : array {
		$plugins = Connectors::all();
		$alerts  = array();
		$active  = array();

		foreach ( $plugins as $p ) {
			if ( ! empty( $p['active'] ) ) {
				$active[] = $p;
			}
			if ( ! empty( $p['alerts'] ) ) {
				foreach ( (array) $p['alerts'] as $a ) {
					$alerts[] = array_merge( array( 'plugin' => $p['name'] ), $a );
				}
			}
		}

		$overall = 0;
		if ( ! empty( $active ) ) {
			$sum = 0;
			foreach ( $active as $p ) {
				$sum += (int) $p['score'];
			}
			$overall = (int) round( $sum / max( 1, count( $active ) ) );
		}

		return array(
			'overall'      => $overall,
			'active_count' => count( $active ),
			'total_count'  => count( $plugins ),
			'alert_count'  => count( $alerts ),
			'plugins'      => $plugins,
			'alerts'       => $alerts,
		);
	}

	public static function snapshot() : void {
		$payload = self::snapshot_payload();
		SnapshotStore::record(
			(int) $payload['overall'],
			(int) $payload['active_count'],
			(int) $payload['alert_count'],
			array(
				'overall'      => $payload['overall'],
				'active_count' => $payload['active_count'],
				'total_count'  => $payload['total_count'],
				'alert_count'  => $payload['alert_count'],
				'plugins'      => array_map(
					static function ( array $p ) : array {
						return array(
							'slug'    => (string) $p['slug'],
							'name'    => (string) $p['name'],
							'active'  => (bool) $p['active'],
							'score'   => (int) $p['score'],
							'alerts'  => count( (array) $p['alerts'] ),
						);
					},
					(array) $payload['plugins']
				),
			)
		);
		$s = Settings::get();
		SnapshotStore::prune( (int) $s['snapshot_retention_days'] );
	}

	/**
	 * Severity → CSS class name (used by Admin and Calendar).
	 */
	public static function severity_class( string $sev ) : string {
		switch ( $sev ) {
			case 'crit':
				return 'eurocomply-dash-alert--crit';
			case 'warn':
				return 'eurocomply-dash-alert--warn';
			case 'info':
			default:
				return 'eurocomply-dash-alert--info';
		}
	}

	/**
	 * 0–100 → traffic-light label.
	 */
	public static function score_label( int $score ) : string {
		if ( $score >= 80 ) {
			return 'green';
		}
		if ( $score >= 50 ) {
			return 'amber';
		}
		return 'red';
	}
}
