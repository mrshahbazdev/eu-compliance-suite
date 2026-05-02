<?php
/**
 * Consumer defect reports.
 *
 * @package EuroComply\ProductLiability
 */

declare( strict_types = 1 );

namespace EuroComply\ProductLiability;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DefectStore {

	private const SCHEMA_OPTION  = 'eurocomply_pl_defect_schema';
	private const SCHEMA_VERSION = '1';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_pl_defects';
	}

	public static function install() : void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$table   = self::table_name();
		$sql     = "CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            reporter_email_hash CHAR(64) NULL,
            reporter_anonymous TINYINT(1) NOT NULL DEFAULT 0,
            country VARCHAR(8) NULL,
            damage_type VARCHAR(32) NULL,
            defectiveness_factor VARCHAR(32) NULL,
            occurred_at DATE NULL,
            severity VARCHAR(16) NOT NULL DEFAULT 'medium',
            status VARCHAR(32) NOT NULL DEFAULT 'received',
            acknowledged_at DATETIME NULL,
            closed_at DATETIME NULL,
            follow_up_token CHAR(64) NULL,
            summary LONGTEXT NULL,
            PRIMARY KEY  (id),
            KEY product_id (product_id),
            KEY status (status),
            KEY damage_type (damage_type),
            KEY follow_up_token (follow_up_token)
        ) $charset;";
		dbDelta( $sql );
		update_option( self::SCHEMA_OPTION, self::SCHEMA_VERSION );
	}

	public static function maybe_upgrade() : void {
		if ( get_option( self::SCHEMA_OPTION ) !== self::SCHEMA_VERSION ) {
			self::install();
		}
	}

	public static function insert( array $data ) : array {
		global $wpdb;
		$now   = current_time( 'mysql', true );
		$token = bin2hex( random_bytes( 16 ) );
		$hash  = hash_hmac( 'sha256', $token, wp_salt( 'auth' ) );
		$email = sanitize_email( (string) ( $data['email'] ?? '' ) );
		$email_hash = '' !== $email ? hash_hmac( 'sha256', strtolower( $email ), wp_salt( 'auth' ) ) : null;

		$ok = $wpdb->insert(
			self::table_name(),
			array(
				'created_at'           => $now,
				'updated_at'           => $now,
				'product_id'           => (int) ( $data['product_id'] ?? 0 ),
				'reporter_email_hash'  => $email_hash,
				'reporter_anonymous'   => '' === $email ? 1 : 0,
				'country'              => substr( strtoupper( sanitize_text_field( (string) ( $data['country'] ?? '' ) ) ), 0, 8 ),
				'damage_type'          => array_key_exists( ( $data['damage_type'] ?? '' ), Settings::damage_types() ) ? (string) $data['damage_type'] : null,
				'defectiveness_factor' => array_key_exists( ( $data['defectiveness_factor'] ?? '' ), Settings::defectiveness_factors() ) ? (string) $data['defectiveness_factor'] : null,
				'occurred_at'          => ! empty( $data['occurred_at'] ) ? sanitize_text_field( (string) $data['occurred_at'] ) : null,
				'severity'             => in_array( ( $data['severity'] ?? 'medium' ), array( 'low', 'medium', 'high', 'critical' ), true ) ? (string) $data['severity'] : 'medium',
				'status'               => 'received',
				'follow_up_token'      => $hash,
				'summary'              => wp_kses_post( (string) ( $data['summary'] ?? '' ) ),
			)
		);
		return $ok ? array(
			'id'    => (int) $wpdb->insert_id,
			'token' => $token,
		) : array(
			'id'    => 0,
			'token' => '',
		);
	}

	public static function set_status( int $id, string $status ) : bool {
		global $wpdb;
		if ( ! array_key_exists( $status, Settings::defect_status() ) ) {
			return false;
		}
		$now    = current_time( 'mysql', true );
		$update = array(
			'updated_at' => $now,
			'status'     => $status,
		);
		if ( 'acknowledged' === $status ) {
			$update['acknowledged_at'] = $now;
		}
		if ( in_array( $status, array( 'closed', 'no_defect' ), true ) ) {
			$update['closed_at'] = $now;
		}
		return (bool) $wpdb->update( self::table_name(), $update, array( 'id' => $id ), null, array( '%d' ) );
	}

	public static function delete( int $id ) : bool {
		global $wpdb;
		return (bool) $wpdb->delete( self::table_name(), array( 'id' => $id ), array( '%d' ) );
	}

	public static function all() : array {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT * FROM ' . self::table_name() . ' ORDER BY id DESC', ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	public static function count() : int {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table_name() );
	}

	public static function open_count() : int {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . self::table_name() . " WHERE status NOT IN ('closed','no_defect')" );
	}

	public static function critical_count() : int {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . self::table_name() . " WHERE severity = 'critical' AND status NOT IN ('closed','no_defect')" );
	}
}
