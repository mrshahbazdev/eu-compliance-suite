<?php
/**
 * Per-plugin connectors.
 *
 * Each connector returns a uniform shape so the aggregator can build a
 * unified compliance view without touching the underlying plugin classes
 * directly. Connectors are designed to degrade gracefully — if a sister
 * plugin is not active, its connector reports `active=false` and is
 * excluded from the score.
 *
 * Connector return shape:
 *   array{
 *     slug:string,           // e.g. 'legal-pages'
 *     name:string,           // human-readable
 *     reference:string,      // citation, e.g. 'EU 2017/1369'
 *     active:bool,
 *     pro:bool,
 *     menu_url:string,
 *     metrics: array<int, array{label:string,value:int|string}>,
 *     alerts:  array<int, array{severity:string,message:string,link?:string}>,
 *     score:int,             // 0..100, ignored when active=false
 *   }
 *
 * @package EuroComply\Dashboard
 */

declare( strict_types = 1 );

namespace EuroComply\Dashboard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Connectors {

	/**
	 * @return array<int, array<string,mixed>>
	 */
	public static function all() : array {
		return array(
			self::legal_pages(),
			self::cookie_consent(),
			self::vat_oss(),
			self::gpsr(),
			self::epr(),
			self::eaa(),
			self::omnibus(),
			self::dsa(),
			self::age_verification(),
			self::dsar(),
			self::nis2(),
			self::r2r(),
			self::ai_act(),
			self::whistleblower(),
			self::eprivacy(),
			self::pay_transparency(),
			self::cbam(),
			self::csrd(),
			self::psd2(),
			self::eudr(),
		);
	}

	private static function admin_url( string $menu_slug ) : string {
		return admin_url( 'admin.php?page=' . $menu_slug );
	}

	private static function table_exists( string $name ) : bool {
		global $wpdb;
		$table = $wpdb->prefix . $name;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		return is_string( $exists ) && $exists === $table;
	}

