<?php
/**
 * Trader store (Article 30 traceability / KYBP) for EuroComply DSA.
 *
 * One row per trader (linked to a WP user when the trader submits via the
 * vendor form). Holds the minimum Art. 30 dataset: name, address, contact,
 * trade-register number, self-certification that products comply with EU law.
 *
 * @package EuroComply\DSA
 */

declare( strict_types = 1 );

namespace EuroComply\DSA;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TraderStore {

	public const DB_VERSION_OPTION = 'eurocomply_dsa_traders_db_version';
	public const DB_VERSION        = '1.0.0';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_dsa_traders';
	}

	public static function install() : void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			submitted_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at DATETIME NULL,
			legal_name VARCHAR(191) NOT NULL DEFAULT '',
			trade_name VARCHAR(191) NOT NULL DEFAULT '',
			address_line1 VARCHAR(191) NOT NULL DEFAULT '',
			address_line2 VARCHAR(191) NOT NULL DEFAULT '',
			postcode VARCHAR(32) NOT NULL DEFAULT '',
			city VARCHAR(128) NOT NULL DEFAULT '',
			country VARCHAR(2) NOT NULL DEFAULT '',
			email VARCHAR(191) NOT NULL DEFAULT '',
			phone VARCHAR(64) NOT NULL DEFAULT '',
			contact_person VARCHAR(191) NOT NULL DEFAULT '',
			trade_register VARCHAR(191) NOT NULL DEFAULT '',
			vat_number VARCHAR(64) NOT NULL DEFAULT '',
			self_certification TINYINT(1) NOT NULL DEFAULT 0,
			id_document_ref VARCHAR(191) NOT NULL DEFAULT '',
			verification_status VARCHAR(32) NOT NULL DEFAULT 'pending',
			verified_at DATETIME NULL,
			verified_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
			notes LONGTEXT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_id (user_id),
			KEY verification_status (verification_status),
			KEY country (country)
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
	 * Upsert trader info by user_id.
	 *
	 * @param array<string,mixed> $data
	 */
	public static function upsert( array $data ) : int {
		global $wpdb;

		$user_id = isset( $data['user_id'] ) ? (int) $data['user_id'] : 0;
		$row     = array(
			'user_id'             => $user_id,
			'legal_name'          => isset( $data['legal_name'] ) ? (string) $data['legal_name'] : '',
			'trade_name'          => isset( $data['trade_name'] ) ? (string) $data['trade_name'] : '',
			'address_line1'       => isset( $data['address_line1'] ) ? (string) $data['address_line1'] : '',
			'address_line2'       => isset( $data['address_line2'] ) ? (string) $data['address_line2'] : '',
			'postcode'            => isset( $data['postcode'] ) ? (string) $data['postcode'] : '',
			'city'                => isset( $data['city'] ) ? (string) $data['city'] : '',
			'country'             => isset( $data['country'] ) ? (string) $data['country'] : '',
			'email'               => isset( $data['email'] ) ? (string) $data['email'] : '',
			'phone'               => isset( $data['phone'] ) ? (string) $data['phone'] : '',
			'contact_person'      => isset( $data['contact_person'] ) ? (string) $data['contact_person'] : '',
			'trade_register'      => isset( $data['trade_register'] ) ? (string) $data['trade_register'] : '',
			'vat_number'          => isset( $data['vat_number'] ) ? (string) $data['vat_number'] : '',
			'self_certification'  => ! empty( $data['self_certification'] ) ? 1 : 0,
			'id_document_ref'     => isset( $data['id_document_ref'] ) ? (string) $data['id_document_ref'] : '',
			'verification_status' => isset( $data['verification_status'] ) ? (string) $data['verification_status'] : 'pending',
			'notes'               => isset( $data['notes'] ) ? (string) $data['notes'] : '',
			'updated_at'          => current_time( 'mysql' ),
		);

		$existing = $user_id ? self::by_user( $user_id ) : null;
		if ( $existing ) {
			$wpdb->update( self::table_name(), $row, array( 'id' => (int) $existing['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			return (int) $existing['id'];
		}
		$row['submitted_at'] = current_time( 'mysql' );
		$ok                  = $wpdb->insert( self::table_name(), $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( false === $ok ) {
			return 0;
		}
		return (int) $wpdb->insert_id;
	}

	public static function mark_verified( int $id, bool $verified, int $admin_id = 0 ) : bool {
		global $wpdb;
		if ( $id <= 0 ) {
			return false;
		}
		$row = array(
			'verification_status' => $verified ? 'verified' : 'rejected',
			'verified_at'         => current_time( 'mysql' ),
			'verified_by'         => $admin_id ?: get_current_user_id(),
		);
		$ok  = $wpdb->update( self::table_name(), $row, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $ok;
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public static function by_user( int $user_id ) : ?array {
		global $wpdb;
		if ( $user_id <= 0 ) {
			return null;
		}
		$table = self::table_name();
		$sql   = $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d LIMIT 1", $user_id ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row   = $wpdb->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return is_array( $row ) ? $row : null;
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public static function by_id( int $id ) : ?array {
		global $wpdb;
		if ( $id <= 0 ) {
			return null;
		}
		$table = self::table_name();
		$sql   = $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row   = $wpdb->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return is_array( $row ) ? $row : null;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function recent( int $limit = 100 ) : array {
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
		$rows  = $wpdb->get_results( "SELECT verification_status, COUNT(*) AS n FROM {$table} GROUP BY verification_status", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out   = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$out[ (string) $row['verification_status'] ] = (int) $row['n'];
			}
		}
		return $out;
	}
}
