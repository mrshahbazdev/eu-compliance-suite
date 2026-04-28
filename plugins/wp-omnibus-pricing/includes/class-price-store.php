<?php
/**
 * Price history store for EuroComply Omnibus.
 *
 * @package EuroComply\Omnibus
 */

declare( strict_types = 1 );

namespace EuroComply\Omnibus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PriceStore {

	public const DB_VERSION = '2';

	public static function table() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_omnibus_history';
	}

	public static function install() : void {
		global $wpdb;
		$table           = self::table();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id BIGINT UNSIGNED NOT NULL,
			parent_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			regular_price DECIMAL(18,4) NOT NULL DEFAULT 0,
			sale_price DECIMAL(18,4) NULL,
			effective_price DECIMAL(18,4) NOT NULL DEFAULT 0,
			net_price DECIMAL(18,4) NULL,
			tax_class VARCHAR(64) NOT NULL DEFAULT '',
			tax_country CHAR(2) NOT NULL DEFAULT '',
			tax_rate DECIMAL(6,3) NULL,
			currency VARCHAR(3) NOT NULL DEFAULT 'EUR',
			trigger_source VARCHAR(32) NOT NULL DEFAULT 'save',
			recorded_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
			PRIMARY KEY  (id),
			KEY product_id (product_id),
			KEY recorded_at (recorded_at),
			KEY product_time (product_id, recorded_at),
			KEY tax_country (tax_country)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		update_option( 'eurocomply_omnibus_db_version', self::DB_VERSION, false );
	}

	public static function maybe_upgrade() : void {
		$current = (string) get_option( 'eurocomply_omnibus_db_version', '' );
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
			'product_id'      => 0,
			'parent_id'       => 0,
			'regular_price'   => 0,
			'sale_price'      => null,
			'effective_price' => 0,
			'currency'        => self::default_currency(),
			'trigger_source'  => 'save',
			'recorded_at'     => current_time( 'mysql', true ),
		);
		$row      = array_merge( $defaults, $row );

		$data    = array(
			'product_id'      => (int) $row['product_id'],
			'parent_id'       => (int) $row['parent_id'],
			'regular_price'   => (float) $row['regular_price'],
			'effective_price' => (float) $row['effective_price'],
			'tax_class'       => sanitize_text_field( (string) ( $row['tax_class']   ?? '' ) ),
			'tax_country'     => strtoupper( substr( (string) ( $row['tax_country'] ?? '' ), 0, 2 ) ),
			'currency'        => strtoupper( substr( (string) $row['currency'], 0, 3 ) ),
			'trigger_source'  => sanitize_key( (string) $row['trigger_source'] ),
			'recorded_at'     => (string) $row['recorded_at'],
		);
		$formats = array( '%d', '%d', '%f', '%f', '%s', '%s', '%s', '%s', '%s' );

		if ( null !== $row['sale_price'] && '' !== $row['sale_price'] ) {
			$data['sale_price'] = (float) $row['sale_price'];
			$formats[]          = '%f';
		}
		if ( isset( $row['net_price'] ) && '' !== $row['net_price'] && null !== $row['net_price'] ) {
			$data['net_price'] = (float) $row['net_price'];
			$formats[]         = '%f';
		}
		if ( isset( $row['tax_rate'] ) && '' !== $row['tax_rate'] && null !== $row['tax_rate'] ) {
			$data['tax_rate'] = (float) $row['tax_rate'];
			$formats[]        = '%f';
		}

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			self::table(),
			$data,
			$formats
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * Lowest effective price recorded for a product in the last $days days,
	 * excluding the current row (we compare against the active price).
	 *
	 * An optional $before timestamp lets callers compute the reference price
	 * as of a specific moment (useful for the "introductory period" rule,
	 * which requires the lowest price in the 30 days *before* the sale began).
	 *
	 * @return array{price:float,currency:string,recorded_at:string}|null
	 */
	public static function lowest_in_window( int $product_id, int $days, ?int $before_ts = null ) : ?array {
		global $wpdb;
		$table     = self::table();
		$days      = max( 1, min( 3650, $days ) );
		$before_ts = $before_ts ?? time();
		$start     = gmdate( 'Y-m-d H:i:s', $before_ts - ( $days * DAY_IN_SECONDS ) );
		$end       = gmdate( 'Y-m-d H:i:s', $before_ts );

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				"SELECT effective_price, currency, recorded_at
				 FROM {$table}
				 WHERE product_id = %d
				   AND recorded_at >= %s
				   AND recorded_at <= %s
				 ORDER BY effective_price ASC, recorded_at ASC
				 LIMIT 1",
				$product_id,
				$start,
				$end
			),
			ARRAY_A
		);

		if ( ! is_array( $row ) ) {
			return null;
		}

		return array(
			'price'       => (float) $row['effective_price'],
			'currency'    => (string) $row['currency'],
			'recorded_at' => (string) $row['recorded_at'],
		);
	}

	/**
	 * First-seen timestamp for a product — used to enforce the
	 * "introductory period" exemption (new products launched less than N days
	 * ago have no meaningful 30-day reference yet).
	 */
	public static function first_recorded_at( int $product_id ) : ?int {
		global $wpdb;
		$table = self::table();
		$value = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				"SELECT MIN(recorded_at) FROM {$table} WHERE product_id = %d",
				$product_id
			)
		);
		if ( ! $value ) {
			return null;
		}
		$ts = strtotime( (string) $value . ' UTC' );
		return false === $ts ? null : $ts;
	}

	/**
	 * Timestamp at which the current sale started, approximated as the most
	 * recent row where sale_price was NULL (i.e. the previous non-sale row).
	 */
	public static function sale_started_at( int $product_id ) : ?int {
		global $wpdb;
		$table = self::table();
		$value = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				"SELECT MIN(recorded_at) FROM {$table}
				 WHERE product_id = %d AND sale_price IS NOT NULL
				   AND recorded_at > COALESCE(
					(SELECT MAX(recorded_at) FROM {$table}
						WHERE product_id = %d AND sale_price IS NULL),
					'1970-01-01 00:00:00'
				)",
				$product_id,
				$product_id
			)
		);
		if ( ! $value ) {
			return null;
		}
		$ts = strtotime( (string) $value . ' UTC' );
		return false === $ts ? null : $ts;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function history_for_product( int $product_id, int $limit = 200 ) : array {
		global $wpdb;
		$table = self::table();
		$limit = max( 1, min( 1000, $limit ) );
		$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				"SELECT * FROM {$table}
				 WHERE product_id = %d OR parent_id = %d
				 ORDER BY recorded_at DESC, id DESC
				 LIMIT %d",
				$product_id,
				$product_id,
				$limit
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
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

	public static function count_for_product( int $product_id ) : int {
		global $wpdb;
		$table = self::table();
		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE product_id = %d OR parent_id = %d",
				$product_id,
				$product_id
			)
		);
	}

	public static function distinct_product_count() : int {
		global $wpdb;
		$table = self::table();
		return (int) $wpdb->get_var( "SELECT COUNT(DISTINCT product_id) FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Most-recent price-history row for a product as of (or before) a
	 * specific timestamp. Used by sister plugins (e.g. VAT OSS, #3) to
	 * answer "what was the gross price on the date this order was
	 * placed?" for credit-note / refund corrections.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function at_or_before( int $product_id, ?int $timestamp = null ) : ?array {
		global $wpdb;
		$table = self::table();
		$ts    = null === $timestamp ? time() : (int) $timestamp;
		$cut   = gmdate( 'Y-m-d H:i:s', $ts );
		$row   = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				"SELECT * FROM {$table}
				 WHERE ( product_id = %d OR parent_id = %d )
				   AND recorded_at <= %s
				 ORDER BY recorded_at DESC, id DESC
				 LIMIT 1",
				$product_id,
				$product_id,
				$cut
			),
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	public static function default_currency() : string {
		$fn = 'get_woocommerce_currency';
		if ( function_exists( $fn ) ) {
			$code = (string) call_user_func( $fn );
			if ( '' !== $code ) {
				return strtoupper( substr( $code, 0, 3 ) );
			}
		}
		return 'EUR';
	}
}
