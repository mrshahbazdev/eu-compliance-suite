<?php
/**
 * Essential-services register.
 *
 * @package EuroComply\CER
 */

declare( strict_types = 1 );

namespace EuroComply\CER;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ServiceStore {

	private const DB_VERSION_OPTION = 'eurocomply_cer_services_db_version';
	private const DB_VERSION        = '0.1.0';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_cer_services';
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
			name VARCHAR(255) NOT NULL DEFAULT '',
			sector VARCHAR(48) NOT NULL DEFAULT '',
			sub_sector VARCHAR(64) NOT NULL DEFAULT '',
			population_served INT(11) NOT NULL DEFAULT 0,
			geographic_scope VARCHAR(255) NOT NULL DEFAULT '',
			cross_border TINYINT(1) NOT NULL DEFAULT 0,
			disruption_threshold VARCHAR(48) NOT NULL DEFAULT '',
			notes LONGTEXT NULL,
			PRIMARY KEY  (id),
			KEY sector (sector)
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
				'created_at'           => current_time( 'mysql' ),
				'name'                 => sanitize_text_field( (string) ( $row['name'] ?? '' ) ),
				'sector'               => sanitize_key( (string) ( $row['sector'] ?? 'digital_infrastructure' ) ),
				'sub_sector'           => sanitize_text_field( (string) ( $row['sub_sector'] ?? '' ) ),
				'population_served'    => max( 0, (int) ( $row['population_served'] ?? 0 ) ),
				'geographic_scope'     => sanitize_text_field( (string) ( $row['geographic_scope'] ?? '' ) ),
				'cross_border'         => ! empty( $row['cross_border'] ) ? 1 : 0,
				'disruption_threshold' => sanitize_text_field( (string) ( $row['disruption_threshold'] ?? '' ) ),
				'notes'                => wp_kses_post( (string) ( $row['notes'] ?? '' ) ),
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
		return (array) $wpdb->get_results( "SELECT * FROM {$table} ORDER BY sector ASC, name ASC", ARRAY_A );
	}

	public static function get( int $id ) : ?array {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		return $row ? (array) $row : null;
	}

	public static function count_total() : int {
		global $wpdb;
		$table = self::table_name();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function count_cross_border() : int {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE cross_border = 1" );
	}
}
