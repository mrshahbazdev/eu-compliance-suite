<?php
/**
 * Art. 7 worker right-to-information requests.
 *
 * @package EuroComply\PayTransparency
 */

declare( strict_types = 1 );

namespace EuroComply\PayTransparency;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RequestStore {

	private const DB_VERSION_OPTION = 'eurocomply_pt_requests_db_version';
	private const DB_VERSION        = '0.1.0';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_pt_requests';
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
			updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			follow_up_token_hash CHAR(64) NOT NULL DEFAULT '',
			employee_ref VARCHAR(255) NOT NULL DEFAULT '',
			contact_email VARCHAR(255) NOT NULL DEFAULT '',
			category_slug VARCHAR(64) NOT NULL DEFAULT '',
			scope VARCHAR(32) NOT NULL DEFAULT 'pay_levels',
			notes LONGTEXT NULL,
			ip_hash CHAR(64) NOT NULL DEFAULT '',
			status VARCHAR(32) NOT NULL DEFAULT 'received',
			responded_at DATETIME NULL DEFAULT NULL,
			response_body LONGTEXT NULL,
			PRIMARY KEY  (id),
			KEY follow_up_token_hash (follow_up_token_hash),
			KEY status (status),
			KEY created_at (created_at)
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

	public static function generate_token() : string {
		return bin2hex( random_bytes( 16 ) );
	}

	public static function hash_token( string $token ) : string {
		return hash_hmac( 'sha256', $token, wp_salt( 'auth' ) );
	}

	public static function hash_ip( string $ip ) : string {
		return hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) );
	}

	/**
	 * @param array<string,mixed> $row
	 * @return array{id:int,token:string}
	 */
	public static function create( array $row ) : array {
		global $wpdb;
		$token = self::generate_token();
		$wpdb->insert(
			self::table_name(),
			array(
				'created_at'           => current_time( 'mysql' ),
				'updated_at'           => current_time( 'mysql' ),
				'follow_up_token_hash' => self::hash_token( $token ),
				'employee_ref'         => sanitize_text_field( (string) ( $row['employee_ref'] ?? '' ) ),
				'contact_email'        => sanitize_email( (string) ( $row['contact_email'] ?? '' ) ),
				'category_slug'        => sanitize_key( (string) ( $row['category_slug'] ?? '' ) ),
				'scope'                => sanitize_key( (string) ( $row['scope'] ?? 'pay_levels' ) ),
				'notes'                => wp_kses_post( (string) ( $row['notes'] ?? '' ) ),
				'ip_hash'              => self::hash_ip( (string) ( $row['ip'] ?? '' ) ),
				'status'               => 'received',
			)
		);
		return array(
			'id'    => (int) $wpdb->insert_id,
			'token' => $token,
		);
	}

	public static function find_by_token( string $token ) : ?array {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE follow_up_token_hash = %s", self::hash_token( $token ) ), ARRAY_A );
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
	 * @param array<string,mixed> $changes
	 */
	public static function update( int $id, array $changes ) : void {
		global $wpdb;
		$changes['updated_at'] = current_time( 'mysql' );
		$wpdb->update( self::table_name(), $changes, array( 'id' => $id ) );
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

	public static function count_open() : int {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status NOT IN ('responded','rejected')" );
	}

	public static function count_overdue( int $days ) : int {
		global $wpdb;
		$table = self::table_name();
		$days  = max( 1, min( 365, $days ) );
		$cut   = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status NOT IN ('responded','rejected') AND created_at < %s", $cut ) );
	}
}
