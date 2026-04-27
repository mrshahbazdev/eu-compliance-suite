<?php
/**
 * Pay-data CSV importer (employees + categories).
 *
 * @package EuroComply\PayTransparency
 */

declare( strict_types = 1 );

namespace EuroComply\PayTransparency;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CsvImport {

	/**
	 * @return array{ok:bool,inserted:int,errors:array<int,string>}
	 */
	public static function import_employees( string $file_path, int $year ) : array {
		$errors = array();
		if ( ! is_readable( $file_path ) ) {
			return array( 'ok' => false, 'inserted' => 0, 'errors' => array( 'unreadable file' ) );
		}
		$fh = fopen( $file_path, 'r' );
		if ( false === $fh ) {
			return array( 'ok' => false, 'inserted' => 0, 'errors' => array( 'fopen failed' ) );
		}
		$header = fgetcsv( $fh );
		if ( ! is_array( $header ) ) {
			fclose( $fh );
			return array( 'ok' => false, 'inserted' => 0, 'errors' => array( 'empty file' ) );
		}
		$header = array_map( 'trim', array_map( 'strtolower', $header ) );
		$cols   = array_flip( $header );
		foreach ( array( 'external_ref', 'category_slug', 'gender', 'total_comp', 'hours_per_week' ) as $required ) {
			if ( ! isset( $cols[ $required ] ) ) {
				fclose( $fh );
				return array( 'ok' => false, 'inserted' => 0, 'errors' => array( 'missing column: ' . $required ) );
			}
		}

		$inserted = 0;
		$line     = 1;
		while ( false !== ( $row = fgetcsv( $fh ) ) ) {
			$line++;
			if ( ! is_array( $row ) ) {
				continue;
			}
			$ref = isset( $cols['external_ref'] ) ? (string) ( $row[ $cols['external_ref'] ] ?? '' ) : '';
			if ( '' === trim( $ref ) ) {
				$errors[] = 'line ' . $line . ': missing external_ref';
				continue;
			}
			EmployeeStore::upsert(
				array(
					'external_ref'   => $ref,
					'category_slug'  => (string) ( $row[ $cols['category_slug'] ] ?? '' ),
					'gender'         => (string) ( $row[ $cols['gender'] ] ?? 'u' ),
					'total_comp'     => (float)  ( $row[ $cols['total_comp'] ] ?? 0 ),
					'hours_per_week' => (float)  ( $row[ $cols['hours_per_week'] ] ?? 40 ),
					'currency'       => isset( $cols['currency'] ) ? (string) ( $row[ $cols['currency'] ] ?? '' ) : '',
					'year'            => $year,
				)
			);
			$inserted++;
		}
		fclose( $fh );
		return array(
			'ok'       => true,
			'inserted' => $inserted,
			'errors'   => array_slice( $errors, 0, 20 ),
		);
	}
}
