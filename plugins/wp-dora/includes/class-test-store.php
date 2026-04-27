<?php
/**
 * Resilience-testing log (Art. 24-27).
 *
 * @package EuroComply\DORA
 */

declare( strict_types = 1 );

namespace EuroComply\DORA;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TestStore {

	private const DB_VERSION_OPTION = 'eurocomply_dora_tests_db_version';
	private const DB_VERSION        = '0.1.0';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_dora_tests';
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
			type VARCHAR(24) NOT NULL DEFAULT 'vuln_scan',
			scope VARCHAR(255) NOT NULL DEFAULT '',
			conducted_at DATE NULL DEFAULT NULL,
			finding_count INT(11) NOT NULL DEFAULT 0,
			critical_findings INT(11) NOT NULL DEFAULT 0,
			status VARCHAR(16) NOT NULL DEFAULT 'planned',
			report_url VARCHAR(255) NOT NULL DEFAULT '',
			notes LONGTEXT NULL,
			PRIMARY KEY  (id),
			KEY type (type),
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

	public static function create( array $row ) : int {
		global $wpdb;
		$wpdb->insert(
			self::table_name(),
			array(
				'created_at'        => current_time( 'mysql' ),
				'type'              => in_array( (string) ( $row['type'] ?? 'vuln_scan' ), array( 'vuln_scan', 'pen_test', 'tlpt', 'scenario', 'bcp', 'red_team' ), true ) ? (string) $row['type'] : 'vuln_scan',
				'scope'             => sanitize_text_field( (string) ( $row['scope'] ?? '' ) ),
				'conducted_at'      => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $row['conducted_at'] ?? '' ) ) ? (string) $row['conducted_at'] : null,
				'finding_count'     => max( 0, (int) ( $row['finding_count'] ?? 0 ) ),
				'critical_findings' => max( 0, (int) ( $row['critical_findings'] ?? 0 ) ),
				'status'            => in_array( (string) ( $row['status'] ?? 'planned' ), array( 'planned', 'in_progress', 'complete', 'remediated' ), true ) ? (string) $row['status'] : 'planned',
				'report_url'        => esc_url_raw( (string) ( $row['report_url'] ?? '' ) ),
				'notes'             => wp_kses_post( (string) ( $row['notes'] ?? '' ) ),
			)
		);
		return (int) $wpdb->insert_id;
	}

	public static function delete( int $id ) : void {
		global $wpdb;
		$wpdb->delete( self::table_name(), array( 'id' => $id ), array( '%d' ) );
	}

	public static function all() : array {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC", ARRAY_A );
	}

	public static function count_total() : int {
		global $wpdb;
		$table = self::table_name();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function count_open_critical() : int {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COALESCE(SUM(critical_findings),0) FROM {$table} WHERE status IN ('planned','in_progress','complete')" );
	}
}
