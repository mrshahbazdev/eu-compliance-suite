<?php
/**
 * DSAR request store.
 *
 * Table: wp_eurocomply_dsar_requests
 *
 * @package EuroComply\DSAR
 */

declare( strict_types = 1 );

namespace EuroComply\DSAR;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RequestStore {

	public const DB_VERSION_OPTION = 'eurocomply_dsar_db_version';
	public const DB_VERSION        = '1.0.0';

	public const TYPES = array(
		'access',
		'erase',
		'rectify',
		'portability',
		'object',
		'restrict',
	);

	public const STATUSES = array(
		'received',
		'verifying',
		'in_progress',
		'completed',
		'rejected',
		'cancelled',
		'expired',
	);

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_dsar_requests';
	}

	public static function install() : void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			submitted_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			deadline_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			completed_at DATETIME NULL DEFAULT NULL,
			request_type VARCHAR(32) NOT NULL DEFAULT 'access',
			status VARCHAR(32) NOT NULL DEFAULT 'received',
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			requester_email VARCHAR(191) NOT NULL DEFAULT '',
			requester_name VARCHAR(191) NOT NULL DEFAULT '',
			verified TINYINT(1) NOT NULL DEFAULT 0,
			verification_token VARCHAR(64) NOT NULL DEFAULT '',
			verification_expires DATETIME NULL DEFAULT NULL,
			ip_hash VARCHAR(64) NOT NULL DEFAULT '',
			details LONGTEXT NULL,
			handler_user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			export_path VARCHAR(255) NOT NULL DEFAULT '',
			admin_notes LONGTEXT NULL,
			PRIMARY KEY  (id),
			KEY submitted_at (submitted_at),
			KEY status (status),
			KEY deadline_at (deadline_at),
			KEY requester_email (requester_email),
			KEY user_id (user_id),
			KEY verification_token (verification_token)
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

		$now        = current_time( 'mysql' );
		$deadline   = isset( $data['deadline_at'] ) ? (string) $data['deadline_at'] : gmdate( 'Y-m-d H:i:s', time() + 30 * DAY_IN_SECONDS );
		$token      = isset( $data['verification_token'] ) ? (string) $data['verification_token'] : '';
		$token_exp  = isset( $data['verification_expires'] ) ? (string) $data['verification_expires'] : gmdate( 'Y-m-d H:i:s', time() + 48 * HOUR_IN_SECONDS );

		$row = array(
			'submitted_at'         => $now,
			'updated_at'           => $now,
			'deadline_at'          => $deadline,
			'request_type'         => isset( $data['request_type'] ) ? (string) $data['request_type'] : 'access',
			'status'               => isset( $data['status'] ) ? (string) $data['status'] : 'received',
			'user_id'              => isset( $data['user_id'] ) ? (int) $data['user_id'] : 0,
			'requester_email'      => isset( $data['requester_email'] ) ? (string) $data['requester_email'] : '',
			'requester_name'       => isset( $data['requester_name'] ) ? (string) $data['requester_name'] : '',
			'verified'             => ! empty( $data['verified'] ) ? 1 : 0,
			'verification_token'   => $token,
			'verification_expires' => $token_exp,
			'ip_hash'              => isset( $data['ip_hash'] ) ? (string) $data['ip_hash'] : '',
			'details'              => isset( $data['details'] ) ? (string) $data['details'] : '',
		);

		$ok = $wpdb->insert( self::table_name(), $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( false === $ok ) {
			return 0;
		}
		return (int) $wpdb->insert_id;
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public static function update( int $id, array $data ) : bool {
		global $wpdb;
		if ( $id <= 0 || empty( $data ) ) {
			return false;
		}

		$allowed = array(
			'status',
			'verified',
			'completed_at',
			'handler_user_id',
			'export_path',
			'admin_notes',
			'deadline_at',
		);
		$row = array( 'updated_at' => current_time( 'mysql' ) );
		foreach ( $allowed as $key ) {
			if ( array_key_exists( $key, $data ) ) {
				$row[ $key ] = $data[ $key ];
			}
		}

		$result = $wpdb->update( self::table_name(), $row, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $result;
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
	 * @return array<string,mixed>|null
	 */
	public static function get_by_token( string $token ) : ?array {
		global $wpdb;
		if ( '' === $token ) {
			return null;
		}
		$table = self::table_name();
		$sql   = $wpdb->prepare( "SELECT * FROM {$table} WHERE verification_token = %s LIMIT 1", $token ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row   = $wpdb->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return is_array( $row ) ? $row : null;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function recent( int $limit = 100, string $status_filter = '' ) : array {
		global $wpdb;
		$limit = max( 1, min( 5000, $limit ) );
		$table = self::table_name();
		if ( '' !== $status_filter ) {
			$sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE status = %s ORDER BY submitted_at DESC, id DESC LIMIT %d", $status_filter, $limit ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} else {
			$sql = $wpdb->prepare( "SELECT * FROM {$table} ORDER BY submitted_at DESC, id DESC LIMIT %d", $limit ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @return array<string,int>
	 */
	public static function status_counts() : array {
		global $wpdb;
		$table = self::table_name();
		$rows  = $wpdb->get_results( "SELECT status, COUNT(*) AS total FROM {$table} GROUP BY status", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out   = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$out[ (string) $row['status'] ] = (int) $row['total'];
			}
		}
		return $out;
	}

	public static function count_overdue() : int {
		global $wpdb;
		$table = self::table_name();
		$now   = current_time( 'mysql' );
		$sql   = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE deadline_at < %s AND status NOT IN ('completed','rejected','cancelled','expired')",
			$now
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	public static function count_ip_recent( string $ip_hash, int $minutes = 60 ) : int {
		global $wpdb;
		if ( '' === $ip_hash ) {
			return 0;
		}
		$table = self::table_name();
		$since = gmdate( 'Y-m-d H:i:s', time() - ( $minutes * 60 ) );
		$sql   = $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE ip_hash = %s AND submitted_at >= %s", $ip_hash, $since ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	public static function ip_hash( string $ip ) : string {
		if ( '' === $ip ) {
			return '';
		}
		return hash( 'sha256', $ip . '|' . wp_salt( 'nonce' ) );
	}

	public static function generate_token() : string {
		return wp_generate_password( 48, false, false );
	}
}
