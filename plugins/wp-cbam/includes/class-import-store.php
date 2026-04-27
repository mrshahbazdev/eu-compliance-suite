<?php
/**
 * Import declarations register.
 *
 * @package EuroComply\CBAM
 */

declare( strict_types = 1 );

namespace EuroComply\CBAM;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ImportStore {

	private const DB_VERSION_OPTION = 'eurocomply_cbam_imports_db_version';
	private const DB_VERSION        = '0.1.0';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_cbam_imports';
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
			imported_at DATETIME NULL DEFAULT NULL,
			period VARCHAR(10) NOT NULL DEFAULT '',
			cn8 VARCHAR(8) NOT NULL DEFAULT '',
			category VARCHAR(32) NOT NULL DEFAULT '',
			origin_country CHAR(2) NOT NULL DEFAULT '',
			supplier VARCHAR(255) NOT NULL DEFAULT '',
			production_route VARCHAR(120) NOT NULL DEFAULT '',
			quantity DECIMAL(14,4) NOT NULL DEFAULT 0,
			unit VARCHAR(8) NOT NULL DEFAULT 't',
			direct_emissions DECIMAL(14,4) NOT NULL DEFAULT 0,
			indirect_emissions DECIMAL(14,4) NOT NULL DEFAULT 0,
			emissions_verified TINYINT UNSIGNED NOT NULL DEFAULT 0,
			data_source VARCHAR(32) NOT NULL DEFAULT 'default',
			notes LONGTEXT NULL,
			PRIMARY KEY  (id),
			KEY period (period),
			KEY cn8 (cn8),
			KEY category (category),
			KEY origin_country (origin_country)
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

	/**
	 * @param array<string,mixed> $row
	 */
	public static function create( array $row ) : int {
		global $wpdb;
		$cn       = preg_replace( '/[^0-9]/', '', (string) ( $row['cn8'] ?? '' ) );
		$category = sanitize_key( (string) ( $row['category'] ?? '' ) );
		if ( '' === $category && '' !== $cn ) {
			$category = CbamRegistry::category_for_cn( $cn );
		}
		$wpdb->insert(
			self::table_name(),
			array(
				'created_at'         => current_time( 'mysql' ),
				'imported_at'        => isset( $row['imported_at'] ) ? (string) $row['imported_at'] : current_time( 'mysql' ),
				'period'             => preg_match( '/^\d{4}-Q[1-4]$/', (string) ( $row['period'] ?? '' ) ) ? (string) $row['period'] : Settings::current_period(),
				'cn8'                => $cn,
				'category'           => $category,
				'origin_country'     => strtoupper( substr( preg_replace( '/[^A-Z]/', '', strtoupper( (string) ( $row['origin_country'] ?? '' ) ) ), 0, 2 ) ),
				'supplier'           => sanitize_text_field( (string) ( $row['supplier'] ?? '' ) ),
				'production_route'   => sanitize_text_field( (string) ( $row['production_route'] ?? '' ) ),
				'quantity'           => max( 0.0, (float) ( $row['quantity'] ?? 0 ) ),
				'unit'               => sanitize_key( (string) ( $row['unit'] ?? 't' ) ),
				'direct_emissions'   => max( 0.0, (float) ( $row['direct_emissions']   ?? 0 ) ),
				'indirect_emissions' => max( 0.0, (float) ( $row['indirect_emissions'] ?? 0 ) ),
				'emissions_verified' => ! empty( $row['emissions_verified'] ) ? 1 : 0,
				'data_source'        => in_array( (string) ( $row['data_source'] ?? '' ), array( 'default', 'estimate', 'verified' ), true ) ? (string) $row['data_source'] : 'default',
				'notes'              => wp_kses_post( (string) ( $row['notes'] ?? '' ) ),
			)
		);
		return (int) $wpdb->insert_id;
	}

	public static function delete( int $id ) : void {
		global $wpdb;
		$wpdb->delete( self::table_name(), array( 'id' => $id ), array( '%d' ) );
	}

	public static function get( int $id ) : ?array {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function for_period( string $period, int $limit = 5000 ) : array {
		global $wpdb;
		$table = self::table_name();
		$limit = max( 1, min( 50000, $limit ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE period = %s ORDER BY id ASC LIMIT %d", $period, $limit ), ARRAY_A );
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

	public static function count_for_period( string $period ) : int {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE period = %s", $period ) );
	}

	public static function unverified_count_for_period( string $period ) : int {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE period = %s AND emissions_verified = 0", $period ) );
	}
}
