<?php
/**
 * Risk-assessment + mitigation log (Art. 10 / Art. 11).
 *
 * @package EuroComply\EUDR
 */

declare( strict_types = 1 );

namespace EuroComply\EUDR;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RiskStore {

	private const DB_VERSION_OPTION = 'eurocomply_eudr_risk_db_version';
	private const DB_VERSION        = '0.1.0';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_eudr_risk';
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
			shipment_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			factor VARCHAR(48) NOT NULL DEFAULT '',
			level VARCHAR(8) NOT NULL DEFAULT 'standard',
			finding LONGTEXT NULL,
			mitigation LONGTEXT NULL,
			conclusion VARCHAR(16) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY shipment_id (shipment_id),
			KEY level (level),
			KEY conclusion (conclusion)
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
				'created_at'  => current_time( 'mysql' ),
				'shipment_id' => (int) ( $row['shipment_id'] ?? 0 ),
				'factor'      => sanitize_key( (string) ( $row['factor'] ?? 'general' ) ),
				'level'       => in_array( (string) ( $row['level'] ?? 'standard' ), array( 'low', 'standard', 'high' ), true ) ? (string) $row['level'] : 'standard',
				'finding'     => wp_kses_post( (string) ( $row['finding']    ?? '' ) ),
				'mitigation'  => wp_kses_post( (string) ( $row['mitigation'] ?? '' ) ),
				'conclusion'  => in_array( (string) ( $row['conclusion'] ?? '' ), array( '', 'negligible', 'non_negligible' ), true ) ? (string) $row['conclusion'] : '',
			)
		);
		return (int) $wpdb->insert_id;
	}

	public static function delete( int $id ) : void {
		global $wpdb;
		$wpdb->delete( self::table_name(), array( 'id' => $id ), array( '%d' ) );
	}

	public static function for_shipment( int $shipment_id ) : array {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE shipment_id = %d ORDER BY id ASC", $shipment_id ), ARRAY_A );
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

	public static function count_non_negligible() : int {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE conclusion = 'non_negligible'" );
	}
}
