<?php
/**
 * Security event store.
 *
 * Table: wp_eurocomply_nis2_events
 *
 * @package EuroComply\NIS2
 */

declare( strict_types = 1 );

namespace EuroComply\NIS2;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EventStore {

	public const DB_VERSION_OPTION = 'eurocomply_nis2_events_db_version';
	public const DB_VERSION        = '1.0.0';

	public const CATEGORIES = array(
		'auth',
		'admin',
		'plugin',
		'theme',
		'file',
		'config',
		'security',
	);

	public const SEVERITIES = array( 'info', 'low', 'medium', 'high', 'critical' );

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_nis2_events';
	}

	public static function install() : void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			occurred_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			category VARCHAR(32) NOT NULL DEFAULT 'admin',
			severity VARCHAR(16) NOT NULL DEFAULT 'info',
			action VARCHAR(64) NOT NULL DEFAULT '',
			actor_user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			actor_login VARCHAR(191) NOT NULL DEFAULT '',
			ip_hash VARCHAR(64) NOT NULL DEFAULT '',
			user_agent VARCHAR(191) NOT NULL DEFAULT '',
			target VARCHAR(191) NOT NULL DEFAULT '',
			details LONGTEXT NULL,
			PRIMARY KEY  (id),
			KEY occurred_at (occurred_at),
			KEY category (category),
			KEY severity (severity),
			KEY action (action),
			KEY actor_user_id (actor_user_id)
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
			'occurred_at'   => current_time( 'mysql' ),
			'category'      => isset( $data['category'] ) && in_array( (string) $data['category'], self::CATEGORIES, true ) ? (string) $data['category'] : 'admin',
			'severity'      => isset( $data['severity'] ) && in_array( (string) $data['severity'], self::SEVERITIES, true ) ? (string) $data['severity'] : 'info',
			'action'        => isset( $data['action'] ) ? substr( (string) $data['action'], 0, 64 ) : '',
			'actor_user_id' => isset( $data['actor_user_id'] ) ? (int) $data['actor_user_id'] : 0,
			'actor_login'   => isset( $data['actor_login'] ) ? substr( (string) $data['actor_login'], 0, 191 ) : '',
			'ip_hash'       => isset( $data['ip_hash'] ) ? (string) $data['ip_hash'] : '',
			'user_agent'    => isset( $data['user_agent'] ) ? substr( (string) $data['user_agent'], 0, 191 ) : '',
			'target'        => isset( $data['target'] ) ? substr( (string) $data['target'], 0, 191 ) : '',
			'details'       => isset( $data['details'] ) ? wp_json_encode( $data['details'] ) : '',
		);
		$ok = $wpdb->insert( self::table_name(), $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return false === $ok ? 0 : (int) $wpdb->insert_id;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function recent( int $limit = 200, string $category = '', string $severity = '' ) : array {
		global $wpdb;
		$limit = max( 1, min( 5000, $limit ) );
		$table = self::table_name();
		$where = 'WHERE 1 = 1';
		$args  = array();
		if ( '' !== $category ) {
			$where .= ' AND category = %s';
			$args[] = $category;
		}
		if ( '' !== $severity ) {
			$where .= ' AND severity = %s';
			$args[] = $severity;
		}
		$args[] = $limit;
		$sql    = $wpdb->prepare( "SELECT * FROM {$table} {$where} ORDER BY occurred_at DESC, id DESC LIMIT %d", $args ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows   = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @return array<string,int>
	 */
	public static function severity_counts( int $hours = 24 ) : array {
		global $wpdb;
		$table = self::table_name();
		$since = gmdate( 'Y-m-d H:i:s', time() - max( 1, $hours ) * HOUR_IN_SECONDS );
		$sql   = $wpdb->prepare( "SELECT severity, COUNT(*) AS total FROM {$table} WHERE occurred_at >= %s GROUP BY severity", $since ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows  = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$out   = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$out[ (string) $row['severity'] ] = (int) $row['total'];
			}
		}
		return $out;
	}

	public static function ip_hash( string $ip ) : string {
		if ( '' === $ip ) {
			return '';
		}
		return hash( 'sha256', $ip . '|' . wp_salt( 'nonce' ) );
	}

	public static function prune( int $retain_days ) : int {
		global $wpdb;
		$retain_days = max( 30, $retain_days );
		$table       = self::table_name();
		$threshold   = gmdate( 'Y-m-d H:i:s', time() - $retain_days * DAY_IN_SECONDS );
		$sql         = $wpdb->prepare( "DELETE FROM {$table} WHERE occurred_at < %s", $threshold ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result      = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return false === $result ? 0 : (int) $result;
	}
}
