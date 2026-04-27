<?php
/**
 * Plot / geolocation register.
 *
 * Art. 9(1)(d) — geolocation of all plots of land where the relevant commodity
 * was produced, with date or time-range of production. Plots ≤ 4 ha may use a
 * single point; otherwise a polygon.
 *
 * @package EuroComply\EUDR
 */

declare( strict_types = 1 );

namespace EuroComply\EUDR;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PlotStore {

	private const DB_VERSION_OPTION = 'eurocomply_eudr_plots_db_version';
	private const DB_VERSION        = '0.1.0';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_eudr_plots';
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
			supplier_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			country CHAR(2) NOT NULL DEFAULT '',
			label VARCHAR(255) NOT NULL DEFAULT '',
			geom_type VARCHAR(16) NOT NULL DEFAULT 'point',
			lat DECIMAL(10,7) NOT NULL DEFAULT 0,
			lng DECIMAL(10,7) NOT NULL DEFAULT 0,
			polygon LONGTEXT NULL,
			area_ha DECIMAL(12,4) NOT NULL DEFAULT 0,
			production_from DATE NULL DEFAULT NULL,
			production_to DATE NULL DEFAULT NULL,
			deforestation_check VARCHAR(32) NOT NULL DEFAULT 'pending',
			notes LONGTEXT NULL,
			PRIMARY KEY  (id),
			KEY supplier_id (supplier_id),
			KEY country (country),
			KEY deforestation_check (deforestation_check)
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
		$polygon = (string) ( $row['polygon'] ?? '' );
		// Validate polygon JSON if provided.
		if ( '' !== $polygon ) {
			$decoded = json_decode( $polygon, true );
			if ( ! is_array( $decoded ) ) {
				$polygon = '';
			}
		}
		$wpdb->insert(
			self::table_name(),
			array(
				'created_at'          => current_time( 'mysql' ),
				'supplier_id'         => (int) ( $row['supplier_id'] ?? 0 ),
				'country'             => strtoupper( substr( (string) ( $row['country'] ?? '' ), 0, 2 ) ),
				'label'               => sanitize_text_field( (string) ( $row['label'] ?? '' ) ),
				'geom_type'           => in_array( (string) ( $row['geom_type'] ?? 'point' ), array( 'point', 'polygon' ), true ) ? (string) $row['geom_type'] : 'point',
				'lat'                 => (float) ( $row['lat'] ?? 0 ),
				'lng'                 => (float) ( $row['lng'] ?? 0 ),
				'polygon'             => $polygon,
				'area_ha'             => (float) ( $row['area_ha'] ?? 0 ),
				'production_from'     => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $row['production_from'] ?? '' ) ) ? (string) $row['production_from'] : null,
				'production_to'       => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $row['production_to']   ?? '' ) ) ? (string) $row['production_to']   : null,
				'deforestation_check' => in_array( (string) ( $row['deforestation_check'] ?? 'pending' ), array( 'pending', 'pass', 'fail', 'inconclusive' ), true ) ? (string) $row['deforestation_check'] : 'pending',
				'notes'               => wp_kses_post( (string) ( $row['notes'] ?? '' ) ),
			)
		);
		return (int) $wpdb->insert_id;
	}

	public static function set_check( int $id, string $status ) : void {
		if ( ! in_array( $status, array( 'pending', 'pass', 'fail', 'inconclusive' ), true ) ) {
			return;
		}
		global $wpdb;
		$wpdb->update(
			self::table_name(),
			array( 'deforestation_check' => $status ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);
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

	public static function for_supplier( int $supplier_id ) : array {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE supplier_id = %d ORDER BY id ASC", $supplier_id ), ARRAY_A );
	}

	public static function count_total() : int {
		global $wpdb;
		$table = self::table_name();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function count_failed() : int {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE deforestation_check = 'fail'" );
	}
}
