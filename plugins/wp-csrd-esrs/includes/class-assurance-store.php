<?php
/**
 * Assurance log (limited / reasonable engagement records).
 *
 * @package EuroComply\CSRD
 */

declare( strict_types = 1 );

namespace EuroComply\CSRD;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AssuranceStore {

	private const DB_VERSION_OPTION = 'eurocomply_csrd_assurance_db_version';
	private const DB_VERSION        = '0.1.0';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_csrd_assurance';
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
			provider VARCHAR(255) NOT NULL DEFAULT '',
			level VARCHAR(16) NOT NULL DEFAULT 'limited',
			scope LONGTEXT NULL,
			opinion LONGTEXT NULL,
			signed_at DATETIME NULL DEFAULT NULL,
			signatory VARCHAR(255) NOT NULL DEFAULT '',
			report_url VARCHAR(255) NOT NULL DEFAULT '',
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
				'created_at' => current_time( 'mysql' ),
				'year'       => (int) ( $row['year'] ?? gmdate( 'Y' ) ),
				'provider'   => sanitize_text_field( (string) ( $row['provider'] ?? '' ) ),
				'level'      => in_array( (string) ( $row['level'] ?? '' ), array( 'limited', 'reasonable', 'none' ), true ) ? (string) $row['level'] : 'limited',
				'scope'      => wp_kses_post( (string) ( $row['scope'] ?? '' ) ),
				'opinion'    => wp_kses_post( (string) ( $row['opinion'] ?? '' ) ),
				'signed_at'  => isset( $row['signed_at'] ) && '' !== (string) $row['signed_at'] ? (string) $row['signed_at'] : null,
				'signatory'  => sanitize_text_field( (string) ( $row['signatory'] ?? '' ) ),
				'report_url' => esc_url_raw( (string) ( $row['report_url'] ?? '' ) ),
			)
		);
		return (int) $wpdb->insert_id;
	}

	public static function delete( int $id ) : void {
		global $wpdb;
		$wpdb->delete( self::table_name(), array( 'id' => $id ), array( '%d' ) );
	}

	public static function recent( int $limit = 50 ) : array {
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

	public static function latest_for_year( int $year ) : ?array {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE year = %d ORDER BY id DESC LIMIT 1", $year ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}
}
