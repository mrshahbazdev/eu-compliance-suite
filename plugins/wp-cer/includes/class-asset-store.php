<?php
/**
 * Assets / sites / dependencies map.
 *
 * @package EuroComply\CER
 */

declare( strict_types = 1 );

namespace EuroComply\CER;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AssetStore {

	private const DB_VERSION_OPTION = 'eurocomply_cer_assets_db_version';
	private const DB_VERSION        = '0.1.0';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_cer_assets';
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
			service_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			name VARCHAR(255) NOT NULL DEFAULT '',
			kind VARCHAR(24) NOT NULL DEFAULT 'site',
			country CHAR(2) NOT NULL DEFAULT '',
			address VARCHAR(255) NOT NULL DEFAULT '',
			lat DECIMAL(10,6) NULL DEFAULT NULL,
			lng DECIMAL(10,6) NULL DEFAULT NULL,
			supplier VARCHAR(255) NOT NULL DEFAULT '',
			single_point_of_failure TINYINT(1) NOT NULL DEFAULT 0,
			notes LONGTEXT NULL,
			PRIMARY KEY  (id),
			KEY service_id (service_id),
			KEY kind (kind)
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
				'created_at'              => current_time( 'mysql' ),
				'service_id'              => (int) ( $row['service_id'] ?? 0 ),
				'name'                    => sanitize_text_field( (string) ( $row['name'] ?? '' ) ),
				'kind'                    => in_array( (string) ( $row['kind'] ?? 'site' ), array( 'site', 'ict_system', 'supply_chain', 'utility' ), true ) ? (string) $row['kind'] : 'site',
				'country'                 => strtoupper( substr( (string) ( $row['country'] ?? '' ), 0, 2 ) ),
				'address'                 => sanitize_text_field( (string) ( $row['address'] ?? '' ) ),
				'lat'                     => is_numeric( $row['lat'] ?? null ) ? (float) $row['lat'] : null,
				'lng'                     => is_numeric( $row['lng'] ?? null ) ? (float) $row['lng'] : null,
				'supplier'                => sanitize_text_field( (string) ( $row['supplier'] ?? '' ) ),
				'single_point_of_failure' => ! empty( $row['single_point_of_failure'] ) ? 1 : 0,
				'notes'                   => wp_kses_post( (string) ( $row['notes'] ?? '' ) ),
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
		return (array) $wpdb->get_results( "SELECT * FROM {$table} ORDER BY kind ASC, name ASC", ARRAY_A );
	}

	public static function for_service( int $service_id ) : array {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE service_id = %d ORDER BY name ASC", $service_id ), ARRAY_A );
	}

	public static function count_total() : int {
		global $wpdb;
		$table = self::table_name();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function count_spof() : int {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE single_point_of_failure = 1" );
	}
}
