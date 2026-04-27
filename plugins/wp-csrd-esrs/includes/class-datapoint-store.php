<?php
/**
 * Datapoint values per reporting year.
 *
 * @package EuroComply\CSRD
 */

declare( strict_types = 1 );

namespace EuroComply\CSRD;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DatapointStore {

	private const DB_VERSION_OPTION = 'eurocomply_csrd_datapoints_db_version';
	private const DB_VERSION        = '0.1.0';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_csrd_datapoints';
	}

	public static function maybe_upgrade() : void {
		if ( get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	public static function install() : void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			year SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			datapoint_id VARCHAR(64) NOT NULL DEFAULT '',
			value_numeric DECIMAL(20,6) NULL DEFAULT NULL,
			value_text LONGTEXT NULL,
			unit VARCHAR(16) NOT NULL DEFAULT '',
			source VARCHAR(64) NOT NULL DEFAULT 'manual',
			notes LONGTEXT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY year_dp (year,datapoint_id),
			KEY datapoint_id (datapoint_id)
		) {$charset};";
		dbDelta( $sql );
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
	}

	public static function uninstall() : void {
		global $wpdb;
		$table = self::table_name();
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		delete_option( self::DB_VERSION_OPTION );
	}

	public static function upsert( array $row ) : int {
		global $wpdb;
		$year = max( 1900, (int) ( $row['year']         ?? gmdate( 'Y' ) ) );
		$dp   = sanitize_text_field( (string) ( $row['datapoint_id'] ?? '' ) );
		if ( '' === $dp ) {
			return 0;
		}

		$dp_def = EsrsRegistry::get_dp( $dp );
		$kind   = $dp_def['kind'] ?? 'narrative';
		$unit   = (string) ( $dp_def['unit'] ?? '' );
		$value_numeric = null;
		$value_text    = null;
		if ( 'numeric' === $kind && isset( $row['value_numeric'] ) ) {
			$value_numeric = (float) $row['value_numeric'];
		}
		if ( isset( $row['value_text'] ) ) {
			$value_text = wp_kses_post( (string) $row['value_text'] );
		}

		$existing = self::get( $year, $dp );
		$data = array(
			'updated_at'    => current_time( 'mysql' ),
			'year'          => $year,
			'datapoint_id'  => $dp,
			'value_numeric' => $value_numeric,
			'value_text'    => $value_text,
			'unit'          => $unit,
			'source'        => sanitize_text_field( (string) ( $row['source'] ?? 'manual' ) ),
			'notes'         => wp_kses_post( (string) ( $row['notes'] ?? '' ) ),
		);
		if ( $existing ) {
			$wpdb->update( self::table_name(), $data, array( 'id' => (int) $existing['id'] ) );
			return (int) $existing['id'];
		}
		$wpdb->insert( self::table_name(), $data );
		return (int) $wpdb->insert_id;
	}

	public static function get( int $year, string $dp ) : ?array {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE year = %d AND datapoint_id = %s", $year, $dp ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	public static function for_year( int $year ) : array {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE year = %d ORDER BY datapoint_id ASC", $year ), ARRAY_A );
	}

	public static function recent( int $limit = 200 ) : array {
		global $wpdb;
		$table = self::table_name();
		$limit = max( 1, min( 5000, $limit ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit ), ARRAY_A );
	}

	public static function count_total() : int {
		global $wpdb;
		$table = self::table_name();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function count_for_year( int $year ) : int {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE year = %d", $year ) );
	}
}
