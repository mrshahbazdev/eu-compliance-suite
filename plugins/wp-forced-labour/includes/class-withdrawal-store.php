<?php
/**
 * Withdrawal procedure log (Art. 20 ban implementation).
 *
 * @package EuroComply\ForcedLabour
 */

declare( strict_types = 1 );

namespace EuroComply\ForcedLabour;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WithdrawalStore {

	private const SCHEMA_OPTION  = 'eurocomply_fl_withdrawal_schema';
	private const SCHEMA_VERSION = '1';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_fl_withdrawals';
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
            risk_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            supplier_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            decision_ref VARCHAR(190) NULL,
            decision_date DATE NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'planned',
            channels VARCHAR(190) NULL,
            units_recalled BIGINT UNSIGNED NOT NULL DEFAULT 0,
            disposal_method VARCHAR(64) NULL,
            completed_at DATE NULL,
            notes LONGTEXT NULL,
            PRIMARY KEY  (id),
            KEY supplier_id (supplier_id),
            KEY status (status)
        ) $charset;";
		dbDelta( $sql );
		update_option( self::SCHEMA_OPTION, self::SCHEMA_VERSION );
	}

	public static function maybe_upgrade() : void {
		if ( get_option( self::SCHEMA_OPTION ) !== self::SCHEMA_VERSION ) {
			self::install();
		}
	}

	public static function insert( array $data ) : int {
		global $wpdb;
		$now = current_time( 'mysql', true );
		$ok  = $wpdb->insert(
			self::table_name(),
			array(
				'created_at'      => $now,
				'updated_at'      => $now,
				'risk_id'         => (int) ( $data['risk_id'] ?? 0 ),
				'supplier_id'     => (int) ( $data['supplier_id'] ?? 0 ),
				'decision_ref'    => sanitize_text_field( (string) ( $data['decision_ref'] ?? '' ) ),
				'decision_date'   => ! empty( $data['decision_date'] ) ? sanitize_text_field( (string) $data['decision_date'] ) : null,
				'status'          => array_key_exists( ( $data['status'] ?? '' ), Settings::withdrawal_status() ) ? (string) $data['status'] : 'planned',
				'channels'        => sanitize_text_field( (string) ( $data['channels'] ?? '' ) ),
				'units_recalled'  => max( 0, (int) ( $data['units_recalled'] ?? 0 ) ),
				'disposal_method' => sanitize_key( (string) ( $data['disposal_method'] ?? '' ) ),
				'completed_at'    => ! empty( $data['completed_at'] ) ? sanitize_text_field( (string) $data['completed_at'] ) : null,
				'notes'           => wp_kses_post( (string) ( $data['notes'] ?? '' ) ),
			)
		);
		return $ok ? (int) $wpdb->insert_id : 0;
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

	public static function active_count() : int {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . self::table_name() . " WHERE status IN ('planned','in_progress')" );
	}
}
