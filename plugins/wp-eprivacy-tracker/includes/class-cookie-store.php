<?php
/**
 * Live cookie observations from the JS sniffer.
 *
 * @package EuroComply\EPrivacy
 */

declare( strict_types = 1 );

namespace EuroComply\EPrivacy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CookieStore {

	private const DB_VERSION_OPTION = 'eurocomply_eprivacy_cookies_db_version';
	private const DB_VERSION        = '0.1.0';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_eprivacy_cookies';
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
			observed_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			page_url VARCHAR(500) NOT NULL DEFAULT '',
			cookie_name VARCHAR(255) NOT NULL DEFAULT '',
			cookie_domain VARCHAR(255) NOT NULL DEFAULT '',
			tracker_slug VARCHAR(64) NOT NULL DEFAULT '',
			category VARCHAR(32) NOT NULL DEFAULT '',
			session_id_hash CHAR(64) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			UNIQUE KEY uniq_per_session (cookie_name(150), session_id_hash),
			KEY tracker_slug (tracker_slug),
			KEY observed_at (observed_at)
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

	public static function record( string $page_url, string $name, string $domain, string $session_hash ) : void {
		global $wpdb;
		$slug     = TrackerRegistry::match_cookie_name( $name );
		$category = '';
		if ( '' !== $slug ) {
			$row      = TrackerRegistry::get( $slug );
			$category = $row ? (string) $row['category'] : '';
		}
		// INSERT IGNORE-equivalent via REPLACE on the unique key.
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare(
				'INSERT IGNORE INTO ' . self::table_name() . ' (observed_at, page_url, cookie_name, cookie_domain, tracker_slug, category, session_id_hash) VALUES (%s, %s, %s, %s, %s, %s, %s)',
				current_time( 'mysql' ),
				mb_substr( $page_url, 0, 500 ),
				mb_substr( $name, 0, 255 ),
				mb_substr( $domain, 0, 255 ),
				$slug,
				$category,
				$session_hash
			)
		);
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

	public static function distinct_count() : int {
		global $wpdb;
		$table = self::table_name();
		return (int) $wpdb->get_var( "SELECT COUNT(DISTINCT cookie_name) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
