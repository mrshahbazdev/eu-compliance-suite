<?php
/**
 * Preventive (Art. 10) and corrective (Art. 11) action plans.
 *
 * @package EuroComply\CSDDD
 */

declare( strict_types = 1 );

namespace EuroComply\CSDDD;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ActionStore {

	private const SCHEMA_VERSION = '1.0.0';
	private const SCHEMA_OPTION  = 'eurocomply_csddd_action_schema';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_csddd_actions';
	}

	public static function install() : void {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$table   = self::table_name();
		$sql     = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			risk_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			action_type VARCHAR(64) NOT NULL DEFAULT 'corrective_plan',
			article VARCHAR(8) NOT NULL DEFAULT '10',
			deadline DATE NULL,
			completed_at DATE NULL,
			owner VARCHAR(190) NOT NULL DEFAULT '',
			notes TEXT NULL,
			PRIMARY KEY (id),
			KEY risk_id (risk_id),
			KEY action_type (action_type),
			KEY deadline (deadline)
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
	 */
	public static function insert( array $data ) : int {
		global $wpdb;
		$type = isset( $data['action_type'] ) ? sanitize_key( (string) $data['action_type'] ) : 'corrective_plan';
		if ( ! array_key_exists( $type, Settings::action_types() ) ) {
			$type = 'corrective_plan';
		}
		$row = array(
			'risk_id'      => isset( $data['risk_id'] ) ? max( 0, (int) $data['risk_id'] ) : 0,
			'action_type'  => $type,
			'article'      => isset( $data['article'] ) ? sanitize_text_field( (string) $data['article'] ) : '10',
			'deadline'     => isset( $data['deadline'] ) && '' !== $data['deadline'] ? sanitize_text_field( (string) $data['deadline'] ) : null,
			'completed_at' => isset( $data['completed_at'] ) && '' !== $data['completed_at'] ? sanitize_text_field( (string) $data['completed_at'] ) : null,
			'owner'        => isset( $data['owner'] ) ? sanitize_text_field( (string) $data['owner'] ) : '',
			'notes'        => isset( $data['notes'] ) ? wp_kses_post( (string) $data['notes'] ) : '',
		);
		$ok = $wpdb->insert( self::table_name(), $row );
		return false === $ok ? 0 : (int) $wpdb->insert_id;
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
		$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' ORDER BY deadline ASC, id DESC LIMIT %d', $limit ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	public static function count() : int {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table_name() );
	}

	public static function overdue_count() : int {
		global $wpdb;
		$today = gmdate( 'Y-m-d' );
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table_name() . ' WHERE deadline IS NOT NULL AND deadline < %s AND completed_at IS NULL', $today ) );
	}
}
