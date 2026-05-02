<?php
/**
 * Formal liability claims register.
 *
 * @package EuroComply\ProductLiability
 */

declare( strict_types = 1 );

namespace EuroComply\ProductLiability;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ClaimStore {

	private const SCHEMA_OPTION  = 'eurocomply_pl_claim_schema';
	private const SCHEMA_VERSION = '1';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_pl_claims';
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
            defect_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            claimant_ref VARCHAR(190) NULL,
            jurisdiction VARCHAR(8) NULL,
            damage_type VARCHAR(32) NULL,
            damage_value_eur DECIMAL(15,2) NOT NULL DEFAULT 0,
            occurred_on DATE NULL,
            became_aware_on DATE NULL,
            limitation_until DATE NULL,
            extended_limitation_until DATE NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'received',
            settled_amount_eur DECIMAL(15,2) NOT NULL DEFAULT 0,
            settled_on DATE NULL,
            outcome VARCHAR(32) NULL,
            counsel VARCHAR(190) NULL,
            notes LONGTEXT NULL,
            PRIMARY KEY  (id),
            KEY product_id (product_id),
            KEY defect_id (defect_id),
            KEY status (status),
            KEY limitation_until (limitation_until)
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
		$settings        = Settings::get();
		$became_aware_on = ! empty( $data['became_aware_on'] ) ? sanitize_text_field( (string) $data['became_aware_on'] ) : null;
		$limitation      = null;
		$extended        = null;
		if ( $became_aware_on ) {
			$ts = strtotime( $became_aware_on );
			if ( false !== $ts ) {
				$limitation = gmdate( 'Y-m-d', $ts + ( 3 * YEAR_IN_SECONDS ) ); // Art. 17(1) — 3 years from awareness.
				$extended   = gmdate( 'Y-m-d', $ts + ( (int) $settings['latent_injury_years'] * YEAR_IN_SECONDS ) );
			}
		}

		$ok = $wpdb->insert(
			self::table_name(),
			array(
				'created_at'                => current_time( 'mysql', true ),
				'updated_at'                => current_time( 'mysql', true ),
				'product_id'                => (int) ( $data['product_id'] ?? 0 ),
				'defect_id'                 => (int) ( $data['defect_id'] ?? 0 ),
				'claimant_ref'              => sanitize_text_field( (string) ( $data['claimant_ref'] ?? '' ) ),
				'jurisdiction'              => substr( strtoupper( sanitize_text_field( (string) ( $data['jurisdiction'] ?? '' ) ) ), 0, 8 ),
				'damage_type'               => array_key_exists( ( $data['damage_type'] ?? '' ), Settings::damage_types() ) ? (string) $data['damage_type'] : null,
				'damage_value_eur'          => (float) ( $data['damage_value_eur'] ?? 0 ),
				'occurred_on'               => ! empty( $data['occurred_on'] ) ? sanitize_text_field( (string) $data['occurred_on'] ) : null,
				'became_aware_on'           => $became_aware_on,
				'limitation_until'          => $limitation,
				'extended_limitation_until' => $extended,
				'status'                    => array_key_exists( ( $data['status'] ?? '' ), Settings::claim_status() ) ? (string) $data['status'] : 'received',
				'counsel'                   => sanitize_text_field( (string) ( $data['counsel'] ?? '' ) ),
				'notes'                     => wp_kses_post( (string) ( $data['notes'] ?? '' ) ),
			)
		);
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	public static function set_status( int $id, string $status, array $extra = array() ) : bool {
		global $wpdb;
		if ( ! array_key_exists( $status, Settings::claim_status() ) ) {
			return false;
		}
		$update = array(
			'updated_at' => current_time( 'mysql', true ),
			'status'     => $status,
		);
		if ( 'settled' === $status ) {
			$update['settled_on']         = ! empty( $extra['settled_on'] ) ? sanitize_text_field( (string) $extra['settled_on'] ) : current_time( 'Y-m-d', true );
			$update['settled_amount_eur'] = (float) ( $extra['settled_amount_eur'] ?? 0 );
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
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . self::table_name() . " WHERE status NOT IN ('settled','rejected','time_barred','closed')" );
	}

	public static function nearing_limitation_count( int $days = 90 ) : int {
		global $wpdb;
		$today  = current_time( 'Y-m-d', true );
		$cutoff = gmdate( 'Y-m-d', current_time( 'timestamp', true ) + ( $days * DAY_IN_SECONDS ) );
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM " . self::table_name() . " WHERE status NOT IN ('settled','rejected','time_barred','closed') AND limitation_until IS NOT NULL AND limitation_until BETWEEN %s AND %s",
				$today,
				$cutoff
			)
		);
	}

	public static function total_paid_eur() : float {
		global $wpdb;
		return (float) $wpdb->get_var( "SELECT COALESCE(SUM(settled_amount_eur),0) FROM " . self::table_name() . " WHERE status = 'settled'" );
	}
}
