<?php
/**
 * PSU consent register (Art. 10 RTS — 90-day re-authentication).
 *
 * Stores tokens as HMAC-SHA-256(token, wp_salt('auth')) — raw token never persisted.
 *
 * @package EuroComply\PSD2
 */

declare( strict_types = 1 );

namespace EuroComply\PSD2;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ConsentStore {

	private const DB_VERSION_OPTION = 'eurocomply_psd2_consents_db_version';
	private const DB_VERSION        = '0.1.0';
	private const DEFAULT_TTL_DAYS  = 90;

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_psd2_consents';
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
			expires_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			subject VARCHAR(64) NOT NULL DEFAULT '',
			tpp_id VARCHAR(64) NOT NULL DEFAULT '',
			scope VARCHAR(255) NOT NULL DEFAULT '',
			token_hash CHAR(64) NOT NULL DEFAULT '',
			revoked TINYINT UNSIGNED NOT NULL DEFAULT 0,
			revoked_at DATETIME NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY subject (subject),
			KEY tpp_id (tpp_id),
			KEY token_hash (token_hash)
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

	public static function hash_token( string $token ) : string {
		return hash_hmac( 'sha256', $token, wp_salt( 'auth' ) );
	}

	public static function create( array $row ) : int {
		global $wpdb;
		$ttl_days   = isset( $row['ttl_days'] ) ? max( 1, min( 180, (int) $row['ttl_days'] ) ) : self::DEFAULT_TTL_DAYS;
		$created    = current_time( 'mysql' );
		$expires    = gmdate( 'Y-m-d H:i:s', strtotime( $created . ' +' . $ttl_days . ' days' ) );
		$token_raw  = (string) ( $row['token_raw'] ?? wp_generate_password( 32, false ) );
		$wpdb->insert(
			self::table_name(),
			array(
				'created_at' => $created,
				'expires_at' => $expires,
				'subject'    => sanitize_text_field( (string) ( $row['subject'] ?? '' ) ),
				'tpp_id'     => sanitize_text_field( (string) ( $row['tpp_id']  ?? '' ) ),
				'scope'      => sanitize_text_field( (string) ( $row['scope']   ?? '' ) ),
				'token_hash' => self::hash_token( $token_raw ),
				'revoked'    => 0,
			)
		);
		return (int) $wpdb->insert_id;
	}

	public static function revoke( int $id ) : void {
		global $wpdb;
		$wpdb->update(
			self::table_name(),
			array( 'revoked' => 1, 'revoked_at' => current_time( 'mysql' ) ),
			array( 'id' => $id ),
			array( '%d', '%s' ),
			array( '%d' )
		);
	}

	public static function delete( int $id ) : void {
		global $wpdb;
		$wpdb->delete( self::table_name(), array( 'id' => $id ), array( '%d' ) );
	}

	public static function recent( int $limit = 100 ) : array {
		global $wpdb;
		$table = self::table_name();
		$limit = max( 1, min( 5000, $limit ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit ), ARRAY_A );
	}

	public static function count_active() : int {
		global $wpdb;
		$table = self::table_name();
		$now   = current_time( 'mysql' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE revoked = 0 AND expires_at >= %s", $now ) );
	}

	public static function count_expired() : int {
		global $wpdb;
		$table = self::table_name();
		$now   = current_time( 'mysql' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE revoked = 0 AND expires_at < %s", $now ) );
	}

	public static function count_total() : int {
		global $wpdb;
		$table = self::table_name();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
