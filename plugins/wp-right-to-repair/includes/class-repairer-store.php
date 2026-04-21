<?php
/**
 * Authorised-repairer directory.
 *
 * Table: wp_eurocomply_r2r_repairers
 *
 * FR Code de la consommation L.111-4: traders must disclose authorised repairers
 * for covered products. The directory is also useful for the upcoming EU
 * Right-to-Repair platform contemplated by Directive (EU) 2024/1799.
 *
 * @package EuroComply\R2R
 */

declare( strict_types = 1 );

namespace EuroComply\R2R;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RepairerStore {

	public const DB_VERSION_OPTION = 'eurocomply_r2r_repairers_db_version';
	public const DB_VERSION        = '1.0.0';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_r2r_repairers';
	}

	public static function install() : void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			name VARCHAR(191) NOT NULL DEFAULT '',
			product_category VARCHAR(32) NOT NULL DEFAULT 'not_applicable',
			country VARCHAR(2) NOT NULL DEFAULT '',
			city VARCHAR(191) NOT NULL DEFAULT '',
			address VARCHAR(255) NOT NULL DEFAULT '',
			website VARCHAR(255) NOT NULL DEFAULT '',
			email VARCHAR(191) NOT NULL DEFAULT '',
			phone VARCHAR(64) NOT NULL DEFAULT '',
			certification VARCHAR(128) NOT NULL DEFAULT '',
			notes LONGTEXT NULL,
			PRIMARY KEY  (id),
			KEY product_category (product_category),
			KEY country (country)
		) {$charset};";

		dbDelta( $sql );
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
	}

	public static function maybe_upgrade() : void {
		if ( get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public static function create( array $data ) : int {
		global $wpdb;
		$row = self::normalise( $data );
		$row['created_at'] = current_time( 'mysql' );
		$ok = $wpdb->insert( self::table_name(), $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return false === $ok ? 0 : (int) $wpdb->insert_id;
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public static function update( int $id, array $data ) : bool {
		global $wpdb;
		if ( $id <= 0 ) {
			return false;
		}
		$row = self::normalise( $data );
		$result = $wpdb->update( self::table_name(), $row, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $result;
	}

	public static function delete( int $id ) : bool {
		global $wpdb;
		if ( $id <= 0 ) {
			return false;
		}
		return false !== $wpdb->delete( self::table_name(), array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public static function get( int $id ) : ?array {
		global $wpdb;
		$table = self::table_name();
		$sql   = $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row   = $wpdb->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return is_array( $row ) ? $row : null;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function all( string $category = '', string $country = '', int $limit = 500 ) : array {
		global $wpdb;
		$limit = max( 1, min( 5000, $limit ) );
		$table = self::table_name();
		$where = 'WHERE 1=1';
		$args  = array();
		if ( '' !== $category ) {
			$where .= ' AND product_category = %s';
			$args[] = $category;
		}
		if ( '' !== $country ) {
			$where .= ' AND country = %s';
			$args[] = strtoupper( $country );
		}
		$args[] = $limit;
		$sql    = $wpdb->prepare( "SELECT * FROM {$table} {$where} ORDER BY country ASC, city ASC, name ASC LIMIT %d", $args ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows   = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @param array<string,mixed> $data
	 * @return array<string,mixed>
	 */
	private static function normalise( array $data ) : array {
		$cc = isset( $data['country'] ) ? strtoupper( (string) $data['country'] ) : '';
		if ( ! preg_match( '/^[A-Z]{2}$/', $cc ) ) {
			$cc = '';
		}
		$cat = isset( $data['product_category'] ) ? sanitize_key( (string) $data['product_category'] ) : 'not_applicable';
		if ( ! isset( Settings::product_categories()[ $cat ] ) ) {
			$cat = 'not_applicable';
		}
		return array(
			'name'             => substr( sanitize_text_field( (string) ( $data['name'] ?? '' ) ), 0, 191 ),
			'product_category' => $cat,
			'country'          => $cc,
			'city'             => substr( sanitize_text_field( (string) ( $data['city'] ?? '' ) ), 0, 191 ),
			'address'          => substr( sanitize_text_field( (string) ( $data['address'] ?? '' ) ), 0, 255 ),
			'website'          => esc_url_raw( (string) ( $data['website'] ?? '' ) ),
			'email'            => ( $e = sanitize_email( (string) ( $data['email'] ?? '' ) ) ) && is_email( $e ) ? $e : '',
			'phone'            => substr( sanitize_text_field( (string) ( $data['phone'] ?? '' ) ), 0, 64 ),
			'certification'    => substr( sanitize_text_field( (string) ( $data['certification'] ?? '' ) ), 0, 128 ),
			'notes'            => sanitize_textarea_field( (string) ( $data['notes'] ?? '' ) ),
		);
	}
}
