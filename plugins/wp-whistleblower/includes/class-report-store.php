<?php
/**
 * Report storage.
 *
 * @package EuroComply\Whistleblower
 */

declare( strict_types = 1 );

namespace EuroComply\Whistleblower;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ReportStore {

	public const DB_VERSION_OPTION = 'eurocomply_wb_reports_db_version';
	public const DB_VERSION        = '1.0.0';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_wb_reports';
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
			status VARCHAR(32) NOT NULL DEFAULT 'received',
			category VARCHAR(64) NOT NULL DEFAULT 'other',
			subject VARCHAR(255) NOT NULL DEFAULT '',
			body LONGTEXT NULL,
			anonymous TINYINT UNSIGNED NOT NULL DEFAULT 1,
			contact_method VARCHAR(32) NOT NULL DEFAULT '',
			contact_value VARCHAR(255) NOT NULL DEFAULT '',
			files_json LONGTEXT NULL,
			ip_hash CHAR(64) NOT NULL DEFAULT '',
			recipient_user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			acknowledged_at DATETIME NULL DEFAULT NULL,
			feedback_sent_at DATETIME NULL DEFAULT NULL,
			closed_at DATETIME NULL DEFAULT NULL,
			internal_notes LONGTEXT NULL,
			PRIMARY KEY  (id),
			KEY follow_up_token_hash (follow_up_token_hash),
			KEY status (status),
			KEY category (category),
			KEY created_at (created_at)
		) {$charset};";

		dbDelta( $sql );
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
	}

	public static function maybe_upgrade() : void {
		if ( get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	public static function statuses() : array {
		return array(
			'received'      => __( 'Received',      'eurocomply-whistleblower' ),
			'acknowledged'  => __( 'Acknowledged',  'eurocomply-whistleblower' ),
			'investigating' => __( 'Investigating', 'eurocomply-whistleblower' ),
			'action_taken'  => __( 'Action taken',  'eurocomply-whistleblower' ),
			'closed'        => __( 'Closed',        'eurocomply-whistleblower' ),
			'rejected'      => __( 'Rejected',      'eurocomply-whistleblower' ),
		);
	}

	/**
	 * Hash a token (constant-time comparison via hash_equals at lookup time).
	 */
	public static function hash_token( string $token ) : string {
		return hash_hmac( 'sha256', $token, wp_salt( 'auth' ) );
	}

	public static function generate_token() : string {
		return wp_generate_password( 24, false, false );
	}

	public static function hash_ip( string $ip ) : string {
		if ( '' === $ip ) {
			return '';
		}
		return hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) );
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public static function create( array $data ) : int {
		global $wpdb;
		$now = current_time( 'mysql' );
		$row = array(
			'created_at'           => $now,
			'updated_at'           => $now,
			'follow_up_token_hash' => isset( $data['follow_up_token_hash'] ) ? (string) $data['follow_up_token_hash'] : '',
			'status'               => isset( $data['status'] ) ? sanitize_key( (string) $data['status'] ) : 'received',
			'category'             => isset( $data['category'] ) ? sanitize_key( (string) $data['category'] ) : 'other',
			'subject'              => isset( $data['subject'] ) ? mb_substr( sanitize_text_field( (string) $data['subject'] ), 0, 255 ) : '',
			'body'                 => isset( $data['body'] ) ? wp_kses_post( (string) $data['body'] ) : '',
			'anonymous'            => isset( $data['anonymous'] ) && (int) $data['anonymous'] ? 1 : 0,
			'contact_method'       => isset( $data['contact_method'] ) ? sanitize_key( (string) $data['contact_method'] ) : '',
			'contact_value'        => isset( $data['contact_value'] ) ? sanitize_text_field( (string) $data['contact_value'] ) : '',
			'files_json'           => isset( $data['files_json'] ) ? wp_json_encode( $data['files_json'] ) : '',
			'ip_hash'              => isset( $data['ip_hash'] ) ? (string) $data['ip_hash'] : '',
		);
		$ok = $wpdb->insert( self::table_name(), $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return false === $ok ? 0 : (int) $wpdb->insert_id;
	}

	public static function get( int $id ) : ?array {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	public static function find_by_token( string $token ) : ?array {
		global $wpdb;
		$hash  = self::hash_token( $token );
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE follow_up_token_hash = %s LIMIT 1", $hash ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public static function update( int $id, array $data ) : bool {
		global $wpdb;
		$data['updated_at'] = current_time( 'mysql' );
		$ok = $wpdb->update( self::table_name(), $data, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $ok;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function recent( int $limit = 50, string $status = '' ) : array {
		global $wpdb;
		$limit = max( 1, min( 5000, $limit ) );
		$table = self::table_name();
		if ( '' !== $status ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE status = %s ORDER BY id DESC LIMIT %d", $status, $limit ), ARRAY_A );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit ), ARRAY_A );
		}
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Open reports older than ack window with no acknowledged_at.
	 */
	public static function overdue_ack( int $days ) : int {
		global $wpdb;
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - max( 1, $days ) * DAY_IN_SECONDS );
		$table  = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE status NOT IN ('closed','rejected') AND created_at < %s AND acknowledged_at IS NULL",
			$cutoff
		) );
	}

	public static function overdue_feedback( int $days ) : int {
		global $wpdb;
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - max( 1, $days ) * DAY_IN_SECONDS );
		$table  = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE status NOT IN ('closed','rejected') AND created_at < %s AND feedback_sent_at IS NULL",
			$cutoff
		) );
	}

	public static function count_total() : int {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	public static function count_open() : int {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status NOT IN ('closed','rejected')" );
	}
}
