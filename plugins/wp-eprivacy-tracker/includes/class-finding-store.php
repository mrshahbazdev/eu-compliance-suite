<?php
/**
 * Per-URL tracker findings (output of static HTML scan).
 *
 * @package EuroComply\EPrivacy
 */

declare( strict_types = 1 );

namespace EuroComply\EPrivacy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FindingStore {

	private const DB_VERSION_OPTION = 'eurocomply_eprivacy_findings_db_version';
	private const DB_VERSION        = '0.1.0';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_eprivacy_findings';
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
			scan_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			observed_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			page_url VARCHAR(500) NOT NULL DEFAULT '',
			tracker_slug VARCHAR(64) NOT NULL DEFAULT '',
			category VARCHAR(32) NOT NULL DEFAULT '',
			evidence TEXT NULL,
			PRIMARY KEY  (id),
			KEY scan_id (scan_id),
			KEY tracker_slug (tracker_slug),
			KEY category (category),
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

	public static function record( int $scan_id, string $page_url, string $slug, string $category, string $evidence ) : void {
		global $wpdb;
		$wpdb->insert(
			self::table_name(),
			array(
				'scan_id'      => $scan_id,
				'observed_at'  => current_time( 'mysql' ),
				'page_url'     => mb_substr( $page_url, 0, 500 ),
				'tracker_slug' => $slug,
				'category'     => $category,
				'evidence'     => mb_substr( $evidence, 0, 1000 ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	public static function for_scan( int $scan_id, int $limit = 1000 ) : array {
		global $wpdb;
		$table = self::table_name();
		$limit = max( 1, min( 5000, $limit ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE scan_id = %d ORDER BY id DESC LIMIT %d", $scan_id, $limit ), ARRAY_A );
	}

	public static function recent( int $limit = 200 ) : array {
		global $wpdb;
		$table = self::table_name();
		$limit = max( 1, min( 5000, $limit ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit ), ARRAY_A );
	}

	public static function distinct_slugs_for_scan( int $scan_id ) : array {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = (array) $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT tracker_slug FROM {$table} WHERE scan_id = %d", $scan_id ) );
		return array_values( array_filter( array_map( 'strval', $rows ) ) );
	}

	public static function distinct_slugs_latest() : array {
		$id = ScanStore::latest_id();
		return $id > 0 ? self::distinct_slugs_for_scan( $id ) : array();
	}

	public static function count_total() : int {
		global $wpdb;
		$table = self::table_name();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function count_consent_required_latest() : int {
		$slugs = self::distinct_slugs_latest();
		$n     = 0;
		foreach ( $slugs as $slug ) {
			$row = TrackerRegistry::get( $slug );
			if ( $row && $row['consent_required'] ) {
				$n++;
			}
		}
		return $n;
	}
}
