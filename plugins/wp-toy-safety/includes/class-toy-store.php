<?php
/**
 * Toy register.
 *
 * @package EuroComply\ToySafety
 */

declare( strict_types = 1 );

namespace EuroComply\ToySafety;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ToyStore {

	private const DB_VERSION_OPTION = 'eurocomply_toy_toys_db_version';
	private const DB_VERSION        = '0.2.0';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_toy_toys';
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
			model VARCHAR(96) NOT NULL DEFAULT '',
			gtin VARCHAR(32) NOT NULL DEFAULT '',
			batch VARCHAR(64) NOT NULL DEFAULT '',
			age_range VARCHAR(16) NOT NULL DEFAULT '36-72',
			under_36 TINYINT(1) NOT NULL DEFAULT 0,
			category VARCHAR(48) NOT NULL DEFAULT '',
			materials LONGTEXT NULL,
			warnings LONGTEXT NULL,
			origin_country CHAR(2) NOT NULL DEFAULT '',
			ce_marked TINYINT(1) NOT NULL DEFAULT 0,
			doc_url VARCHAR(255) NOT NULL DEFAULT '',
			dpp_url VARCHAR(255) NOT NULL DEFAULT '',
			image_url VARCHAR(255) NOT NULL DEFAULT '',
			linked_product_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			gpsr_synced_at DATETIME NULL,
			status VARCHAR(24) NOT NULL DEFAULT 'on_market',
			notes LONGTEXT NULL,
			PRIMARY KEY  (id),
			KEY gtin (gtin),
			KEY status (status),
			KEY linked_product_id (linked_product_id)
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
				'name'              => sanitize_text_field( (string) ( $row['name'] ?? '' ) ),
				'model'             => sanitize_text_field( (string) ( $row['model'] ?? '' ) ),
				'gtin'              => preg_replace( '/[^0-9]/', '', (string) ( $row['gtin'] ?? '' ) ),
				'batch'             => sanitize_text_field( (string) ( $row['batch'] ?? '' ) ),
				'age_range'         => array_key_exists( (string) ( $row['age_range'] ?? '' ), Settings::age_ranges() ) ? (string) $row['age_range'] : '36-72',
				'under_36'          => ! empty( $row['under_36'] ) ? 1 : 0,
				'category'          => sanitize_text_field( (string) ( $row['category'] ?? '' ) ),
				'materials'         => wp_kses_post( (string) ( $row['materials'] ?? '' ) ),
				'warnings'          => wp_kses_post( (string) ( $row['warnings'] ?? '' ) ),
				'origin_country'    => strtoupper( substr( (string) ( $row['origin_country'] ?? '' ), 0, 2 ) ),
				'ce_marked'         => ! empty( $row['ce_marked'] ) ? 1 : 0,
				'doc_url'           => esc_url_raw( (string) ( $row['doc_url'] ?? '' ) ),
				'dpp_url'           => esc_url_raw( (string) ( $row['dpp_url'] ?? '' ) ),
				'image_url'         => esc_url_raw( (string) ( $row['image_url'] ?? '' ) ),
				'linked_product_id' => max( 0, (int) ( $row['linked_product_id'] ?? 0 ) ),
				'status'            => in_array( (string) ( $row['status'] ?? 'on_market' ), array( 'draft', 'on_market', 'recalled', 'withdrawn' ), true ) ? (string) $row['status'] : 'on_market',
				'notes'             => wp_kses_post( (string) ( $row['notes'] ?? '' ) ),
			)
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * Update an existing toy. Caller is responsible for whitelisting fields.
	 *
	 * @param array<string,mixed> $row
	 */
	public static function update( int $id, array $row ) : bool {
		if ( $id <= 0 || empty( $row ) ) {
			return false;
		}
		global $wpdb;
		$allowed = array(
			'name', 'model', 'gtin', 'batch', 'age_range', 'under_36', 'category',
			'materials', 'warnings', 'origin_country', 'ce_marked', 'doc_url',
			'dpp_url', 'image_url', 'linked_product_id', 'gpsr_synced_at',
			'status', 'notes',
		);
		$data = array_intersect_key( $row, array_flip( $allowed ) );
		if ( empty( $data ) ) {
			return false;
		}
		$ok = $wpdb->update( self::table_name(), $data, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $ok;
	}

	/**
	 * Find a toy by linked WC product id (>0) or normalised GTIN.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function find_by_product_or_gtin( int $product_id, string $gtin ) : ?array {
		global $wpdb;
		$table = self::table_name();
		if ( $product_id > 0 ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE linked_product_id = %d ORDER BY id DESC LIMIT 1", $product_id ), ARRAY_A );
			if ( $row ) {
				return (array) $row;
			}
		}
		$gtin = preg_replace( '/[^0-9]/', '', $gtin );
		if ( '' !== $gtin ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE gtin = %s ORDER BY id DESC LIMIT 1", $gtin ), ARRAY_A );
			if ( $row ) {
				return (array) $row;
			}
		}
		return null;
	}

	public static function delete( int $id ) : void {
		global $wpdb;
		$wpdb->delete( self::table_name(), array( 'id' => $id ), array( '%d' ) );
	}

	public static function all() : array {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results( "SELECT * FROM {$table} ORDER BY name ASC", ARRAY_A );
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

	public static function count_under_36() : int {
		global $wpdb;
		$table = self::table_name();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE under_36 = 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function count_no_ce() : int {
		global $wpdb;
		$table = self::table_name();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE ce_marked = 0 AND status = 'on_market'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function count_recalled() : int {
		global $wpdb;
		$table = self::table_name();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'recalled'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
