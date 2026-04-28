<?php
/**
 * Settings store for EuroComply Cookie Consent.
 *
 * @package EuroComply\CookieConsent
 */

declare( strict_types = 1 );

namespace EuroComply\CookieConsent;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persists and retrieves plugin settings.
 */
final class Settings {

	public const OPTION_KEY = 'eurocomply_cc_settings';

	/**
	 * Return default settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() : array {
		return array(
			'banner_position'        => 'bottom',
			'banner_layout'          => 'box',
			'banner_color_bg'        => '#111827',
			'banner_color_text'      => '#f9fafb',
			'banner_color_accent'    => '#2563eb',
			'show_reject_button'     => '1',
			'show_preferences_link'  => '1',
			'primary_language'       => 'en',
			'auto_language'          => '1',
			'consent_days'           => 180,
			'consent_version'        => '1',
			'regions'                => 'eea',
			'gcm_enabled'            => '1',
			'gcm_ads_data_redaction' => '1',
			'gcm_url_passthrough'    => '1',
			'gcm_wait_for_update'    => 500,
			'ga4_id'                 => '',
			'meta_pixel_id'          => '',
			'google_ads_id'          => '',
			'privacy_policy_page'    => 0,
			'imprint_page'           => 0,
			'categories'             => self::default_categories(),
			'tracker_inventory'      => array(),
			'text_en'                => self::default_text_en(),
			'text_de'                => self::default_text_de(),
		);
	}

	/**
	 * Public read accessor for the tracker inventory (slug => row).
	 *
	 * Populated by sister plugins such as EuroComply ePrivacy & Tracker
	 * Registry; safe to call when no inventory has been seeded.
	 *
	 * @return array<string,array{name:string,vendor:string,category:string,cc_category:string,source:string,last_seen:string}>
	 */
	public static function tracker_inventory() : array {
		$s = self::get();
		$inv = isset( $s['tracker_inventory'] ) && is_array( $s['tracker_inventory'] ) ? $s['tracker_inventory'] : array();
		$out = array();
		foreach ( $inv as $slug => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$out[ (string) $slug ] = array(
				'name'        => isset( $row['name'] ) ? (string) $row['name'] : (string) $slug,
				'vendor'      => isset( $row['vendor'] ) ? (string) $row['vendor'] : '',
				'category'    => isset( $row['category'] ) ? (string) $row['category'] : '',
				'cc_category' => isset( $row['cc_category'] ) ? (string) $row['cc_category'] : '',
				'source'      => isset( $row['source'] ) ? (string) $row['source'] : '',
				'last_seen'   => isset( $row['last_seen'] ) ? (string) $row['last_seen'] : '',
			);
		}
		return $out;
	}

	/**
	 * Sister-plugin merge API used by the ePrivacy scanner bridge.
	 *
	 * Each input row must have at least `slug` and `cc_category`; everything
	 * else is normalised. New rows are added; existing rows have `last_seen`
	 * refreshed and `cc_category` overwritten with the latest mapping.
	 *
	 * @param array<int,array<string,mixed>> $rows  Tracker rows from sister plugin.
	 * @param string                          $source Origin tag (e.g. "eprivacy").
	 * @return int Number of rows added (i.e. genuinely new slugs).
	 */
	public static function merge_trackers( array $rows, string $source = 'eprivacy' ) : int {
		$known    = self::default_categories();
		$cc_keys  = array_keys( $known );
		$current  = self::get();
		$existing = isset( $current['tracker_inventory'] ) && is_array( $current['tracker_inventory'] ) ? $current['tracker_inventory'] : array();
		$added    = 0;
		$now      = current_time( 'mysql' );

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$slug = isset( $row['slug'] ) ? sanitize_key( (string) $row['slug'] ) : '';
			if ( '' === $slug ) {
				continue;
			}
			$cc_category = isset( $row['cc_category'] ) ? sanitize_key( (string) $row['cc_category'] ) : '';
			if ( ! in_array( $cc_category, $cc_keys, true ) ) {
				$cc_category = 'marketing';
			}
			$is_new = ! isset( $existing[ $slug ] );
			$existing[ $slug ] = array(
				'name'        => isset( $row['name'] ) ? sanitize_text_field( (string) $row['name'] ) : $slug,
				'vendor'      => isset( $row['vendor'] ) ? sanitize_text_field( (string) $row['vendor'] ) : '',
				'category'    => isset( $row['category'] ) ? sanitize_key( (string) $row['category'] ) : '',
				'cc_category' => $cc_category,
				'source'      => sanitize_key( $source ),
				'last_seen'   => $now,
			);
			if ( $is_new ) {
				$added++;
			}
		}

