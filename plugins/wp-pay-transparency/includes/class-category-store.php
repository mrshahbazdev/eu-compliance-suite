<?php
/**
 * Pay categories (Art. 11): groupings of workers performing equal work or
 * work of equal value (skills, effort, responsibility, working conditions).
 *
 * @package EuroComply\PayTransparency
 */

declare( strict_types = 1 );

namespace EuroComply\PayTransparency;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CategoryStore {

	private const DB_VERSION_OPTION = 'eurocomply_pt_categories_db_version';
	private const DB_VERSION        = '0.1.0';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_pt_categories';
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
			slug VARCHAR(64) NOT NULL DEFAULT '',
			name VARCHAR(255) NOT NULL DEFAULT '',
			description LONGTEXT NULL,
			skills_level TINYINT UNSIGNED NOT NULL DEFAULT 0,
			effort_level TINYINT UNSIGNED NOT NULL DEFAULT 0,
			responsibility_level TINYINT UNSIGNED NOT NULL DEFAULT 0,
			working_conditions_level TINYINT UNSIGNED NOT NULL DEFAULT 0,
			pay_min DECIMAL(12,2) NOT NULL DEFAULT 0,
			pay_max DECIMAL(12,2) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug)
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
	public static function upsert( array $row ) : int {
		global $wpdb;
		$slug = sanitize_key( (string) ( $row['slug'] ?? '' ) );
		if ( '' === $slug ) {
			return 0;
		}
		$payload = array(
			'created_at'                => current_time( 'mysql' ),
			'slug'                      => $slug,
			'name'                      => sanitize_text_field( (string) ( $row['name'] ?? $slug ) ),
			'description'               => wp_kses_post( (string) ( $row['description'] ?? '' ) ),
			'skills_level'              => max( 0, min( 10, (int) ( $row['skills_level'] ?? 0 ) ) ),
			'effort_level'              => max( 0, min( 10, (int) ( $row['effort_level'] ?? 0 ) ) ),
			'responsibility_level'      => max( 0, min( 10, (int) ( $row['responsibility_level'] ?? 0 ) ) ),
			'working_conditions_level'  => max( 0, min( 10, (int) ( $row['working_conditions_level'] ?? 0 ) ) ),
			'pay_min'                   => max( 0.0, (float) ( $row['pay_min'] ?? 0 ) ),
			'pay_max'                   => max( 0.0, (float) ( $row['pay_max'] ?? 0 ) ),
		);

		$existing = self::get_by_slug( $slug );
		if ( $existing ) {
			$wpdb->update( self::table_name(), $payload, array( 'id' => (int) $existing['id'] ) );
			return (int) $existing['id'];
		}
		$wpdb->insert( self::table_name(), $payload );
		return (int) $wpdb->insert_id;
	}

	public static function delete( int $id ) : void {
		global $wpdb;
		$wpdb->delete( self::table_name(), array( 'id' => $id ), array( '%d' ) );
	}

	public static function get_by_slug( string $slug ) : ?array {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE slug = %s", $slug ), ARRAY_A );
		return is_array( $row ) ? $row : null;
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
	public static function all() : array {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results( "SELECT * FROM {$table} ORDER BY name ASC", ARRAY_A );
	}

	public static function count_total() : int {
		global $wpdb;
		$table = self::table_name();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
