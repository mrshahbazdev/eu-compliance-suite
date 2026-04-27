<?php
/**
 * Resilience-measures register (Art. 13).
 *
 * @package EuroComply\CER
 */

declare( strict_types = 1 );

namespace EuroComply\CER;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MeasureStore {

	private const DB_VERSION_OPTION = 'eurocomply_cer_measures_db_version';
	private const DB_VERSION        = '0.1.0';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_cer_measures';
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
			service_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			category VARCHAR(48) NOT NULL DEFAULT '',
			measure VARCHAR(255) NOT NULL DEFAULT '',
			owner VARCHAR(128) NOT NULL DEFAULT '',
			deadline DATE NULL DEFAULT NULL,
			status VARCHAR(16) NOT NULL DEFAULT 'planned',
			evidence_url VARCHAR(255) NOT NULL DEFAULT '',
			notes LONGTEXT NULL,
			PRIMARY KEY  (id),
			KEY service_id (service_id),
			KEY category (category),
			KEY status (status)
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

	/**
	 * @return array<string,string>
	 */
	public static function categories() : array {
		return array(
			'physical_protection' => __( 'Physical protection of premises (Art. 13(1)(b))', 'eurocomply-cer' ),
			'continuity'          => __( 'Business continuity & crisis management (Art. 13(1)(c))', 'eurocomply-cer' ),
			'recovery'            => __( 'Recovery from incidents (Art. 13(1)(d))',                  'eurocomply-cer' ),
			'access_control'      => __( 'Access control & background checks (Art. 13(1)(e))',         'eurocomply-cer' ),
			'staff_training'      => __( 'Staff awareness & training (Art. 13(1)(f))',                  'eurocomply-cer' ),
			'risk_governance'     => __( 'Risk governance (Art. 13(1)(a))',                              'eurocomply-cer' ),
			'supply_chain'        => __( 'Supply-chain measures',                                         'eurocomply-cer' ),
			'cyber'               => __( 'Cyber resilience (incl. NIS2 alignment)',                        'eurocomply-cer' ),
		);
	}

	public static function create( array $row ) : int {
		global $wpdb;
		$wpdb->insert(
			self::table_name(),
			array(
				'created_at'   => current_time( 'mysql' ),
				'service_id'   => (int) ( $row['service_id'] ?? 0 ),
				'category'     => sanitize_key( (string) ( $row['category'] ?? 'risk_governance' ) ),
				'measure'      => sanitize_text_field( (string) ( $row['measure'] ?? '' ) ),
				'owner'        => sanitize_text_field( (string) ( $row['owner'] ?? '' ) ),
				'deadline'     => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $row['deadline'] ?? '' ) ) ? (string) $row['deadline'] : null,
				'status'       => in_array( (string) ( $row['status'] ?? 'planned' ), array( 'planned', 'in_progress', 'implemented', 'verified' ), true ) ? (string) $row['status'] : 'planned',
				'evidence_url' => esc_url_raw( (string) ( $row['evidence_url'] ?? '' ) ),
				'notes'        => wp_kses_post( (string) ( $row['notes'] ?? '' ) ),
			)
		);
		return (int) $wpdb->insert_id;
	}

	public static function delete( int $id ) : void {
		global $wpdb;
		$wpdb->delete( self::table_name(), array( 'id' => $id ), array( '%d' ) );
	}

	public static function all() : array {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results( "SELECT * FROM {$table} ORDER BY status ASC, deadline ASC", ARRAY_A );
	}

	public static function count_total() : int {
		global $wpdb;
		$table = self::table_name();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function count_overdue() : int {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE status IN ('planned','in_progress') AND deadline IS NOT NULL AND deadline < %s",
				gmdate( 'Y-m-d' )
			)
		);
	}
}
