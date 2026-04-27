<?php
/**
 * Crypto-asset register.
 *
 * @package EuroComply\MiCA
 */

declare( strict_types = 1 );

namespace EuroComply\MiCA;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AssetStore {

	private const DB_VERSION_OPTION = 'eurocomply_mica_assets_db_version';
	private const DB_VERSION        = '0.1.0';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_mica_assets';
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
			ticker VARCHAR(32) NOT NULL DEFAULT '',
			category VARCHAR(16) NOT NULL DEFAULT 'other',
			significant TINYINT(1) NOT NULL DEFAULT 0,
			network VARCHAR(64) NOT NULL DEFAULT '',
			contract_address VARCHAR(96) NOT NULL DEFAULT '',
			isin VARCHAR(32) NOT NULL DEFAULT '',
			pegged_to VARCHAR(48) NOT NULL DEFAULT '',
			reserve_assets LONGTEXT NULL,
			max_supply VARCHAR(64) NOT NULL DEFAULT '',
			circulating VARCHAR(64) NOT NULL DEFAULT '',
			status VARCHAR(24) NOT NULL DEFAULT 'draft',
			notes LONGTEXT NULL,
			PRIMARY KEY  (id),
			KEY category (category),
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
				'created_at'       => current_time( 'mysql' ),
				'name'             => sanitize_text_field( (string) ( $row['name'] ?? '' ) ),
				'ticker'           => strtoupper( substr( preg_replace( '/[^A-Za-z0-9]/', '', (string) ( $row['ticker'] ?? '' ) ), 0, 32 ) ),
				'category'         => in_array( (string) ( $row['category'] ?? 'other' ), array( 'art', 'emt', 'utility', 'other' ), true ) ? (string) $row['category'] : 'other',
				'significant'      => ! empty( $row['significant'] ) ? 1 : 0,
				'network'          => sanitize_text_field( (string) ( $row['network'] ?? '' ) ),
				'contract_address' => sanitize_text_field( (string) ( $row['contract_address'] ?? '' ) ),
				'isin'             => sanitize_text_field( (string) ( $row['isin'] ?? '' ) ),
				'pegged_to'        => sanitize_text_field( (string) ( $row['pegged_to'] ?? '' ) ),
				'reserve_assets'   => wp_kses_post( (string) ( $row['reserve_assets'] ?? '' ) ),
				'max_supply'       => sanitize_text_field( (string) ( $row['max_supply'] ?? '' ) ),
				'circulating'      => sanitize_text_field( (string) ( $row['circulating'] ?? '' ) ),
				'status'           => in_array( (string) ( $row['status'] ?? 'draft' ), array( 'draft', 'notified', 'live', 'suspended', 'redeemed' ), true ) ? (string) $row['status'] : 'draft',
				'notes'            => wp_kses_post( (string) ( $row['notes'] ?? '' ) ),
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
		return (array) $wpdb->get_results( "SELECT * FROM {$table} ORDER BY category ASC, name ASC", ARRAY_A );
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

	public static function count_category( string $cat ) : int {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE category = %s", $cat ) );
	}

	public static function count_significant() : int {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE significant = 1" );
	}
}
