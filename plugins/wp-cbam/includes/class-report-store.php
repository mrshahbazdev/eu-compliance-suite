<?php
/**
 * CBAM quarterly report snapshots.
 *
 * @package EuroComply\CBAM
 */

declare( strict_types = 1 );

namespace EuroComply\CBAM;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ReportStore {

	private const DB_VERSION_OPTION = 'eurocomply_cbam_reports_db_version';
	private const DB_VERSION        = '0.1.0';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_cbam_reports';
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
			period VARCHAR(10) NOT NULL DEFAULT '',
			imports_count INT UNSIGNED NOT NULL DEFAULT 0,
			total_quantity DECIMAL(14,4) NOT NULL DEFAULT 0,
			total_direct DECIMAL(18,4) NOT NULL DEFAULT 0,
			total_indirect DECIMAL(18,4) NOT NULL DEFAULT 0,
			xml_envelope LONGTEXT NULL,
			notes LONGTEXT NULL,
			PRIMARY KEY  (id),
			KEY period (period)
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
				'created_at'      => current_time( 'mysql' ),
				'period'          => (string) ( $row['period'] ?? '' ),
				'imports_count'   => max( 0, (int) ( $row['imports_count']   ?? 0 ) ),
				'total_quantity'  => (float) ( $row['total_quantity'] ?? 0 ),
				'total_direct'    => (float) ( $row['total_direct']   ?? 0 ),
				'total_indirect'  => (float) ( $row['total_indirect'] ?? 0 ),
				'xml_envelope'    => (string) ( $row['xml_envelope'] ?? '' ),
				'notes'           => wp_kses_post( (string) ( $row['notes'] ?? '' ) ),
			)
		);
		return (int) $wpdb->insert_id;
	}

	public static function recent( int $limit = 50 ) : array {
		global $wpdb;
		$table = self::table_name();
		$limit = max( 1, min( 5000, $limit ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, created_at, period, imports_count, total_quantity, total_direct, total_indirect FROM {$table} ORDER BY id DESC LIMIT %d", $limit ), ARRAY_A );
	}

	public static function get( int $id ) : ?array {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	public static function count_total() : int {
		global $wpdb;
		$table = self::table_name();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
