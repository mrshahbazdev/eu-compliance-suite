<?php
/**
 * ICT risk-management policy register.
 *
 * @package EuroComply\DORA
 */

declare( strict_types = 1 );

namespace EuroComply\DORA;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PolicyStore {

	private const DB_VERSION_OPTION = 'eurocomply_dora_policies_db_version';
	private const DB_VERSION        = '0.1.0';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_dora_policies';
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
			control_area VARCHAR(48) NOT NULL DEFAULT '',
			policy_name VARCHAR(255) NOT NULL DEFAULT '',
			version VARCHAR(32) NOT NULL DEFAULT '',
			owner VARCHAR(128) NOT NULL DEFAULT '',
			last_review DATE NULL DEFAULT NULL,
			next_review DATE NULL DEFAULT NULL,
			evidence_url VARCHAR(255) NOT NULL DEFAULT '',
			status VARCHAR(16) NOT NULL DEFAULT 'draft',
			notes LONGTEXT NULL,
			PRIMARY KEY  (id),
			KEY control_area (control_area),
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
	public static function control_areas() : array {
		return array(
			'governance'        => __( 'Governance & oversight (Art. 5)',                  'eurocomply-dora' ),
			'risk_framework'    => __( 'ICT risk-management framework (Art. 6)',           'eurocomply-dora' ),
			'identification'    => __( 'Identification (Art. 8)',                            'eurocomply-dora' ),
			'protection'        => __( 'Protection & prevention (Art. 9)',                    'eurocomply-dora' ),
			'detection'         => __( 'Detection (Art. 10)',                                  'eurocomply-dora' ),
			'response_recovery' => __( 'Response & recovery (Art. 11)',                         'eurocomply-dora' ),
			'backup'            => __( 'Backup, restoration & recovery (Art. 12)',                'eurocomply-dora' ),
			'learning'          => __( 'Learning & evolving (Art. 13)',                            'eurocomply-dora' ),
			'communications'    => __( 'Communications (Art. 14)',                                  'eurocomply-dora' ),
			'incident_mgmt'     => __( 'Incident management (Art. 17)',                              'eurocomply-dora' ),
			'testing'           => __( 'Resilience testing (Art. 24)',                                'eurocomply-dora' ),
			'tpp_risk'          => __( 'ICT third-party risk (Art. 28)',                               'eurocomply-dora' ),
			'info_sharing'      => __( 'Information sharing (Art. 45)',                                  'eurocomply-dora' ),
		);
	}

	public static function create( array $row ) : int {
		global $wpdb;
		$wpdb->insert(
			self::table_name(),
			array(
				'created_at'   => current_time( 'mysql' ),
				'control_area' => sanitize_key( (string) ( $row['control_area'] ?? 'governance' ) ),
				'policy_name'  => sanitize_text_field( (string) ( $row['policy_name'] ?? '' ) ),
				'version'      => sanitize_text_field( (string) ( $row['version'] ?? '1.0' ) ),
				'owner'        => sanitize_text_field( (string) ( $row['owner']   ?? '' ) ),
				'last_review'  => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $row['last_review'] ?? '' ) ) ? (string) $row['last_review'] : null,
				'next_review'  => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $row['next_review'] ?? '' ) ) ? (string) $row['next_review'] : null,
				'evidence_url' => esc_url_raw( (string) ( $row['evidence_url'] ?? '' ) ),
				'status'       => in_array( (string) ( $row['status'] ?? 'draft' ), array( 'draft', 'approved', 'review_due', 'retired' ), true ) ? (string) $row['status'] : 'draft',
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
		return (array) $wpdb->get_results( "SELECT * FROM {$table} ORDER BY control_area ASC, policy_name ASC", ARRAY_A );
	}

	public static function count_total() : int {
		global $wpdb;
		$table = self::table_name();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function reviews_due( int $within_days = 30 ) : int {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE next_review IS NOT NULL AND next_review <= %s",
				gmdate( 'Y-m-d', time() + $within_days * 86400 )
			)
		);
	}
}
