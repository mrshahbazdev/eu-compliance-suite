<?php
/**
 * Invoice log store for EuroComply E-Invoicing.
 *
 * @package EuroComply\EInvoicing
 */

declare( strict_types = 1 );

namespace EuroComply\EInvoicing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class InvoiceStore {

	public const DB_VERSION = '1';

	public static function table() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_einv_log';
	}

	public static function install() : void {
		global $wpdb;
		$table           = self::table();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id BIGINT UNSIGNED NOT NULL,
			invoice_number VARCHAR(64) NOT NULL DEFAULT '',
			profile VARCHAR(32) NOT NULL DEFAULT 'minimum',
			total DECIMAL(18,4) NOT NULL DEFAULT 0,
			currency VARCHAR(3) NOT NULL DEFAULT 'EUR',
			file_path TEXT NOT NULL,
			file_url TEXT NOT NULL,
			status VARCHAR(16) NOT NULL DEFAULT 'generated',
			message TEXT NOT NULL,
			generated_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
			PRIMARY KEY  (id),
			KEY order_id (order_id),
			KEY generated_at (generated_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		update_option( 'eurocomply_einv_db_version', self::DB_VERSION, false );
	}

	public static function maybe_upgrade() : void {
		$current = (string) get_option( 'eurocomply_einv_db_version', '' );
		if ( self::DB_VERSION !== $current ) {
			self::install();
		}
	}

	/**
	 * @param array<string,mixed> $row
	 */
	public static function record( array $row ) : int {
		global $wpdb;
		$defaults = array(
			'order_id'       => 0,
			'invoice_number' => '',
			'profile'        => 'minimum',
			'total'          => 0,
			'currency'       => 'EUR',
			'file_path'      => '',
			'file_url'       => '',
			'status'         => 'generated',
			'message'        => '',
			'generated_at'   => current_time( 'mysql', true ),
		);
		$row      = array_merge( $defaults, $row );
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			self::table(),
			$row,
			array( '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function recent( int $limit = 50 ) : array {
		global $wpdb;
		$table = self::table();
		$limit = max( 1, min( 500, $limit ) );
		$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit ),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	public static function count() : int {
		global $wpdb;
		$table = self::table();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public static function latest_for_order( int $order_id ) : ?array {
		global $wpdb;
		$table = self::table();
		$row   = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare( "SELECT * FROM {$table} WHERE order_id = %d ORDER BY id DESC LIMIT 1", $order_id ),
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}
}