	private static function table_count( string $name, string $where = '', array $args = array() ) : int {
		global $wpdb;
		$table = $wpdb->prefix . $name;
		if ( ! self::table_exists( $name ) ) {
			return 0;
		}
		$sql = "SELECT COUNT(*) FROM {$table}" . ( '' !== $where ? ' WHERE ' . $where : '' );
		if ( ! empty( $args ) ) {
			$sql = $wpdb->prepare( $sql, $args ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $sql );
	}

	/* -------- Plugin #1 — Legal Pages -------- */
	private static function legal_pages() : array {
		$option = get_option( 'eurocomply_legal_settings' );
		$active = is_array( $option ) || class_exists( '\\EuroComply\\Legal\\Plugin' );
		$alerts = array();
		$score  = 0;
		$pages  = 0;

		if ( $active ) {
			foreach ( array( 'imprint', 'privacy', 'terms', 'cookie_policy' ) as $key ) {
				if ( ! empty( $option[ $key . '_page_id' ] ) && get_post( (int) $option[ $key . '_page_id' ] ) ) {
					$pages++;
				}
			}
			if ( $pages < 4 ) {
				$alerts[] = array(
					'severity' => 'warn',
					'message'  => sprintf( /* translators: %d count */ __( '%d / 4 statutory pages configured (Imprint, Privacy, Terms, Cookie policy).', 'eurocomply-dashboard' ), $pages ),
					'link'     => self::admin_url( 'eurocomply-legal' ),
				);
			}
			$score = (int) round( ( $pages / 4 ) * 100 );
		}

		return array(
			'slug'      => 'legal-pages',
			'name'      => __( 'Legal Pages', 'eurocomply-dashboard' ),
			'reference' => 'GDPR Art. 13–14, ePrivacy, TMG §5',
			'active'    => $active,
			'pro'       => self::is_pro( 'eurocomply_legal_pages_license' ) || self::is_pro( 'eurocomply_legal_license' ),
			'menu_url'  => self::admin_url( 'eurocomply-legal' ),
			'metrics'   => array( array( 'label' => __( 'Statutory pages', 'eurocomply-dashboard' ), 'value' => $pages . ' / 4' ) ),
			'alerts'    => $alerts,
			'score'     => $score,
		);
	}

	/* -------- Plugin #2 — Cookie Consent -------- */
	private static function cookie_consent() : array {
		$option = get_option( 'eurocomply_cc_settings' );
		$active = is_array( $option ) || class_exists( '\\EuroComply\\CookieConsent\\Plugin' );
		$alerts = array();
		$cats   = 0;
		$score  = 0;

		if ( $active && is_array( $option ) ) {
			$categories = isset( $option['categories'] ) && is_array( $option['categories'] ) ? $option['categories'] : array();
			$cats       = count( $categories );
			$score      = $cats > 0 ? 100 : 40;
			if ( 0 === $cats ) {
				$alerts[] = array(
					'severity' => 'warn',
					'message'  => __( 'No cookie categories configured. GCM v2 will not signal consent correctly.', 'eurocomply-dashboard' ),
					'link'     => self::admin_url( 'eurocomply-cookie-consent' ),
				);
			}
		} elseif ( $active ) {
			$score = 50;
		}

		return array(
			'slug'      => 'cookie-consent',
			'name'      => __( 'Cookie Consent + GCM v2', 'eurocomply-dashboard' ),
			'reference' => 'ePrivacy 2002/58, GDPR Art. 7, GCM v2',
			'active'    => $active,
			'pro'       => self::is_pro( 'eurocomply_cc_license' ),
			'menu_url'  => self::admin_url( 'eurocomply-cookie-consent' ),
			'metrics'   => array( array( 'label' => __( 'Categories', 'eurocomply-dashboard' ), 'value' => $cats ) ),
			'alerts'    => $alerts,
			'score'     => $score,
		);
	}

	/* -------- Plugin #3 — VAT OSS -------- */
	private static function vat_oss() : array {
		$option = get_option( 'eurocomply_vat_settings' );
		$active = is_array( $option ) || class_exists( '\\EuroComply\\VatOss\\Plugin' );
		$alerts = array();
		$score  = $active ? 80 : 0;

		return array(
			'slug'      => 'vat-oss',
			'name'      => __( 'VAT OSS', 'eurocomply-dashboard' ),
			'reference' => 'Council Directive 2017/2455 (e-commerce VAT)',
			'active'    => $active,
			'pro'       => self::is_pro( 'eurocomply_vat_license' ),
			'menu_url'  => self::admin_url( 'eurocomply-vat-oss' ),
			'metrics'   => array(),
			'alerts'    => $alerts,
			'score'     => $score,
		);
	}

	/* -------- Plugin #4 — GPSR -------- */
	private static function gpsr() : array {
		$option = get_option( 'eurocomply_gpsr_settings' );
		$active = is_array( $option ) || class_exists( '\\EuroComply\\GPSR\\Plugin' );
		$score  = $active ? 75 : 0;

		return array(
			'slug'      => 'gpsr',
			'name'      => __( 'General Product Safety (GPSR)', 'eurocomply-dashboard' ),
			'reference' => 'Regulation (EU) 2023/988',
			'active'    => $active,
			'pro'       => self::is_pro( 'eurocomply_gpsr_license' ),
			'menu_url'  => self::admin_url( 'eurocomply-gpsr' ),
			'metrics'   => array(),
			'alerts'    => array(),
			'score'     => $score,
		);
	}

	/* -------- Plugin #5 — EPR -------- */
	private static function epr() : array {
		$option = get_option( 'eurocomply_epr_settings' );
		$active = is_array( $option ) || class_exists( '\\EuroComply\\EPR\\Plugin' );
		$score  = $active ? 75 : 0;

		return array(
			'slug'      => 'epr',
			'name'      => __( 'Extended Producer Responsibility (EPR)', 'eurocomply-dashboard' ),
			'reference' => 'WEEE 2012/19, Packaging 94/62, Batteries 2023/1542',
			'active'    => $active,
			'pro'       => self::is_pro( 'eurocomply_epr_license' ),
			'menu_url'  => self::admin_url( 'eurocomply-epr' ),
			'metrics'   => array(),
			'alerts'    => array(),
			'score'     => $score,
		);
	}

	/* -------- Plugin #6 — EAA -------- */
	private static function eaa() : array {
		$active = class_exists( '\\EuroComply\\EAA\\Plugin' ) || (bool) get_option( 'eurocomply_eaa_settings' );
		return array(
			'slug'      => 'eaa',
			'name'      => __( 'European Accessibility Act (EAA)', 'eurocomply-dashboard' ),
			'reference' => 'Directive (EU) 2019/882',
			'active'    => $active,
			'pro'       => self::is_pro( 'eurocomply_eaa_license' ),
			'menu_url'  => self::admin_url( 'eurocomply-eaa' ),
			'metrics'   => array(),
			'alerts'    => array(),
			'score'     => $active ? 80 : 0,
		);
	}

	/* -------- Plugin #8 — Omnibus -------- */
	private static function omnibus() : array {
		$active = self::table_exists( 'eurocomply_omnibus_history' ) || class_exists( '\\EuroComply\\Omnibus\\Plugin' );
		$rows   = self::table_count( 'eurocomply_omnibus_history' );

		return array(
			'slug'      => 'omnibus',
			'name'      => __( 'Omnibus 30-day price', 'eurocomply-dashboard' ),
			'reference' => 'Directive (EU) 2019/2161',
			'active'    => $active,
			'pro'       => self::is_pro( 'eurocomply_omnibus_license' ),
			'menu_url'  => self::admin_url( 'eurocomply-omnibus' ),
			'metrics'   => array( array( 'label' => __( 'Price-history rows', 'eurocomply-dashboard' ), 'value' => $rows ) ),
			'alerts'    => 0 === $rows && $active ? array( array(
				'severity' => 'info',
				'message'  => __( 'No price history captured yet. Run the backfill on the Omnibus dashboard.', 'eurocomply-dashboard' ),
				'link'     => self::admin_url( 'eurocomply-omnibus' ),
			) ) : array(),
			'score'     => $active ? ( $rows > 0 ? 100 : 60 ) : 0,
		);
	}

	/* -------- Plugin #9 — DSA -------- */
	private static function dsa() : array {
		$active     = class_exists( '\\EuroComply\\DSA\\Plugin' ) || self::table_exists( 'eurocomply_dsa_notices' );
		$notices    = self::table_count( 'eurocomply_dsa_notices' );
		$statements = self::table_count( 'eurocomply_dsa_statements' );
		$traders    = self::table_count( 'eurocomply_dsa_traders' );

		return array(
			'slug'      => 'dsa',
			'name'      => __( 'DSA Transparency', 'eurocomply-dashboard' ),
			'reference' => 'Regulation (EU) 2022/2065',
			'active'    => $active,
			'pro'       => self::is_pro( 'eurocomply_dsa_license' ),
			'menu_url'  => self::admin_url( 'eurocomply-dsa' ),
			'metrics'   => array(
				array( 'label' => __( 'Notices', 'eurocomply-dashboard' ), 'value' => $notices ),
				array( 'label' => __( 'Statements', 'eurocomply-dashboard' ), 'value' => $statements ),
				array( 'label' => __( 'Traders', 'eurocomply-dashboard' ), 'value' => $traders ),
			),
			'alerts'    => array(),
			'score'     => $active ? 80 : 0,
		);
	}

	/* -------- Plugin #10 — Age Verification -------- */
	private static function age_verification() : array {
		$active = class_exists( '\\EuroComply\\AgeVerification\\Plugin' ) || self::table_exists( 'eurocomply_av_verifications' );
		$total  = self::table_count( 'eurocomply_av_verifications' );
		$failed = self::table_count( 'eurocomply_av_verifications', 'passed = %d', array( 0 ) );

		return array(
			'slug'      => 'age-verification',
			'name'      => __( 'Age Verification', 'eurocomply-dashboard' ),
			'reference' => 'JMStV (DE), ARCOM (FR), OSA (UK)',
			'active'    => $active,
			'pro'       => self::is_pro( 'eurocomply_av_license' ),
			'menu_url'  => self::admin_url( 'eurocomply-av' ),
			'metrics'   => array(
				array( 'label' => __( 'Verifications', 'eurocomply-dashboard' ), 'value' => $total ),
				array( 'label' => __( 'Failed', 'eurocomply-dashboard' ), 'value' => $failed ),
			),
			'alerts'    => array(),
			'score'     => $active ? 80 : 0,
		);
	}

	/* -------- Plugin #11 — GDPR DSAR -------- */
	private static function dsar() : array {
		$active   = class_exists( '\\EuroComply\\DSAR\\Plugin' ) || self::table_exists( 'eurocomply_dsar_requests' );
		$total    = self::table_count( 'eurocomply_dsar_requests' );
		$open     = self::table_count( 'eurocomply_dsar_requests', "status NOT IN ('completed','rejected','cancelled')" );
		$alerts   = array();
		$overdue  = 0;
		if ( $active ) {
			global $wpdb;
			$table   = $wpdb->prefix . 'eurocomply_dsar_requests';
			$cutoff  = gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$overdue = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status NOT IN ('completed','rejected','cancelled') AND created_at < %s", $cutoff ) );
			if ( $overdue > 0 ) {
				$alerts[] = array(
					'severity' => 'crit',
					'message'  => sprintf( /* translators: %d count */ _n( '%d DSAR is past the GDPR Art. 12(3) 30-day deadline.', '%d DSARs are past the GDPR Art. 12(3) 30-day deadline.', $overdue, 'eurocomply-dashboard' ), $overdue ),
					'link'     => self::admin_url( 'eurocomply-dsar' ),
				);
			}
		}

		return array(
			'slug'      => 'dsar',
			'name'      => __( 'GDPR DSAR', 'eurocomply-dashboard' ),
			'reference' => 'GDPR Art. 15 / 16 / 17 / 18 / 20 / 21',
			'active'    => $active,
			'pro'       => self::is_pro( 'eurocomply_dsar_license' ),
			'menu_url'  => self::admin_url( 'eurocomply-dsar' ),
			'metrics'   => array(
				array( 'label' => __( 'Requests total', 'eurocomply-dashboard' ), 'value' => $total ),
				array( 'label' => __( 'Open', 'eurocomply-dashboard' ), 'value' => $open ),
				array( 'label' => __( 'Overdue', 'eurocomply-dashboard' ), 'value' => $overdue ),
			),
			'alerts'    => $alerts,
			'score'     => $active ? max( 0, 100 - 25 * min( 4, $overdue ) ) : 0,
		);
	}

	/* -------- Plugin #12 — NIS2 / CRA -------- */
	private static function nis2() : array {
		$active     = class_exists( '\\EuroComply\\NIS2\\Plugin' ) || self::table_exists( 'eurocomply_nis2_incidents' );
		$events     = self::table_count( 'eurocomply_nis2_events' );
		$incidents  = self::table_count( 'eurocomply_nis2_incidents' );
		$open       = self::table_count( 'eurocomply_nis2_incidents', "status NOT IN ('closed','dismissed')" );
		$alerts     = array();
		$overdue_24 = 0;
		$overdue_72 = 0;
		if ( $active ) {
			global $wpdb;
			$table = $wpdb->prefix . 'eurocomply_nis2_incidents';
			// Open incidents whose detected_at is older than 24h and early_warning_sent_at is empty / null.
			$cutoff_24 = gmdate( 'Y-m-d H:i:s', time() - 24 * HOUR_IN_SECONDS );
			$cutoff_72 = gmdate( 'Y-m-d H:i:s', time() - 72 * HOUR_IN_SECONDS );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$overdue_24 = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status NOT IN ('closed','dismissed') AND detected_at < %s AND ( early_warning_sent_at IS NULL OR early_warning_sent_at = '0000-00-00 00:00:00' )", $cutoff_24 ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$overdue_72 = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status NOT IN ('closed','dismissed') AND detected_at < %s AND ( notification_sent_at IS NULL OR notification_sent_at = '0000-00-00 00:00:00' )", $cutoff_72 ) );
			if ( $overdue_24 > 0 ) {
				$alerts[] = array(
					'severity' => 'crit',
					'message'  => sprintf( /* translators: %d count */ _n( '%d incident past the NIS2 Art. 23 24-hour early-warning window.', '%d incidents past the NIS2 Art. 23 24-hour early-warning window.', $overdue_24, 'eurocomply-dashboard' ), $overdue_24 ),
					'link'     => self::admin_url( 'eurocomply-nis2' ),
				);
			}
			if ( $overdue_72 > 0 ) {
				$alerts[] = array(
					'severity' => 'crit',
					'message'  => sprintf( /* translators: %d count */ _n( '%d incident past the NIS2 Art. 23 72-hour notification window.', '%d incidents past the NIS2 Art. 23 72-hour notification window.', $overdue_72, 'eurocomply-dashboard' ), $overdue_72 ),
					'link'     => self::admin_url( 'eurocomply-nis2' ),
				);
			}
		}

