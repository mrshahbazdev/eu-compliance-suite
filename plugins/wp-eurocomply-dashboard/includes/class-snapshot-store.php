<?php
/**
 * Daily compliance-score snapshot store.
 *
 * @package EuroComply\Dashboard
 */

declare( strict_types = 1 );

namespace EuroComply\Dashboard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SnapshotStore {

	public const DB_VERSION_OPTION = 'eurocomply_dashboard_snapshots_db_version';
	public const DB_VERSION        = '1.0.0';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_dashboard_snapshots';
	}

	public static function install() : void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			occurred_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			score TINYINT UNSIGNED NOT NULL DEFAULT 0,
			active_count INT UNSIGNED NOT NULL DEFAULT 0,
			alert_count INT UNSIGNED NOT NULL DEFAULT 0,
			payload LONGTEXT NULL,
			PRIMARY KEY  (id),
			KEY occurred_at (occurred_at)
		) {$charset};";

		dbDelta( $sql );
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
	}

	public static function maybe_upgrade() : void {
		if ( get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * @param array<string,mixed> $payload
	 */
	public static function record( int $score, int $active_count, int $alert_count, array $payload ) : int {
		global $wpdb;
		$row = array(
			'occurred_at'  => current_time( 'mysql' ),
			'score'        => max( 0, min( 100, $score ) ),
			'active_count' => max( 0, $active_count ),
			'alert_count'  => max( 0, $alert_count ),
			'payload'      => wp_json_encode( $payload ),
		);
		$ok = $wpdb->insert( self::table_name(), $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return false === $ok ? 0 : (int) $wpdb->insert_id;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function recent( int $limit = 90 ) : array {
		global $wpdb;
		$limit = max( 1, min( 5000, $limit ) );
		$table = self::table_name();
		$sql   = $wpdb->prepare( "SELECT id, occurred_at, score, active_count, alert_count FROM {$table} ORDER BY id DESC LIMIT %d", $limit ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows  = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return is_array( $rows ) ? $rows : array();
	}

	public static function prune( int $keep_days ) : int {
		global $wpdb;
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - max( 1, $keep_days ) * DAY_IN_SECONDS );
		$table  = self::table_name();
		$sql    = $wpdb->prepare( "DELETE FROM {$table} WHERE occurred_at < %s", $cutoff ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$res    = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return false === $res ? 0 : (int) $res;
	}
}
