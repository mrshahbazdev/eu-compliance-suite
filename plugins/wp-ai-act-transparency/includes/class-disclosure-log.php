<?php
/**
 * Disclosure audit log.
 *
 * Table: wp_eurocomply_aiact_log
 *
 * Provides a tamper-evident audit trail for AI-content marking changes —
 * the kind of evidence a market-surveillance authority will request under
 * Art. 70 of the AI Act.
 *
 * @package EuroComply\AIAct
 */

declare( strict_types = 1 );

namespace EuroComply\AIAct;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DisclosureLog {

	public const DB_VERSION_OPTION = 'eurocomply_aiact_log_db_version';
	public const DB_VERSION        = '1.0.0';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_aiact_log';
	}

	public static function install() : void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			occurred_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			post_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			action VARCHAR(64) NOT NULL DEFAULT '',
			provider VARCHAR(64) NOT NULL DEFAULT '',
			purpose VARCHAR(64) NOT NULL DEFAULT '',
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			user_login VARCHAR(60) NOT NULL DEFAULT '',
			details LONGTEXT NULL,
			PRIMARY KEY  (id),
			KEY occurred_at (occurred_at),
			KEY post_id (post_id),
			KEY action (action)
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

		$user_id = (int) ( $data['user_id'] ?? get_current_user_id() );
		$user    = $user_id > 0 ? get_userdata( $user_id ) : false;

		$row = array(
			'occurred_at' => current_time( 'mysql' ),
			'post_id'     => max( 0, (int) ( $data['post_id'] ?? 0 ) ),
			'action'      => substr( sanitize_key( (string) ( $data['action'] ?? '' ) ), 0, 64 ),
			'provider'    => substr( sanitize_key( (string) ( $data['provider'] ?? '' ) ), 0, 64 ),
			'purpose'     => substr( sanitize_key( (string) ( $data['purpose'] ?? '' ) ), 0, 64 ),
			'user_id'     => $user_id,
			'user_login'  => $user instanceof \WP_User ? substr( (string) $user->user_login, 0, 60 ) : '',
			'details'     => isset( $data['details'] ) ? sanitize_textarea_field( (string) $data['details'] ) : '',
		);

		$ok = $wpdb->insert( self::table_name(), $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return false === $ok ? 0 : (int) $wpdb->insert_id;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function recent( int $limit = 100, string $action = '' ) : array {
		global $wpdb;
		$limit = max( 1, min( 5000, $limit ) );
		$table = self::table_name();
		if ( '' !== $action ) {
			$sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE action = %s ORDER BY id DESC LIMIT %d", $action, $limit ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} else {
			$sql = $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return is_array( $rows ) ? $rows : array();
	}

	public static function count_total() : int {
		global $wpdb;
		$table = self::table_name();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
	}
}
