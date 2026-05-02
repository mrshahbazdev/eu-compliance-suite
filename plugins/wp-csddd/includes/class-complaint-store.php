<?php
/**
 * Stakeholder complaints / notification mechanism (Art. 14).
 *
 * @package EuroComply\CSDDD
 */

declare( strict_types = 1 );

namespace EuroComply\CSDDD;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ComplaintStore {

	private const SCHEMA_VERSION = '1.0.0';
	private const SCHEMA_OPTION  = 'eurocomply_csddd_complaint_schema';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_csddd_complaints';
	}

	public static function install() : void {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$table   = self::table_name();
		$sql     = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			complainant_email_hash CHAR(64) NOT NULL DEFAULT '',
			complainant_anonymous TINYINT(1) NOT NULL DEFAULT 0,
			supplier_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			category VARCHAR(64) NOT NULL DEFAULT '',
			country CHAR(2) NOT NULL DEFAULT '',
			status VARCHAR(32) NOT NULL DEFAULT 'received',
			acknowledged_at DATETIME NULL,
			closed_at DATETIME NULL,
			follow_up_token CHAR(64) NOT NULL DEFAULT '',
			summary TEXT NULL,
			PRIMARY KEY (id),
			KEY supplier_id (supplier_id),
			KEY status (status),
			KEY category (category)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		update_option( self::SCHEMA_OPTION, self::SCHEMA_VERSION, false );
	}

	public static function maybe_upgrade() : void {
		if ( get_option( self::SCHEMA_OPTION ) !== self::SCHEMA_VERSION ) {
			self::install();
		}
	}

	/**
	 * @param array<string,mixed> $data
	 * @return array{id:int,token:string}
	 */
	public static function insert( array $data ) : array {
		global $wpdb;
		$cats  = Settings::risk_categories();
		$cat   = isset( $data['category'] ) ? sanitize_key( (string) $data['category'] ) : '';
		if ( ! isset( $cats[ $cat ] ) ) {
			return array( 'id' => 0, 'token' => '' );
		}
		$token = wp_generate_password( 32, false, false );
		$email = isset( $data['complainant_email'] ) ? sanitize_email( (string) $data['complainant_email'] ) : '';
		$anon  = '' === $email ? 1 : 0;
		$row   = array(
			'complainant_email_hash' => '' === $email ? '' : hash_hmac( 'sha256', $email, wp_salt( 'auth' ) ),
			'complainant_anonymous'  => $anon,
			'supplier_id'            => isset( $data['supplier_id'] ) ? max( 0, (int) $data['supplier_id'] ) : 0,
			'category'               => $cat,
			'country'                => isset( $data['country'] ) ? strtoupper( substr( sanitize_text_field( (string) $data['country'] ), 0, 2 ) ) : '',
			'status'                 => 'received',
			'follow_up_token'        => hash_hmac( 'sha256', $token, wp_salt( 'nonce' ) ),
			'summary'                => isset( $data['summary'] ) ? wp_kses_post( (string) $data['summary'] ) : '',
		);
		$ok = $wpdb->insert( self::table_name(), $row );
		if ( false === $ok ) {
			return array( 'id' => 0, 'token' => '' );
		}
		return array( 'id' => (int) $wpdb->insert_id, 'token' => $token );
	}

	public static function set_status( int $id, string $status ) : bool {
		global $wpdb;
		if ( ! array_key_exists( $status, Settings::complaint_status() ) ) {
			return false;
		}
		$update = array( 'status' => $status );
		if ( 'acknowledged' === $status ) {
			$update['acknowledged_at'] = current_time( 'mysql', true );
		} elseif ( 'closed' === $status || 'rejected' === $status ) {
			$update['closed_at'] = current_time( 'mysql', true );
		}
		return false !== $wpdb->update( self::table_name(), $update, array( 'id' => $id ), null, array( '%d' ) );
	}

	public static function delete( int $id ) : bool {
		global $wpdb;
		return false !== $wpdb->delete( self::table_name(), array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function all( int $limit = 500 ) : array {
		global $wpdb;
		$limit = max( 1, min( 5000, $limit ) );
		$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' ORDER BY id DESC LIMIT %d', $limit ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	public static function count() : int {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table_name() );
	}

	/**
	 * Art. 14 — Article does not fix a hard ack deadline; we use 30 days as a reasonable in-house SLA.
	 */
	public static function overdue_ack_count( int $days = 30 ) : int {
		global $wpdb;
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table_name() . " WHERE acknowledged_at IS NULL AND created_at < %s AND status NOT IN ('closed','rejected')", $cutoff ) );
	}
}
