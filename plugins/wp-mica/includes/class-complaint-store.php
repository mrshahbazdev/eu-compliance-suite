<?php
/**
 * Complaint-handling register (Art. 31 for CASPs; Art. 27 for ART issuers).
 *
 * @package EuroComply\MiCA
 */

declare( strict_types = 1 );

namespace EuroComply\MiCA;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ComplaintStore {

	private const DB_VERSION_OPTION = 'eurocomply_mica_complaints_db_version';
	private const DB_VERSION        = '0.1.0';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_mica_complaints';
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
			received_at DATETIME NULL DEFAULT NULL,
			ack_at DATETIME NULL DEFAULT NULL,
			resolved_at DATETIME NULL DEFAULT NULL,
			complainant_hash CHAR(64) NOT NULL DEFAULT '',
			country CHAR(2) NOT NULL DEFAULT '',
			category VARCHAR(48) NOT NULL DEFAULT '',
			asset_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			summary LONGTEXT NULL,
			outcome LONGTEXT NULL,
			status VARCHAR(24) NOT NULL DEFAULT 'received',
			PRIMARY KEY  (id),
			KEY status (status),
			KEY category (category)
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

	public static function create( array $row ) : int {
		global $wpdb;
		$ref = (string) ( $row['complainant_ref'] ?? wp_generate_uuid4() );
		$hash = hash_hmac( 'sha256', $ref, wp_salt( 'auth' ) );
		$wpdb->insert(
			self::table_name(),
			array(
				'created_at'       => current_time( 'mysql' ),
				'received_at'      => self::dt( $row['received_at'] ?? null ) ?: current_time( 'mysql' ),
				'ack_at'           => self::dt( $row['ack_at']      ?? null ),
				'resolved_at'      => self::dt( $row['resolved_at'] ?? null ),
				'complainant_hash' => $hash,
				'country'          => strtoupper( substr( (string) ( $row['country'] ?? '' ), 0, 2 ) ),
				'category'         => sanitize_key( (string) ( $row['category'] ?? 'other' ) ),
				'asset_id'         => (int) ( $row['asset_id'] ?? 0 ),
				'summary'          => wp_kses_post( (string) ( $row['summary'] ?? '' ) ),
				'outcome'          => wp_kses_post( (string) ( $row['outcome'] ?? '' ) ),
				'status'           => in_array( (string) ( $row['status'] ?? 'received' ), array( 'received', 'investigating', 'resolved', 'rejected', 'escalated' ), true ) ? (string) $row['status'] : 'received',
			)
		);
		return (int) $wpdb->insert_id;
	}

	public static function mark_step( int $id, string $step ) : void {
		if ( ! in_array( $step, array( 'ack', 'resolved' ), true ) ) {
			return;
		}
		global $wpdb;
		$col = $step . '_at';
		$wpdb->update( self::table_name(), array( $col => current_time( 'mysql' ) ), array( 'id' => $id ), array( '%s' ), array( '%d' ) );
	}

	public static function delete( int $id ) : void {
		global $wpdb;
		$wpdb->delete( self::table_name(), array( 'id' => $id ), array( '%d' ) );
	}

	public static function all() : array {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC", ARRAY_A );
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
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status IN ('received','investigating','escalated')" );
	}

	public static function ack_overdue( int $ack_days ) : int {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE ack_at IS NULL AND received_at IS NOT NULL AND TIMESTAMPDIFF(DAY, received_at, %s) > %d",
				current_time( 'mysql' ),
				max( 1, $ack_days )
			)
		);
	}

	public static function resolution_overdue( int $resolution_days ) : int {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE resolved_at IS NULL AND status IN ('received','investigating','escalated') AND received_at IS NOT NULL AND TIMESTAMPDIFF(DAY, received_at, %s) > %d",
				current_time( 'mysql' ),
				max( 1, $resolution_days )
			)
		);
	}

	private static function dt( $v ) : ?string {
		if ( null === $v || '' === $v ) {
			return null;
		}
		$v = str_replace( 'T', ' ', (string) $v );
		if ( preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $v ) ) {
			return strlen( $v ) === 16 ? $v . ':00' : $v;
		}
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ) {
			return $v . ' 00:00:00';
		}
		return null;
	}
}
