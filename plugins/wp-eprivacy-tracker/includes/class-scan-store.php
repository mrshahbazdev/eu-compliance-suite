<?php
/**
 * Scan run record.
 *
 * @package EuroComply\EPrivacy
 */

declare( strict_types = 1 );

namespace EuroComply\EPrivacy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ScanStore {

	private const DB_VERSION_OPTION = 'eurocomply_eprivacy_scans_db_version';
	private const DB_VERSION        = '0.1.0';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_eprivacy_scans';
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
			started_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			finished_at DATETIME NULL DEFAULT NULL,
			status VARCHAR(32) NOT NULL DEFAULT 'pending',
			urls_scanned INT UNSIGNED NOT NULL DEFAULT 0,
			findings_count INT UNSIGNED NOT NULL DEFAULT 0,
			cookies_count INT UNSIGNED NOT NULL DEFAULT 0,
			notes LONGTEXT NULL,
			PRIMARY KEY  (id),
			KEY started_at (started_at),
			KEY status (status)
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

	public static function start() : int {
		global $wpdb;
		$wpdb->insert(
			self::table_name(),
			array(
				'started_at' => current_time( 'mysql' ),
				'status'     => 'running',
			),
			array( '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	public static function finish( int $scan_id, int $urls, int $findings, int $cookies = 0, string $notes = '' ) : void {
		global $wpdb;
		$wpdb->update(
			self::table_name(),
			array(
				'finished_at'    => current_time( 'mysql' ),
				'status'         => 'finished',
				'urls_scanned'   => $urls,
				'findings_count' => $findings,
				'cookies_count'  => $cookies,
				'notes'          => $notes,
			),
			array( 'id' => $scan_id ),
			array( '%s', '%s', '%d', '%d', '%d', '%s' ),
			array( '%d' )
		);
	}

	public static function fail( int $scan_id, string $msg ) : void {
		global $wpdb;
		$wpdb->update(
			self::table_name(),
			array(
				'finished_at' => current_time( 'mysql' ),
				'status'      => 'failed',
				'notes'       => $msg,
			),
			array( 'id' => $scan_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	public static function recent( int $limit = 50 ) : array {
		global $wpdb;
		$limit = max( 1, min( 5000, $limit ) );
		$table = self::table_name();
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

	public static function latest_id() : int {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT MAX(id) FROM {$table} WHERE status = 'finished'" );
	}

	public static function count_total() : int {
		global $wpdb;
		$table = self::table_name();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
