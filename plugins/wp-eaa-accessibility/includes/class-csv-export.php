<?php
/**
 * CSV export of recorded accessibility issues.
 *
 * @package EuroComply\Eaa
 */

declare( strict_types = 1 );

namespace EuroComply\Eaa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CsvExport {

	public static function stream() : void {
		$rows     = IssueStore::recent_issues( 5000 );
		$filename = sprintf( 'eurocomply-eaa-%s.csv', gmdate( 'Ymd-His' ) );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		$out = fopen( 'php://output', 'w' );
		if ( ! is_resource( $out ) ) {
			return;
		}

		fputcsv(
			$out,
			array(
				'scanned_at',
				'object_type',
				'object_id',
				'url',
				'rule',
				'wcag',
				'severity',
				'snippet',
			)
		);

		foreach ( $rows as $r ) {
			fputcsv(
				$out,
				array(
					(string) ( $r['scanned_at'] ?? '' ),
					(string) ( $r['object_type'] ?? '' ),
					(int) ( $r['object_id'] ?? 0 ),
					(string) ( $r['url'] ?? '' ),
					(string) ( $r['rule'] ?? '' ),
					(string) ( $r['wcag'] ?? '' ),
					(string) ( $r['severity'] ?? '' ),
					(string) ( $r['snippet'] ?? '' ),
				)
			);
		}

		fclose( $out );
	}
}
