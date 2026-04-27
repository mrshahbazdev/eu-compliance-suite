<?php
/**
 * Art. 9 annual report snapshots.
 *
 * @package EuroComply\PayTransparency
 */

declare( strict_types = 1 );

namespace EuroComply\PayTransparency;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ReportStore {

	private const DB_VERSION_OPTION = 'eurocomply_pt_reports_db_version';
	private const DB_VERSION        = '0.1.0';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_pt_reports';
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
			gap_overall_pct DECIMAL(6,2) NOT NULL DEFAULT 0,
			gap_overall_median_pct DECIMAL(6,2) NOT NULL DEFAULT 0,
			employees_count INT UNSIGNED NOT NULL DEFAULT 0,
			joint_assessment_required TINYINT UNSIGNED NOT NULL DEFAULT 0,
			payload_json LONGTEXT NULL,
			notes LONGTEXT NULL,
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

	/**
	 * @param array<string,mixed> $row
	 */
	public static function create( array $row ) : int {
		global $wpdb;
		$wpdb->insert(
			self::table_name(),
			array(
				'created_at'                => current_time( 'mysql' ),
				'year'                      => max( 0, (int) ( $row['year'] ?? 0 ) ),
				'gap_overall_pct'           => (float) ( $row['gap_overall_pct'] ?? 0 ),
				'gap_overall_median_pct'    => (float) ( $row['gap_overall_median_pct'] ?? 0 ),
				'employees_count'           => max( 0, (int) ( $row['employees_count'] ?? 0 ) ),
				'joint_assessment_required' => ! empty( $row['joint_assessment_required'] ) ? 1 : 0,
				'payload_json'              => wp_json_encode( $row['payload'] ?? array() ),
				'notes'                     => wp_kses_post( (string) ( $row['notes'] ?? '' ) ),
			)
		);
		return (int) $wpdb->insert_id;
	}

	public static function recent( int $limit = 50 ) : array {
		global $wpdb;
		$table = self::table_name();
		$limit = max( 1, min( 5000, $limit ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit ), ARRAY_A );
	}

	public static function get( int $id ) : ?array {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	public static function latest_for_year( int $year ) : ?array {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE year = %d ORDER BY id DESC LIMIT 1", $year ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	public static function count_total() : int {
		global $wpdb;
		$table = self::table_name();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
