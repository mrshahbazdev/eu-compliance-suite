<?php
/**
 * Transparency report generator (DSA Articles 15 / 24).
 *
 * Builds an aggregated report across a date range covering: total notices
 * received, broken down by category; statements of reasons issued, broken
 * down by restriction type; share of automated decisions; average response
 * time; trader verification status counts. Output formats: PHP array, JSON,
 * CSV.
 *
 * @package EuroComply\DSA
 */

declare( strict_types = 1 );

namespace EuroComply\DSA;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TransparencyReport {

	/**
	 * @return array<string,mixed>
	 */
	public static function build( int $since_ts, int $until_ts ) : array {
		$notices_total    = NoticeStore::count_since( $since_ts );
		$categories       = NoticeStore::category_counts( $since_ts, $until_ts );
		$statements_total = StatementStore::count_since( $since_ts );
		$restrictions     = StatementStore::restriction_counts( $since_ts, $until_ts );
		$automated        = StatementStore::automated_count( $since_ts, $until_ts );
		$ai_generated     = StatementStore::ai_generated_count( $since_ts, $until_ts );
		$ai_deepfake      = StatementStore::ai_deepfake_count( $since_ts, $until_ts );
		$traders          = TraderStore::status_counts();

		$automated_share    = $statements_total > 0 ? round( ( $automated / $statements_total ) * 100, 2 ) : 0.0;
		$ai_generated_share = $statements_total > 0 ? round( ( $ai_generated / $statements_total ) * 100, 2 ) : 0.0;

		$settings = Settings::get();

		return array(
			'schema'           => 'eurocomply-dsa-transparency',
			'schema_version'   => '1.0',
			'generator'        => 'EuroComply DSA ' . EUROCOMPLY_DSA_VERSION,
			'site'             => array(
				'name'                 => get_bloginfo( 'name' ),
				'url'                  => home_url( '/' ),
				'contact_point_email'  => (string) $settings['contact_point_email'],
				'legal_representative' => (string) $settings['legal_representative'],
			),
			'period'           => array(
				'from' => gmdate( 'c', $since_ts ),
				'to'   => gmdate( 'c', $until_ts ),
			),
			'notices'          => array(
				'total'      => $notices_total,
				'categories' => $categories,
			),
			'statements'       => array(
				'total'              => $statements_total,
				'restriction_types'  => $restrictions,
				'automated'          => $automated,
				'automated_share'    => $automated_share,
				'ai_generated'       => $ai_generated,
				'ai_generated_share' => $ai_generated_share,
				'ai_deepfake'        => $ai_deepfake,
			),
			'traders'          => $traders,
			'generated_at'     => gmdate( 'c' ),
		);
	}

	public static function to_json( array $report ) : string {
		return (string) wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	/**
	 * Flatten the report into a small CSV (section,key,value).
	 */
	public static function to_csv( array $report ) : string {
		$rows = array();
		$rows[] = array( 'section', 'key', 'value' );
		$rows[] = array( 'period', 'from', (string) $report['period']['from'] );
		$rows[] = array( 'period', 'to', (string) $report['period']['to'] );
		$rows[] = array( 'notices', 'total', (string) $report['notices']['total'] );
		foreach ( (array) $report['notices']['categories'] as $cat => $n ) {
			$rows[] = array( 'notices.category', (string) $cat, (string) $n );
		}
		$rows[] = array( 'statements', 'total', (string) $report['statements']['total'] );
		$rows[] = array( 'statements', 'automated', (string) $report['statements']['automated'] );
		$rows[] = array( 'statements', 'automated_share', (string) $report['statements']['automated_share'] );
		$rows[] = array( 'statements', 'ai_generated', (string) ( isset( $report['statements']['ai_generated'] ) ? $report['statements']['ai_generated'] : 0 ) );
		$rows[] = array( 'statements', 'ai_generated_share', (string) ( isset( $report['statements']['ai_generated_share'] ) ? $report['statements']['ai_generated_share'] : 0 ) );
		$rows[] = array( 'statements', 'ai_deepfake', (string) ( isset( $report['statements']['ai_deepfake'] ) ? $report['statements']['ai_deepfake'] : 0 ) );
		foreach ( (array) $report['statements']['restriction_types'] as $type => $n ) {
			$rows[] = array( 'statements.restriction_type', (string) $type, (string) $n );
		}
		foreach ( (array) $report['traders'] as $status => $n ) {
			$rows[] = array( 'traders.status', (string) $status, (string) $n );
		}

		$buf = fopen( 'php://temp', 'w+' );
		if ( false === $buf ) {
			return '';
		}
		foreach ( $rows as $row ) {
			fputcsv( $buf, $row );
		}
		rewind( $buf );
		$out = stream_get_contents( $buf );
		fclose( $buf );
		return (string) $out;
	}
}
