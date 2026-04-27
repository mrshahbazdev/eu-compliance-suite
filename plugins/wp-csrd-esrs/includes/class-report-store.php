<?php
/**
 * CSRD report snapshots.
 *
 * @package EuroComply\CSRD
 */

declare( strict_types = 1 );

namespace EuroComply\CSRD;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ReportStore {

	private const DB_VERSION_OPTION = 'eurocomply_csrd_reports_db_version';
	private const DB_VERSION        = '0.1.0';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_csrd_reports';
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
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			year SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			datapoints_count INT UNSIGNED NOT NULL DEFAULT 0,
			material_topics INT UNSIGNED NOT NULL DEFAULT 0,
			coverage_pct DECIMAL(5,2) NOT NULL DEFAULT 0,
			xbrl_envelope LONGTEXT NULL,
			payload LONGTEXT NULL,
			PRIMARY KEY  (id),
			KEY year (year)
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

	public static function create( array $row ) : int {
		global $wpdb;
		$wpdb->insert(
			self::table_name(),
			array(
				'created_at'       => current_time( 'mysql' ),
				'year'             => (int) ( $row['year'] ?? 0 ),
				'datapoints_count' => max( 0, (int) ( $row['datapoints_count'] ?? 0 ) ),
				'material_topics'  => max( 0, (int) ( $row['material_topics']  ?? 0 ) ),
				'coverage_pct'     => max( 0, (float) ( $row['coverage_pct'] ?? 0 ) ),
				'xbrl_envelope'    => (string) ( $row['xbrl_envelope'] ?? '' ),
				'payload'          => (string) ( $row['payload']       ?? '' ),
			)
		);
		return (int) $wpdb->insert_id;
	}

	public static function get( int $id ) : ?array {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	public static function recent( int $limit = 30 ) : array {
		global $wpdb;
		$table = self::table_name();
		$limit = max( 1, min( 5000, $limit ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, created_at, year, datapoints_count, material_topics, coverage_pct FROM {$table} ORDER BY id DESC LIMIT %d", $limit ), ARRAY_A );
	}

	public static function count_total() : int {
		global $wpdb;
		$table = self::table_name();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
