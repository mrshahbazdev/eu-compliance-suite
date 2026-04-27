<?php
/**
 * Shipment / Due Diligence Statement (DDS) register.
 *
 * Art. 4 + Art. 33 — operators submit a DDS via TRACES NT before placing on
 * market or exporting. Each DDS gets a reference number; downstream traders
 * cite the reference number on their own DDS.
 *
 * @package EuroComply\EUDR
 */

declare( strict_types = 1 );

namespace EuroComply\EUDR;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ShipmentStore {

	private const DB_VERSION_OPTION = 'eurocomply_eudr_shipments_db_version';
	private const DB_VERSION        = '0.1.0';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_eudr_shipments';
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
			year INT(4) UNSIGNED NOT NULL DEFAULT 0,
			commodity VARCHAR(32) NOT NULL DEFAULT '',
			hs_code VARCHAR(16) NOT NULL DEFAULT '',
			description VARCHAR(255) NOT NULL DEFAULT '',
			quantity DECIMAL(14,4) NOT NULL DEFAULT 0,
			unit VARCHAR(8) NOT NULL DEFAULT 'kg',
			supplier_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			country_origin CHAR(2) NOT NULL DEFAULT '',
			plot_ids VARCHAR(255) NOT NULL DEFAULT '',
			upstream_dds VARCHAR(64) NOT NULL DEFAULT '',
			dds_reference VARCHAR(64) NOT NULL DEFAULT '',
			dds_status VARCHAR(16) NOT NULL DEFAULT 'draft',
			submitted_at DATETIME NULL DEFAULT NULL,
			risk_level VARCHAR(8) NOT NULL DEFAULT '',
			notes LONGTEXT NULL,
			PRIMARY KEY  (id),
			KEY year (year),
			KEY commodity (commodity),
			KEY supplier_id (supplier_id),
			KEY country_origin (country_origin),
			KEY dds_status (dds_status),
			KEY risk_level (risk_level)
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
		$plot_ids = isset( $row['plot_ids'] ) && is_array( $row['plot_ids'] )
			? implode( ',', array_filter( array_map( 'absint', $row['plot_ids'] ) ) )
			: sanitize_text_field( (string) ( $row['plot_ids'] ?? '' ) );
		$wpdb->insert(
			self::table_name(),
			array(
				'created_at'     => current_time( 'mysql' ),
				'year'           => (int) ( $row['year'] ?? gmdate( 'Y' ) ),
				'commodity'      => sanitize_key( (string) ( $row['commodity'] ?? '' ) ),
				'hs_code'        => sanitize_text_field( (string) ( $row['hs_code'] ?? '' ) ),
				'description'    => sanitize_text_field( (string) ( $row['description'] ?? '' ) ),
				'quantity'       => (float) ( $row['quantity'] ?? 0 ),
				'unit'           => sanitize_key( (string) ( $row['unit'] ?? 'kg' ) ),
				'supplier_id'    => (int) ( $row['supplier_id'] ?? 0 ),
				'country_origin' => strtoupper( substr( (string) ( $row['country_origin'] ?? '' ), 0, 2 ) ),
				'plot_ids'       => $plot_ids,
				'upstream_dds'   => sanitize_text_field( (string) ( $row['upstream_dds'] ?? '' ) ),
				'dds_reference'  => sanitize_text_field( (string) ( $row['dds_reference'] ?? '' ) ),
				'dds_status'     => in_array( (string) ( $row['dds_status'] ?? 'draft' ), array( 'draft', 'submitted', 'accepted', 'rejected' ), true ) ? (string) $row['dds_status'] : 'draft',
				'submitted_at'   => null,
				'risk_level'     => in_array( (string) ( $row['risk_level'] ?? '' ), array( '', 'low', 'standard', 'high' ), true ) ? (string) $row['risk_level'] : '',
				'notes'          => wp_kses_post( (string) ( $row['notes'] ?? '' ) ),
			)
		);
		return (int) $wpdb->insert_id;
	}

	public static function update( int $id, array $changes ) : void {
		global $wpdb;
		$set = array();
		foreach ( $changes as $k => $v ) {
			if ( in_array( $k, array( 'dds_status', 'dds_reference', 'risk_level', 'submitted_at', 'notes' ), true ) ) {
				$set[ $k ] = $v;
			}
		}
		if ( empty( $set ) ) {
			return;
		}
		$wpdb->update( self::table_name(), $set, array( 'id' => $id ), null, array( '%d' ) );
	}

	public static function delete( int $id ) : void {
		global $wpdb;
		$wpdb->delete( self::table_name(), array( 'id' => $id ), array( '%d' ) );
	}

	public static function all( int $limit = 200 ) : array {
		global $wpdb;
		$table = self::table_name();
		$limit = max( 1, min( 5000, $limit ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit ), ARRAY_A );
	}

	public static function for_year( int $year ) : array {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE year = %d ORDER BY id ASC", $year ), ARRAY_A );
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

	public static function count_by_status( string $status ) : int {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE dds_status = %s", $status ) );
	}

	public static function count_high_risk() : int {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE risk_level = 'high'" );
	}
}
