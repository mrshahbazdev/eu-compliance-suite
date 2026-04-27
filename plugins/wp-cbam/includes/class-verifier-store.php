<?php
/**
 * Accredited verifier directory.
 *
 * @package EuroComply\CBAM
 */

declare( strict_types = 1 );

namespace EuroComply\CBAM;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class VerifierStore {

	private const DB_VERSION_OPTION = 'eurocomply_cbam_verifiers_db_version';
	private const DB_VERSION        = '0.1.0';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_cbam_verifiers';
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
			country CHAR(2) NOT NULL DEFAULT '',
			name VARCHAR(255) NOT NULL DEFAULT '',
			accreditation_id VARCHAR(64) NOT NULL DEFAULT '',
			scope VARCHAR(255) NOT NULL DEFAULT '',
			contact_email VARCHAR(255) NOT NULL DEFAULT '',
			website VARCHAR(255) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY country (country)
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
		$wpdb->insert(
			self::table_name(),
			array(
				'created_at'       => current_time( 'mysql' ),
				'country'          => strtoupper( substr( preg_replace( '/[^A-Z]/', '', strtoupper( (string) ( $row['country'] ?? '' ) ) ), 0, 2 ) ),
				'name'             => sanitize_text_field( (string) ( $row['name'] ?? '' ) ),
				'accreditation_id' => sanitize_text_field( (string) ( $row['accreditation_id'] ?? '' ) ),
				'scope'            => sanitize_text_field( (string) ( $row['scope'] ?? '' ) ),
				'contact_email'    => sanitize_email( (string) ( $row['contact_email'] ?? '' ) ),
				'website'          => esc_url_raw( (string) ( $row['website'] ?? '' ) ),
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
		return (array) $wpdb->get_results( "SELECT * FROM {$table} ORDER BY country ASC, name ASC", ARRAY_A );
	}

	public static function count_total() : int {
		global $wpdb;
		$table = self::table_name();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