		$current['tracker_inventory'] = $existing;
		update_option( self::OPTION_KEY, $current, false );
		return $added;
	}

	/**
	 * Category definitions with their Consent Mode v2 signal mapping.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function default_categories() : array {
		return array(
			'necessary'   => array(
				'label'       => __( 'Necessary', 'eurocomply-cookie-consent' ),
				'description' => __( 'Required for basic site functions such as security, network management and the consent cookie itself.', 'eurocomply-cookie-consent' ),
				'enabled'     => '1',
				'locked'      => '1',
				'gcm'         => array( 'security_storage' ),
			),
			'preferences' => array(
				'label'       => __( 'Preferences', 'eurocomply-cookie-consent' ),
				'description' => __( 'Remembers choices you make such as language, region or UI preferences.', 'eurocomply-cookie-consent' ),
				'enabled'     => '1',
				'locked'      => '',
				'gcm'         => array( 'functionality_storage', 'personalization_storage' ),
			),
			'statistics'  => array(
				'label'       => __( 'Statistics', 'eurocomply-cookie-consent' ),
				'description' => __( 'Helps us understand how visitors interact with the site by collecting anonymous analytics data.', 'eurocomply-cookie-consent' ),
				'enabled'     => '1',
				'locked'      => '',
				'gcm'         => array( 'analytics_storage' ),
			),
			'marketing'   => array(
				'label'       => __( 'Marketing', 'eurocomply-cookie-consent' ),
				'description' => __( 'Used to deliver advertising and measure the effectiveness of ad campaigns across sites.', 'eurocomply-cookie-consent' ),
				'enabled'     => '1',
				'locked'      => '',
				'gcm'         => array( 'ad_storage', 'ad_user_data', 'ad_personalization' ),
			),
		);
	}

	/**
	 * Default English banner copy.
	 *
	 * @return array<string,string>
	 */
	public static function default_text_en() : array {
		return array(
			'title'       => __( 'We value your privacy', 'eurocomply-cookie-consent' ),
			'body'        => __( 'We use cookies to improve your experience, analyse traffic and deliver personalised content. Choose which categories you consent to.', 'eurocomply-cookie-consent' ),
			'accept_all'  => __( 'Accept all', 'eurocomply-cookie-consent' ),
			'reject_all'  => __( 'Reject non-essential', 'eurocomply-cookie-consent' ),
			'customize'   => __( 'Preferences', 'eurocomply-cookie-consent' ),
			'save'        => __( 'Save preferences', 'eurocomply-cookie-consent' ),
			'policy_link' => __( 'Privacy policy', 'eurocomply-cookie-consent' ),
		);
	}

	/**
	 * Default German banner copy.
	 *
	 * @return array<string,string>
	 */
	public static function default_text_de() : array {
		return array(
			'title'       => 'Wir respektieren Ihre Privatsphäre',
			'body'        => 'Wir verwenden Cookies, um Ihr Erlebnis zu verbessern, Zugriffe auszuwerten und personalisierte Inhalte anzubieten. Wählen Sie, welchen Kategorien Sie zustimmen.',
			'accept_all'  => 'Alle akzeptieren',
			'reject_all'  => 'Nur notwendige',
			'customize'   => 'Einstellungen',
			'save'        => 'Auswahl speichern',
			'policy_link' => 'Datenschutzerklärung',
		);
	}

	/**
	 * Retrieve the stored settings, merged with defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function get() : array {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		$merged = array_replace_recursive( self::defaults(), $stored );
		// array_replace_recursive leaves stale numeric keys; normalise categories order.
		$merged['categories'] = array_merge( self::default_categories(), is_array( $merged['categories'] ?? null ) ? $merged['categories'] : array() );
		return $merged;
	}

	/**
	 * Seed defaults on activation if no settings stored.
	 */
	public static function seed_defaults() : void {
		if ( false === get_option( self::OPTION_KEY, false ) ) {
			update_option( self::OPTION_KEY, self::defaults(), false );
		}
	}

	/**
	 * Sanitise and persist a raw settings submission.
	 *
	 * @param array<string,mixed> $raw Unfiltered input.
	 * @return array<string,mixed> Stored sanitised payload.
	 */
	public static function save( array $raw ) : array {
		$defaults = self::defaults();
		$clean    = self::get();

		$positions   = array( 'bottom', 'top', 'modal' );
		$layouts     = array( 'box', 'bar' );
		$regions     = array( 'eea', 'world', 'off' );
		$languages   = array( 'en', 'de' );

		if ( isset( $raw['banner_position'] ) && in_array( $raw['banner_position'], $positions, true ) ) {
			$clean['banner_position'] = $raw['banner_position'];
		}
		if ( isset( $raw['banner_layout'] ) && in_array( $raw['banner_layout'], $layouts, true ) ) {
			$clean['banner_layout'] = $raw['banner_layout'];
		}
		foreach ( array( 'banner_color_bg', 'banner_color_text', 'banner_color_accent' ) as $color_key ) {
			if ( isset( $raw[ $color_key ] ) ) {
				$color = sanitize_hex_color( $raw[ $color_key ] );
				if ( $color ) {
					$clean[ $color_key ] = $color;
				}
			}
		}
		$clean['show_reject_button']    = ! empty( $raw['show_reject_button'] ) ? '1' : '';
		$clean['show_preferences_link'] = ! empty( $raw['show_preferences_link'] ) ? '1' : '';
		$clean['auto_language']         = ! empty( $raw['auto_language'] ) ? '1' : '';
		$clean['gcm_enabled']           = ! empty( $raw['gcm_enabled'] ) ? '1' : '';
		$clean['gcm_ads_data_redaction'] = ! empty( $raw['gcm_ads_data_redaction'] ) ? '1' : '';
		$clean['gcm_url_passthrough']   = ! empty( $raw['gcm_url_passthrough'] ) ? '1' : '';

		if ( isset( $raw['primary_language'] ) && in_array( $raw['primary_language'], $languages, true ) ) {
			$clean['primary_language'] = $raw['primary_language'];
		}
		if ( isset( $raw['regions'] ) && in_array( $raw['regions'], $regions, true ) ) {
			$clean['regions'] = $raw['regions'];
		}
		if ( isset( $raw['consent_days'] ) ) {
			$days = (int) $raw['consent_days'];
			if ( $days >= 1 && $days <= 365 ) {
				$clean['consent_days'] = $days;
			}
		}
		if ( isset( $raw['gcm_wait_for_update'] ) ) {
			$wait = (int) $raw['gcm_wait_for_update'];
			if ( $wait >= 0 && $wait <= 5000 ) {
				$clean['gcm_wait_for_update'] = $wait;
			}
		}

		foreach ( array( 'ga4_id', 'meta_pixel_id', 'google_ads_id' ) as $id_key ) {
			if ( isset( $raw[ $id_key ] ) ) {
				$clean[ $id_key ] = sanitize_text_field( (string) $raw[ $id_key ] );
			}
		}
		foreach ( array( 'privacy_policy_page', 'imprint_page' ) as $page_key ) {
			if ( isset( $raw[ $page_key ] ) ) {
				$clean[ $page_key ] = max( 0, (int) $raw[ $page_key ] );
			}
		}

		if ( ! empty( $raw['categories'] ) && is_array( $raw['categories'] ) ) {
			$clean['categories'] = self::sanitize_categories( $raw['categories'], $clean['categories'] );
		}

		foreach ( array( 'text_en', 'text_de' ) as $text_key ) {
			if ( isset( $raw[ $text_key ] ) && is_array( $raw[ $text_key ] ) ) {
				$clean[ $text_key ] = self::sanitize_text_block( $raw[ $text_key ], $defaults[ $text_key ] );
			}
		}

		// Bump consent_version when categories or required-consent fields change; lets sites re-prompt users.
		if ( ! empty( $raw['bump_consent_version'] ) ) {
			$clean['consent_version'] = (string) ( (int) ( $clean['consent_version'] ?? 1 ) + 1 );
		}

		update_option( self::OPTION_KEY, $clean, false );
		return $clean;
	}

	/**
	 * Clamp category input to the known schema.
	 *
	 * @param array<string,mixed> $input    Raw POST data.
	 * @param array<string,mixed> $existing Current stored categories.
	 * @return array<string,mixed>
	 */
	private static function sanitize_categories( array $input, array $existing ) : array {
		foreach ( $existing as $slug => $row ) {
			if ( 'necessary' === $slug ) {
				$existing[ $slug ]['enabled'] = '1';
				$existing[ $slug ]['locked']  = '1';
				continue;
			}
			$enabled                      = ! empty( $input[ $slug ]['enabled'] ) ? '1' : '';
			$existing[ $slug ]['enabled'] = $enabled;
		}
		return $existing;
	}

	/**
	 * Sanitise a single language text block.
	 *
	 * @param array<string,mixed> $input    Raw POST data.
	 * @param array<string,string> $defaults Default copy.
	 * @return array<string,string>
	 */
	private static function sanitize_text_block( array $input, array $defaults ) : array {
		$out = array();
		foreach ( $defaults as $key => $fallback ) {
			$value       = isset( $input[ $key ] ) ? (string) $input[ $key ] : '';
			$out[ $key ] = '' !== trim( $value ) ? sanitize_text_field( $value ) : $fallback;
		}
		return $out;
	}
}
