<?php
/**
 * Statement-of-Reasons store (Article 17) for EuroComply DSA.
 *
 * Every restrictive moderation action (removal, demotion, account suspension, etc.)
 * MUST be accompanied by a structured statement of reasons per DSA Art. 17.
 *
 * @package EuroComply\DSA
 */

declare( strict_types = 1 );

namespace EuroComply\DSA;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class StatementStore {

	public const DB_VERSION_OPTION = 'eurocomply_dsa_statements_db_version';
	public const DB_VERSION        = '1.0.0';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_dsa_statements';
	}

	public static function install() : void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			issued_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			notice_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			target_post_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			target_user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			target_url TEXT NULL,
			decision_ground VARCHAR(64) NOT NULL DEFAULT 'tos',
			restriction_type VARCHAR(64) NOT NULL DEFAULT 'removed',
			restriction_scope VARCHAR(64) NOT NULL DEFAULT 'content',
			category VARCHAR(64) NOT NULL DEFAULT 'other',
			legal_reference TEXT NULL,
			tos_reference TEXT NULL,
			facts_summary LONGTEXT NULL,
			automated_detection TINYINT(1) NOT NULL DEFAULT 0,
			automated_decision TINYINT(1) NOT NULL DEFAULT 0,
			redress_info TEXT NULL,
			issued_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
			submitted_to_db TINYINT(1) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY issued_at (issued_at),
			KEY notice_id (notice_id),
			KEY target_post_id (target_post_id),
			KEY category (category),
			KEY restriction_type (restriction_type)
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
			'issued_at'           => current_time( 'mysql' ),
			'notice_id'           => isset( $data['notice_id'] ) ? (int) $data['notice_id'] : 0,
			'target_post_id'      => isset( $data['target_post_id'] ) ? (int) $data['target_post_id'] : 0,
			'target_user_id'      => isset( $data['target_user_id'] ) ? (int) $data['target_user_id'] : 0,
			'target_url'          => isset( $data['target_url'] ) ? (string) $data['target_url'] : '',
			'decision_ground'     => isset( $data['decision_ground'] ) ? (string) $data['decision_ground'] : 'tos',
			'restriction_type'    => isset( $data['restriction_type'] ) ? (string) $data['restriction_type'] : 'removed',
			'restriction_scope'   => isset( $data['restriction_scope'] ) ? (string) $data['restriction_scope'] : 'content',
			'category'            => isset( $data['category'] ) ? (string) $data['category'] : 'other',
			'legal_reference'     => isset( $data['legal_reference'] ) ? (string) $data['legal_reference'] : '',
			'tos_reference'       => isset( $data['tos_reference'] ) ? (string) $data['tos_reference'] : '',
			'facts_summary'       => isset( $data['facts_summary'] ) ? (string) $data['facts_summary'] : '',
			'automated_detection' => ! empty( $data['automated_detection'] ) ? 1 : 0,
			'automated_decision'  => ! empty( $data['automated_decision'] ) ? 1 : 0,
			'redress_info'        => isset( $data['redress_info'] ) ? (string) $data['redress_info'] : '',
			'issued_by'           => isset( $data['issued_by'] ) ? (int) $data['issued_by'] : get_current_user_id(),
			'submitted_to_db'     => ! empty( $data['submitted_to_db'] ) ? 1 : 0,
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
	public static function recent( int $limit = 50 ) : array {
		global $wpdb;
		$limit = max( 1, min( 5000, $limit ) );
		$table = self::table_name();
		$sql   = $wpdb->prepare( "SELECT * FROM {$table} ORDER BY issued_at DESC, id DESC LIMIT %d", $limit ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows  = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function for_notice( int $notice_id ) : array {
		global $wpdb;
		if ( $notice_id <= 0 ) {
			return array();
		}
		$table = self::table_name();
		$sql   = $wpdb->prepare( "SELECT * FROM {$table} WHERE notice_id = %d ORDER BY issued_at DESC", $notice_id ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows  = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return is_array( $rows ) ? $rows : array();
	}

	public static function count_since( int $since_ts ) : int {
		global $wpdb;
		$table = self::table_name();
		$sql   = $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE issued_at >= %s", gmdate( 'Y-m-d H:i:s', $since_ts ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * @return array<string,int>
	 */
	public static function restriction_counts( ?int $since_ts = null, ?int $until_ts = null ) : array {
		global $wpdb;
		$table = self::table_name();
		$where = '1=1';
		$args  = array();
		if ( $since_ts ) {
			$where .= ' AND issued_at >= %s';
			$args[] = gmdate( 'Y-m-d H:i:s', $since_ts );
		}
		if ( $until_ts ) {
			$where .= ' AND issued_at <= %s';
			$args[] = gmdate( 'Y-m-d H:i:s', $until_ts );
		}
		$sql  = "SELECT restriction_type, COUNT(*) AS n FROM {$table} WHERE {$where} GROUP BY restriction_type";
		$sql  = $args ? $wpdb->prepare( $sql, $args ) : $sql; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		$out  = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$out[ (string) $row['restriction_type'] ] = (int) $row['n'];
			}
		}
		return $out;
	}

	public static function automated_count( ?int $since_ts = null, ?int $until_ts = null ) : int {
		global $wpdb;
		$table = self::table_name();
		$where = 'automated_decision = 1';
		$args  = array();
		if ( $since_ts ) {
			$where .= ' AND issued_at >= %s';
			$args[] = gmdate( 'Y-m-d H:i:s', $since_ts );
		}
		if ( $until_ts ) {
			$where .= ' AND issued_at <= %s';
			$args[] = gmdate( 'Y-m-d H:i:s', $until_ts );
		}
		$sql = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
		$sql = $args ? $wpdb->prepare( $sql, $args ) : $sql; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
	}
}
