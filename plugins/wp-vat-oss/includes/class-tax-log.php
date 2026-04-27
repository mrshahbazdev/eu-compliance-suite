<?php
/**
 * Transaction log: records reverse-charge decisions + VIES validations so the
 * merchant can prove due-diligence during a VAT audit.
 *
 * @package EuroComply\VatOss
 */

declare( strict_types = 1 );

namespace EuroComply\VatOss;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TaxLog {

	public const TABLE = 'eurocomply_vat_log';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	public static function install() : void {
		global $wpdb;

		$table           = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			event VARCHAR(32) NOT NULL,
			order_id BIGINT UNSIGNED NULL,
			buyer_country CHAR(2) NULL,
			shop_country CHAR(2) NULL,
			vat_prefix CHAR(2) NULL,
			vat_number VARCHAR(32) NULL,
			vat_valid TINYINT(1) NOT NULL DEFAULT 0,
			reverse_charge TINYINT(1) NOT NULL DEFAULT 0,
			tax_rate DECIMAL(5,2) NULL,
			vies_source VARCHAR(32) NULL,
			vies_name VARCHAR(255) NULL,
			notes TEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY event (event),
			KEY order_id (order_id),
			KEY buyer_country (buyer_country),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Insert a log entry. Missing fields default to null / 0.
	 *
	 * @param array<string,mixed> $row
	 */
	public static function insert( array $row ) : int {
		global $wpdb;

		$defaults = array(
			'event'          => 'unknown',
			'order_id'       => null,
			'buyer_country'  => null,
			'shop_country'   => null,
			'vat_prefix'     => null,
			'vat_number'     => null,
			'vat_valid'      => 0,
			'reverse_charge' => 0,
			'tax_rate'       => null,
			'vies_source'    => null,
			'vies_name'      => null,
			'notes'          => null,
			'created_at'     => current_time( 'mysql', true ),
		);
		$row = array_merge( $defaults, $row );

		$wpdb->insert( self::table_name(), $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		return (int) $wpdb->insert_id;
	}

	public static function purge() : int {
		global $wpdb;
		$table = self::table_name();
		return (int) $wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL,WordPress.DB.DirectDatabaseQuery
	}

	public static function count() : int {
		global $wpdb;
		$table = self::table_name();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL,WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function recent( int $limit = 100 ) : array {
		global $wpdb;
		$table = self::table_name();
		$limit = max( 1, min( 500, $limit ) );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL,WordPress.DB.DirectDatabaseQuery
		return is_array( $rows ) ? $rows : array();
	}
}
