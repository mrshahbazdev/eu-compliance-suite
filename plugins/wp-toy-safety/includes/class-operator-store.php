<?php
/**
 * Economic-operator chain.
 *
 * @package EuroComply\ToySafety
 */

declare( strict_types = 1 );

namespace EuroComply\ToySafety;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class OperatorStore {

	private const DB_VERSION_OPTION = 'eurocomply_toy_operators_db_version';
	private const DB_VERSION        = '0.1.0';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_toy_operators';
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
			toy_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			role VARCHAR(24) NOT NULL DEFAULT 'manufacturer',
			name VARCHAR(255) NOT NULL DEFAULT '',
			country CHAR(2) NOT NULL DEFAULT '',
			address VARCHAR(255) NOT NULL DEFAULT '',
			email VARCHAR(255) NOT NULL DEFAULT '',
			eori VARCHAR(24) NOT NULL DEFAULT '',
			vat VARCHAR(24) NOT NULL DEFAULT '',
			notes LONGTEXT NULL,
			PRIMARY KEY  (id),
			KEY toy_id (toy_id),
			KEY role (role)
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
				'created_at' => current_time( 'mysql' ),
				'toy_id'     => (int) ( $row['toy_id'] ?? 0 ),
				'role'       => array_key_exists( (string) ( $row['role'] ?? '' ), Settings::roles() ) ? (string) $row['role'] : 'manufacturer',
				'name'       => sanitize_text_field( (string) ( $row['name'] ?? '' ) ),
				'country'    => strtoupper( substr( (string) ( $row['country'] ?? '' ), 0, 2 ) ),
				'address'    => sanitize_text_field( (string) ( $row['address'] ?? '' ) ),
				'email'      => is_email( sanitize_email( (string) ( $row['email'] ?? '' ) ) ) ? sanitize_email( (string) ( $row['email'] ?? '' ) ) : '',
				'eori'       => strtoupper( substr( preg_replace( '/[^A-Za-z0-9]/', '', (string) ( $row['eori'] ?? '' ) ), 0, 24 ) ),
				'vat'        => strtoupper( substr( preg_replace( '/[^A-Za-z0-9]/', '', (string) ( $row['vat'] ?? '' ) ), 0, 24 ) ),
				'notes'      => wp_kses_post( (string) ( $row['notes'] ?? '' ) ),
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
		return (array) $wpdb->get_results( "SELECT * FROM {$table} ORDER BY toy_id ASC, role ASC", ARRAY_A );
	}

	public static function for_toy( int $toy_id ) : array {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE toy_id = %d ORDER BY role ASC", $toy_id ), ARRAY_A );
	}

	public static function count_total() : int {
		global $wpdb;
		$table = self::table_name();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
