<?php
/**
 * Information-sharing log (Art. 45).
 *
 * @package EuroComply\DORA
 */

declare( strict_types = 1 );

namespace EuroComply\DORA;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class IntelStore {

	private const DB_VERSION_OPTION = 'eurocomply_dora_intel_db_version';
	private const DB_VERSION        = '0.1.0';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_dora_intel';
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
			direction VARCHAR(16) NOT NULL DEFAULT 'received',
			source VARCHAR(128) NOT NULL DEFAULT '',
			tlp VARCHAR(8) NOT NULL DEFAULT 'AMBER',
			summary LONGTEXT NULL,
			indicators LONGTEXT NULL,
			PRIMARY KEY  (id),
			KEY direction (direction),
			KEY tlp (tlp)
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
				'direction'  => in_array( (string) ( $row['direction'] ?? 'received' ), array( 'received', 'shared' ), true ) ? (string) $row['direction'] : 'received',
				'source'     => sanitize_text_field( (string) ( $row['source'] ?? '' ) ),
				'tlp'        => in_array( strtoupper( (string) ( $row['tlp'] ?? 'AMBER' ) ), array( 'CLEAR', 'GREEN', 'AMBER', 'AMBER+STRICT', 'RED' ), true ) ? strtoupper( (string) $row['tlp'] ) : 'AMBER',
				'summary'    => wp_kses_post( (string) ( $row['summary'] ?? '' ) ),
				'indicators' => wp_kses_post( (string) ( $row['indicators'] ?? '' ) ),
			)
		);
		return (int) $wpdb->insert_id;
	}

	public static function delete( int $id ) : void {
		global $wpdb;
		$wpdb->delete( self::table_name(), array( 'id' => $id ), array( '%d' ) );
	}

	public static function recent( int $limit = 100 ) : array {
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
}