		return array(
			'slug'      => 'nis2',
			'name'      => __( 'NIS2 & CRA', 'eurocomply-dashboard' ),
			'reference' => 'Directive (EU) 2022/2555 + CRA',
			'active'    => $active,
			'pro'       => self::is_pro( 'eurocomply_nis2_license' ),
			'menu_url'  => self::admin_url( 'eurocomply-nis2' ),
			'metrics'   => array(
				array( 'label' => __( 'Events', 'eurocomply-dashboard' ), 'value' => $events ),
				array( 'label' => __( 'Incidents', 'eurocomply-dashboard' ), 'value' => $incidents ),
				array( 'label' => __( 'Open', 'eurocomply-dashboard' ), 'value' => $open ),
			),
			'alerts'    => $alerts,
			'score'     => $active ? max( 0, 100 - 30 * ( min( 2, $overdue_24 ) + min( 2, $overdue_72 ) ) ) : 0,
		);
	}

	/* -------- Plugin #13 — R2R / Energy -------- */
	private static function r2r() : array {
		$active    = class_exists( '\\EuroComply\\R2R\\Plugin' ) || self::table_exists( 'eurocomply_r2r_suppliers' );
		$suppliers = self::table_count( 'eurocomply_r2r_suppliers' );
		$repairers = self::table_count( 'eurocomply_r2r_repairers' );

		return array(
			'slug'      => 'r2r',
			'name'      => __( 'Right-to-Repair & Energy Label', 'eurocomply-dashboard' ),
			'reference' => 'Dir. (EU) 2024/1799, ESPR 2024/1781, Reg. 2017/1369',
			'active'    => $active,
			'pro'       => self::is_pro( 'eurocomply_r2r_license' ),
			'menu_url'  => self::admin_url( 'eurocomply-r2r' ),
			'metrics'   => array(
				array( 'label' => __( 'Suppliers', 'eurocomply-dashboard' ), 'value' => $suppliers ),
				array( 'label' => __( 'Repairers', 'eurocomply-dashboard' ), 'value' => $repairers ),
			),
			'alerts'    => array(),
			'score'     => $active ? 80 : 0,
		);
	}

	/* -------- Plugin #14 — AI Act Transparency -------- */
	private static function ai_act() : array {
		$active     = class_exists( '\\EuroComply\\AIAct\\Plugin' ) || self::table_exists( 'eurocomply_aiact_log' );
		$providers  = self::table_count( 'eurocomply_aiact_providers' );
		$log        = self::table_count( 'eurocomply_aiact_log' );
		$ai_marked  = 0;
		if ( $active ) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$ai_marked = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s",
				'_eurocomply_aiact_generated',
				'1'
			) );
		}

		return array(
			'slug'      => 'ai-act',
			'name'      => __( 'AI Act Transparency', 'eurocomply-dashboard' ),
			'reference' => 'Regulation (EU) 2024/1689 Art. 50',
			'active'    => $active,
			'pro'       => self::is_pro( 'eurocomply_ai_act_license' ),
			'menu_url'  => self::admin_url( 'eurocomply-ai-act' ),
			'metrics'   => array(
				array( 'label' => __( 'Providers', 'eurocomply-dashboard' ), 'value' => $providers ),
				array( 'label' => __( 'AI-marked posts', 'eurocomply-dashboard' ), 'value' => $ai_marked ),
				array( 'label' => __( 'Log entries', 'eurocomply-dashboard' ), 'value' => $log ),
			),
			'alerts'    => array(),
			'score'     => $active ? 80 : 0,
		);
	}

	/* -------- Plugin #15 — Whistleblower (Directive (EU) 2019/1937) -------- */
	private static function whistleblower() : array {
		$active = class_exists( '\\EuroComply\\Whistleblower\\Plugin' ) || self::table_exists( 'eurocomply_wb_reports' );
		$total  = self::table_count( 'eurocomply_wb_reports' );
		$open   = self::table_count( 'eurocomply_wb_reports', "status NOT IN ('closed','rejected')" );
		$alerts      = array();
		$ack_overdue = 0;
		$fb_overdue  = 0;

		if ( $active ) {
			global $wpdb;
			$table   = $wpdb->prefix . 'eurocomply_wb_reports';
			$option  = get_option( 'eurocomply_wb_settings', array() );
			$ack_d   = isset( $option['ack_window_days'] ) ? max( 1, (int) $option['ack_window_days'] ) : 7;
			$fb_d    = isset( $option['feedback_window_days'] ) ? max( 1, (int) $option['feedback_window_days'] ) : 90;
			$ack_cut = gmdate( 'Y-m-d H:i:s', time() - $ack_d * DAY_IN_SECONDS );
			$fb_cut  = gmdate( 'Y-m-d H:i:s', time() - $fb_d  * DAY_IN_SECONDS );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$ack_overdue = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status NOT IN ('closed','rejected') AND created_at < %s AND acknowledged_at IS NULL", $ack_cut ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$fb_overdue  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status NOT IN ('closed','rejected') AND created_at < %s AND feedback_sent_at IS NULL", $fb_cut ) );
			if ( $ack_overdue > 0 ) {
				$alerts[] = array(
					'severity' => 'crit',
					'message'  => sprintf( /* translators: %d count */ _n( '%d report past the Whistleblower Art. 9(1)(b) acknowledgement window.', '%d reports past the Whistleblower Art. 9(1)(b) acknowledgement window.', $ack_overdue, 'eurocomply-dashboard' ), $ack_overdue ),
					'link'     => self::admin_url( 'eurocomply-wb' ),
				);
			}
			if ( $fb_overdue > 0 ) {
				$alerts[] = array(
					'severity' => 'crit',
					'message'  => sprintf( /* translators: %d count */ _n( '%d report past the Whistleblower Art. 9(1)(f) feedback window.', '%d reports past the Whistleblower Art. 9(1)(f) feedback window.', $fb_overdue, 'eurocomply-dashboard' ), $fb_overdue ),
					'link'     => self::admin_url( 'eurocomply-wb' ),
				);
			}
		}

		return array(
			'slug'      => 'whistleblower',
			'name'      => __( 'Whistleblower', 'eurocomply-dashboard' ),
			'reference' => 'Directive (EU) 2019/1937',
			'active'    => $active,
			'pro'       => self::is_pro( 'eurocomply_wb_license' ),
			'menu_url'  => self::admin_url( 'eurocomply-wb' ),
			'metrics'   => array(
				array( 'label' => __( 'Reports total', 'eurocomply-dashboard' ), 'value' => $total ),
				array( 'label' => __( 'Open', 'eurocomply-dashboard' ), 'value' => $open ),
				array( 'label' => __( 'Ack overdue', 'eurocomply-dashboard' ), 'value' => $ack_overdue ),
				array( 'label' => __( 'Feedback overdue', 'eurocomply-dashboard' ), 'value' => $fb_overdue ),
			),
			'alerts'    => $alerts,
			'score'     => $active ? max( 0, 100 - 25 * ( min( 2, $ack_overdue ) + min( 2, $fb_overdue ) ) ) : 0,
		);
	}

	/* -------- Plugin #16 — ePrivacy / Tracker Registry -------- */
	private static function eprivacy() : array {
		$active   = class_exists( '\\EuroComply\\EPrivacy\\Plugin' ) || self::table_exists( 'eurocomply_eprivacy_scans' );
		$scans    = self::table_count( 'eurocomply_eprivacy_scans' );
		$findings = self::table_count( 'eurocomply_eprivacy_findings' );
		$cookies  = self::table_count( 'eurocomply_eprivacy_cookies' );
		$alerts   = array();

		if ( $active && 0 === $scans ) {
			$alerts[] = array(
				'severity' => 'warn',
				'message'  => __( 'No tracker scans run yet. Schedule a scan to detect third-party SDKs.', 'eurocomply-dashboard' ),
				'link'     => self::admin_url( 'eurocomply-eprivacy' ),
			);
		}

		return array(
			'slug'      => 'eprivacy',
			'name'      => __( 'ePrivacy & Tracker Registry', 'eurocomply-dashboard' ),
			'reference' => 'Dir. 2002/58/EC + ePrivacy Reg. (proposal)',
			'active'    => $active,
			'pro'       => self::is_pro( 'eurocomply_eprivacy_license' ),
			'menu_url'  => self::admin_url( 'eurocomply-eprivacy' ),
			'metrics'   => array(
				array( 'label' => __( 'Scans', 'eurocomply-dashboard' ), 'value' => $scans ),
				array( 'label' => __( 'Findings', 'eurocomply-dashboard' ), 'value' => $findings ),
				array( 'label' => __( 'Cookies', 'eurocomply-dashboard' ), 'value' => $cookies ),
			),
			'alerts'    => $alerts,
			'score'     => $active ? ( $scans > 0 ? 80 : 50 ) : 0,
		);
	}

	/* -------- Plugin #17 — Pay Transparency (Directive (EU) 2023/970) -------- */
	private static function pay_transparency() : array {
		$active     = class_exists( '\\EuroComply\\PayTransparency\\Plugin' ) || self::table_exists( 'eurocomply_pt_requests' );
		$employees  = self::table_count( 'eurocomply_pt_employees' );
		$requests   = self::table_count( 'eurocomply_pt_requests' );
		$reports    = self::table_count( 'eurocomply_pt_reports' );
		$alerts     = array();
		$overdue    = 0;

		if ( $active ) {
			global $wpdb;
			$table = $wpdb->prefix . 'eurocomply_pt_requests';
			$cut   = gmdate( 'Y-m-d H:i:s', time() - 60 * DAY_IN_SECONDS );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$overdue = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status NOT IN ('responded','rejected') AND created_at < %s", $cut ) );
			if ( $overdue > 0 ) {
				$alerts[] = array(
					'severity' => 'crit',
					'message'  => sprintf( /* translators: %d count */ _n( '%d worker pay-information request past the Art. 7(1) two-month deadline.', '%d worker pay-information requests past the Art. 7(1) two-month deadline.', $overdue, 'eurocomply-dashboard' ), $overdue ),
					'link'     => self::admin_url( 'eurocomply-pay-transparency' ),
				);
			}
		}

		return array(
			'slug'      => 'pay-transparency',
			'name'      => __( 'Pay Transparency', 'eurocomply-dashboard' ),
			'reference' => 'Directive (EU) 2023/970',
			'active'    => $active,
			'pro'       => self::is_pro( 'eurocomply_pay_transparency_license' ),
			'menu_url'  => self::admin_url( 'eurocomply-pay-transparency' ),
			'metrics'   => array(
				array( 'label' => __( 'Employees', 'eurocomply-dashboard' ), 'value' => $employees ),
				array( 'label' => __( 'Info requests', 'eurocomply-dashboard' ), 'value' => $requests ),
				array( 'label' => __( 'Reports', 'eurocomply-dashboard' ), 'value' => $reports ),
				array( 'label' => __( 'Overdue', 'eurocomply-dashboard' ), 'value' => $overdue ),
			),
			'alerts'    => $alerts,
			'score'     => $active ? max( 0, 100 - 25 * min( 4, $overdue ) ) : 0,
		);
	}

	/* -------- Plugin #18 — CBAM -------- */
	private static function cbam() : array {
		$active    = class_exists( '\\EuroComply\\CBAM\\Plugin' ) || self::table_exists( 'eurocomply_cbam_imports' );
		$imports   = self::table_count( 'eurocomply_cbam_imports' );
		$reports   = self::table_count( 'eurocomply_cbam_reports' );
		$verifiers = self::table_count( 'eurocomply_cbam_verifiers' );
		$alerts    = array();
		$unverified = 0;

		if ( $active ) {
			$unverified = self::table_count( 'eurocomply_cbam_imports', 'emissions_verified = %d', array( 0 ) );
			if ( $unverified > 0 ) {
				$alerts[] = array(
					'severity' => 'warn',
					'message'  => sprintf( /* translators: %d count */ _n( '%d CBAM import row uses default emissions (Reg. 2023/1773 transitional).', '%d CBAM import rows use default emissions (Reg. 2023/1773 transitional).', $unverified, 'eurocomply-dashboard' ), $unverified ),
					'link'     => self::admin_url( 'eurocomply-cbam' ),
				);
			}
		}

		return array(
			'slug'      => 'cbam',
			'name'      => __( 'Carbon Border Adjustment (CBAM)', 'eurocomply-dashboard' ),
			'reference' => 'Reg. (EU) 2023/956 + 2023/1773',
			'active'    => $active,
			'pro'       => self::is_pro( 'eurocomply_cbam_license' ),
			'menu_url'  => self::admin_url( 'eurocomply-cbam' ),
			'metrics'   => array(
				array( 'label' => __( 'Imports', 'eurocomply-dashboard' ), 'value' => $imports ),
				array( 'label' => __( 'Reports', 'eurocomply-dashboard' ), 'value' => $reports ),
				array( 'label' => __( 'Verifiers', 'eurocomply-dashboard' ), 'value' => $verifiers ),
				array( 'label' => __( 'Default emissions', 'eurocomply-dashboard' ), 'value' => $unverified ),
			),
			'alerts'    => $alerts,
			'score'     => $active ? ( $imports > 0 ? max( 40, 100 - 5 * min( 12, $unverified ) ) : 60 ) : 0,
		);
	}

	/* -------- Plugin #19 — CSRD / ESRS -------- */
	private static function csrd() : array {
		$active     = class_exists( '\\EuroComply\\CSRD\\Plugin' ) || self::table_exists( 'eurocomply_csrd_materiality' );
		$topics     = self::table_count( 'eurocomply_csrd_materiality' );
		$datapoints = self::table_count( 'eurocomply_csrd_datapoints' );
		$reports    = self::table_count( 'eurocomply_csrd_reports' );
		$assurance  = self::table_count( 'eurocomply_csrd_assurance' );
		$alerts     = array();

		if ( $active && 0 === $topics ) {
			$alerts[] = array(
				'severity' => 'warn',
				'message'  => __( 'Double-materiality assessment empty (ESRS 1 §3).', 'eurocomply-dashboard' ),
				'link'     => self::admin_url( 'eurocomply-csrd-esrs' ),
			);
		}

		return array(
			'slug'      => 'csrd',
			'name'      => __( 'CSRD / ESRS', 'eurocomply-dashboard' ),
			'reference' => 'Dir. (EU) 2022/2464 + ESRS Set 1',
			'active'    => $active,
			'pro'       => self::is_pro( 'eurocomply_csrd_license' ),
			'menu_url'  => self::admin_url( 'eurocomply-csrd-esrs' ),
			'metrics'   => array(
				array( 'label' => __( 'Material topics', 'eurocomply-dashboard' ), 'value' => $topics ),
				array( 'label' => __( 'Data points', 'eurocomply-dashboard' ), 'value' => $datapoints ),
				array( 'label' => __( 'Reports', 'eurocomply-dashboard' ), 'value' => $reports ),
				array( 'label' => __( 'Assurance', 'eurocomply-dashboard' ), 'value' => $assurance ),
			),
			'alerts'    => $alerts,
			'score'     => $active ? ( $topics > 0 ? 80 : 50 ) : 0,
		);
	}

	/* -------- Plugin #20 — PSD2 / SCA -------- */
	private static function psd2() : array {
		$active = class_exists( '\\EuroComply\\PSD2\\Plugin' ) || self::table_exists( 'eurocomply_psd2_transactions' );
		$tx     = self::table_count( 'eurocomply_psd2_transactions' );
		$fraud  = self::table_count( 'eurocomply_psd2_fraud' );
		$cons   = self::table_count( 'eurocomply_psd2_consents' );
		$tpps   = self::table_count( 'eurocomply_psd2_tpps' );
		$alerts = array();

		if ( $active && $fraud > 0 && $tx > 0 ) {
			$rate = ( $fraud / $tx ) * 100;
			if ( $rate > 0.13 ) { // RTS 2018/389 reference fraud-rate threshold (low end).
				$alerts[] = array(
					'severity' => 'warn',
					'message'  => sprintf( /* translators: %s percent */ __( 'Fraud rate %s%% exceeds RTS 2018/389 reference band — review TRA exemption usage.', 'eurocomply-dashboard' ), number_format_i18n( $rate, 2 ) ),
					'link'     => self::admin_url( 'eurocomply-psd2-sca' ),
				);
			}
		}

		return array(
			'slug'      => 'psd2',
			'name'      => __( 'PSD2 / SCA', 'eurocomply-dashboard' ),
			'reference' => 'Dir. (EU) 2015/2366 + RTS 2018/389',
			'active'    => $active,
			'pro'       => self::is_pro( 'eurocomply_psd2_license' ),
			'menu_url'  => self::admin_url( 'eurocomply-psd2-sca' ),
			'metrics'   => array(
				array( 'label' => __( 'Transactions', 'eurocomply-dashboard' ), 'value' => $tx ),
				array( 'label' => __( 'Fraud', 'eurocomply-dashboard' ), 'value' => $fraud ),
				array( 'label' => __( 'Consents', 'eurocomply-dashboard' ), 'value' => $cons ),
				array( 'label' => __( 'TPPs', 'eurocomply-dashboard' ), 'value' => $tpps ),
			),
			'alerts'    => $alerts,
			'score'     => $active ? 80 : 0,
		);
	}

	/* -------- Plugin #21 — EUDR (Reg. (EU) 2023/1115) -------- */
	private static function eudr() : array {
		$active    = class_exists( '\\EuroComply\\EUDR\\Plugin' ) || self::table_exists( 'eurocomply_eudr_shipments' );
		$suppliers = self::table_count( 'eurocomply_eudr_suppliers' );
		$plots     = self::table_count( 'eurocomply_eudr_plots' );
		$shipments = self::table_count( 'eurocomply_eudr_shipments' );
		$risk      = self::table_count( 'eurocomply_eudr_risk' );
		$alerts    = array();
		$high_risk = 0;
		$drafts    = 0;

		if ( $active ) {
			$high_risk = self::table_count( 'eurocomply_eudr_shipments', "risk_level = 'high'" );
			$drafts    = self::table_count( 'eurocomply_eudr_shipments', "dds_status = 'draft'" );
			if ( $high_risk > 0 ) {
				$alerts[] = array(
					'severity' => 'crit',
					'message'  => sprintf( /* translators: %d count */ _n( '%d shipment classified high-risk under EUDR Art. 10 — enhanced due diligence required.', '%d shipments classified high-risk under EUDR Art. 10 — enhanced due diligence required.', $high_risk, 'eurocomply-dashboard' ), $high_risk ),
					'link'     => self::admin_url( 'eurocomply-eudr' ),
				);
			}
			if ( $drafts > 0 ) {
				$alerts[] = array(
					'severity' => 'warn',
					'message'  => sprintf( /* translators: %d count */ _n( '%d EUDR DDS still in draft (not yet submitted to TRACES NT).', '%d EUDR DDS still in draft (not yet submitted to TRACES NT).', $drafts, 'eurocomply-dashboard' ), $drafts ),
					'link'     => self::admin_url( 'eurocomply-eudr' ),
				);
			}
		}

		return array(
			'slug'      => 'eudr',
			'name'      => __( 'EU Deforestation Reg. (EUDR)', 'eurocomply-dashboard' ),
			'reference' => 'Reg. (EU) 2023/1115',
			'active'    => $active,
			'pro'       => self::is_pro( 'eurocomply_eudr_license' ),
			'menu_url'  => self::admin_url( 'eurocomply-eudr' ),
			'metrics'   => array(
				array( 'label' => __( 'Suppliers', 'eurocomply-dashboard' ), 'value' => $suppliers ),
				array( 'label' => __( 'Plots', 'eurocomply-dashboard' ), 'value' => $plots ),
				array( 'label' => __( 'Shipments', 'eurocomply-dashboard' ), 'value' => $shipments ),
				array( 'label' => __( 'Risk rows', 'eurocomply-dashboard' ), 'value' => $risk ),
				array( 'label' => __( 'High-risk', 'eurocomply-dashboard' ), 'value' => $high_risk ),
			),
			'alerts'    => $alerts,
			'score'     => $active ? max( 0, 100 - 30 * min( 2, $high_risk ) - 10 * min( 4, $drafts ) ) : 0,
		);
	}

	private static function is_pro( string $option_key ) : bool {
		$d = get_option( $option_key, array() );
		return is_array( $d ) && ! empty( $d['status'] ) && 'active' === $d['status'];
	}
}
