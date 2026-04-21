<?php
/**
 * Verification audit log store.
 *
 * Every gate encounter (pass / block) is recorded with a hashed IP, a
 * declared date of birth (normalised to a year), the verification method,
 * and the computed age — so site operators can demonstrate to regulators
 * (DE KJM, FR ARCOM) that a verification system was in place and working.
 *
 * Raw IPs and full DOB strings are never stored: only a SHA-256(IP|wp_salt)
 * hash and the declared birth year.
 *
 * @package EuroComply\AgeVerification
 */

declare( strict_types = 1 );

namespace EuroComply\AgeVerification;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class VerificationStore {

	public const DB_VERSION_OPTION = 'eurocomply_av_db_version';
	public const DB_VERSION        = '1.0.0';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_av_verifications';
	}

	public static function install() : void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			attempted_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			ip_hash VARCHAR(64) NOT NULL DEFAULT '',
			country VARCHAR(2) NOT NULL DEFAULT '',
			method VARCHAR(32) NOT NULL DEFAULT 'dob',
			declared_year SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			computed_age SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			required_age SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			passed TINYINT(1) NOT NULL DEFAULT 0,
			context VARCHAR(32) NOT NULL DEFAULT 'site',
			context_ref VARCHAR(191) NOT NULL DEFAULT '',
			session_token VARCHAR(64) NOT NULL DEFAULT '',
			user_agent VARCHAR(191) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY attempted_at (attempted_at),
			KEY passed (passed),
			KEY ip_hash (ip_hash),
			KEY context (context)
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
	public static function record( array $data ) : int {
		global $wpdb;

		$row = array(
			'attempted_at'  => current_time( 'mysql' ),
			'user_id'       => isset( $data['user_id'] ) ? (int) $data['user_id'] : 0,
			'ip_hash'       => isset( $data['ip_hash'] ) ? (string) $data['ip_hash'] : '',
			'country'       => isset( $data['country'] ) ? (string) $data['country'] : '',
			'method'        => isset( $data['method'] ) ? (string) $data['method'] : 'dob',
			'declared_year' => isset( $data['declared_year'] ) ? (int) $data['declared_year'] : 0,
			'computed_age'  => isset( $data['computed_age'] ) ? (int) $data['computed_age'] : 0,
			'required_age'  => isset( $data['required_age'] ) ? (int) $data['required_age'] : 0,
			'passed'        => ! empty( $data['passed'] ) ? 1 : 0,
			'context'       => isset( $data['context'] ) ? (string) $data['context'] : 'site',
			'context_ref'   => isset( $data['context_ref'] ) ? (string) $data['context_ref'] : '',
			'session_token' => isset( $data['session_token'] ) ? (string) $data['session_token'] : '',
			'user_agent'    => isset( $data['user_agent'] ) ? substr( (string) $data['user_agent'], 0, 190 ) : '',
		);

		$ok = $wpdb->insert( self::table_name(), $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( false === $ok ) {
			return 0;
		}
		return (int) $wpdb->insert_id;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function recent( int $limit = 100 ) : array {
		global $wpdb;
		$limit = max( 1, min( 5000, $limit ) );
		$table = self::table_name();
		$sql   = $wpdb->prepare( "SELECT * FROM {$table} ORDER BY attempted_at DESC, id DESC LIMIT %d", $limit ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows  = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return is_array( $rows ) ? $rows : array();
	}

	public static function count_since( int $since_ts, ?bool $passed = null ) : int {
		global $wpdb;
		$table = self::table_name();
		$sql   = "SELECT COUNT(*) FROM {$table} WHERE attempted_at >= %s";
		$args  = array( gmdate( 'Y-m-d H:i:s', $since_ts ) );
		if ( null !== $passed ) {
			$sql   .= ' AND passed = %d';
			$args[] = $passed ? 1 : 0;
		}
		$sql = $wpdb->prepare( $sql, $args ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	public static function count_ip_failed_recent( string $ip_hash, int $minutes = 60 ) : int {
		global $wpdb;
		if ( '' === $ip_hash ) {
			return 0;
		}
		$table = self::table_name();
		$since = gmdate( 'Y-m-d H:i:s', time() - ( $minutes * 60 ) );
		$sql   = $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE ip_hash = %s AND passed = 0 AND attempted_at >= %s", $ip_hash, $since ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	public static function ip_hash( string $ip ) : string {
		if ( '' === $ip ) {
			return '';
		}
		return hash( 'sha256', $ip . '|' . wp_salt( 'nonce' ) );
	}
}
