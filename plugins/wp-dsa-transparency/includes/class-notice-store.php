<?php
/**
 * Notice store (Article 16 notice-and-action log) for EuroComply DSA.
 *
 * @package EuroComply\DSA
 */

declare( strict_types = 1 );

namespace EuroComply\DSA;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NoticeStore {

	public const DB_VERSION_OPTION = 'eurocomply_dsa_notices_db_version';
	public const DB_VERSION        = '1.0.0';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_dsa_notices';
	}

	public static function install() : void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			submitted_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			reporter_name VARCHAR(191) NOT NULL DEFAULT '',
			reporter_email VARCHAR(191) NOT NULL DEFAULT '',
			reporter_role VARCHAR(32) NOT NULL DEFAULT 'user',
			target_url TEXT NULL,
			target_post_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			category VARCHAR(64) NOT NULL DEFAULT 'other',
			legal_basis VARCHAR(191) NOT NULL DEFAULT '',
			description LONGTEXT NULL,
			evidence LONGTEXT NULL,
			status VARCHAR(32) NOT NULL DEFAULT 'received',
			action_taken VARCHAR(191) NOT NULL DEFAULT '',
			closed_at DATETIME NULL,
			ip_hash VARCHAR(64) NOT NULL DEFAULT '',
			statement_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY submitted_at (submitted_at),
			KEY status (status),
			KEY target_post_id (target_post_id),
			KEY category (category)
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
	 *
	 * @return int Inserted ID or 0 on failure.
	 */
	public static function record( array $data ) : int {
		global $wpdb;

		$row = array(
			'submitted_at'   => current_time( 'mysql' ),
			'reporter_name'  => isset( $data['reporter_name'] ) ? (string) $data['reporter_name'] : '',
			'reporter_email' => isset( $data['reporter_email'] ) ? (string) $data['reporter_email'] : '',
			'reporter_role'  => isset( $data['reporter_role'] ) ? (string) $data['reporter_role'] : 'user',
			'target_url'     => isset( $data['target_url'] ) ? (string) $data['target_url'] : '',
			'target_post_id' => isset( $data['target_post_id'] ) ? (int) $data['target_post_id'] : 0,
			'category'       => isset( $data['category'] ) ? (string) $data['category'] : 'other',
			'legal_basis'    => isset( $data['legal_basis'] ) ? (string) $data['legal_basis'] : '',
			'description'    => isset( $data['description'] ) ? (string) $data['description'] : '',
			'evidence'       => isset( $data['evidence'] ) ? (string) $data['evidence'] : '',
			'status'         => isset( $data['status'] ) ? (string) $data['status'] : 'received',
			'action_taken'   => isset( $data['action_taken'] ) ? (string) $data['action_taken'] : '',
			'ip_hash'        => isset( $data['ip_hash'] ) ? (string) $data['ip_hash'] : '',
			'statement_id'   => isset( $data['statement_id'] ) ? (int) $data['statement_id'] : 0,
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
		if ( $id <= 0 ) {
			return false;
		}
		$allowed = array( 'status', 'action_taken', 'closed_at', 'statement_id' );
		$row     = array();
		foreach ( $allowed as $key ) {
			if ( array_key_exists( $key, $data ) ) {
				$row[ $key ] = $data[ $key ];
			}
		}
		if ( empty( $row ) ) {
			return false;
		}
		$ok = $wpdb->update( self::table_name(), $row, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $ok;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function recent( int $limit = 50 ) : array {
		global $wpdb;
		$limit = max( 1, min( 5000, $limit ) );
		$table = self::table_name();
		$sql   = $wpdb->prepare( "SELECT * FROM {$table} ORDER BY submitted_at DESC, id DESC LIMIT %d", $limit ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows  = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @return array<string,int>
	 */
	public static function status_counts() : array {
		global $wpdb;
		$table = self::table_name();
		$rows  = $wpdb->get_results( "SELECT status, COUNT(*) AS n FROM {$table} GROUP BY status", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out   = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$out[ (string) $row['status'] ] = (int) $row['n'];
			}
		}
		return $out;
	}

	/**
	 * @return array<string,int>
	 */
	public static function category_counts( ?int $since_ts = null, ?int $until_ts = null ) : array {
		global $wpdb;
		$table = self::table_name();
		$where = '1=1';
		$args  = array();
		if ( $since_ts ) {
			$where .= ' AND submitted_at >= %s';
			$args[] = gmdate( 'Y-m-d H:i:s', $since_ts );
		}
		if ( $until_ts ) {
			$where .= ' AND submitted_at <= %s';
			$args[] = gmdate( 'Y-m-d H:i:s', $until_ts );
		}
		$sql  = "SELECT category, COUNT(*) AS n FROM {$table} WHERE {$where} GROUP BY category";
		$sql  = $args ? $wpdb->prepare( $sql, $args ) : $sql; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		$out  = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$out[ (string) $row['category'] ] = (int) $row['n'];
			}
		}
		return $out;
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public static function get_by_id( int $id ) : ?array {
		global $wpdb;
		if ( $id <= 0 ) {
			return null;
		}
		$table = self::table_name();
		$sql   = $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row   = $wpdb->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return is_array( $row ) ? $row : null;
	}

	public static function count_since( int $since_ts ) : int {
		global $wpdb;
		$table = self::table_name();
		$sql   = $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE submitted_at >= %s", gmdate( 'Y-m-d H:i:s', $since_ts ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
}
