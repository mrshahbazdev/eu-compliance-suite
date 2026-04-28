<?php
/**
 * Bridge from EuroComply ePrivacy & Tracker Registry (#16) to
 * EuroComply Cookie Consent (#2).
 *
 * Detects whether the sister plugin is active, maps ePrivacy
 * tracker categories onto Cookie Consent categories, and pushes
 * the latest scan's findings into Cookie Consent's tracker
 * inventory via its public `Settings::merge_trackers()` API.
 *
 * The bridge degrades gracefully when the sister plugin is not
 * installed: every method is safe to call with no side effects.
 *
 * @package EuroComply\EPrivacy
 */

declare( strict_types = 1 );

namespace EuroComply\EPrivacy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CookieConsentBridge {

	/**
	 * Cookie Consent's `Settings` class as detected at runtime.
	 *
	 * Kept loose as a string (not a use statement) so this plugin can be
	 * activated and lint-clean even when wp-cookie-consent isn't installed.
	 */
	private const CC_SETTINGS_FQN = '\\EuroComply\\CookieConsent\\Settings';

	/**
	 * Maps ePrivacy tracker categories onto Cookie Consent category slugs.
	 *
	 * Cookie Consent's defaults are `necessary` / `preferences` /
	 * `statistics` / `marketing`. We deliberately treat anything we don't
	 * recognise as `marketing` (the most restrictive non-essential bucket)
	 * so the bridge errs on the side of requiring consent.
	 *
	 * @return array<string,string>
	 */
	public static function category_map() : array {
		return array(
			'analytics'   => 'statistics',
			'advertising' => 'marketing',
			'social'      => 'marketing',
			'functional'  => 'preferences',
			'preferences' => 'preferences',
		);
	}

	/**
	 * Whether the Cookie Consent (#2) sister plugin is installed and
	 * exposes the bridge's merge API.
	 */
	public static function is_active() : bool {
		return class_exists( self::CC_SETTINGS_FQN )
			&& method_exists( self::CC_SETTINGS_FQN, 'merge_trackers' );
	}

	/**
	 * Push every tracker observed on the latest scan into the Cookie
	 * Consent inventory.
	 *
	 * Returns an array describing the operation so the admin handler can
	 * show a contextual notice.
	 *
	 * @return array{ok:bool,reason:string,added:int,sent:int}
	 */
	public static function apply_findings() : array {
		if ( ! self::is_active() ) {
			return array(
				'ok'     => false,
				'reason' => 'sister-plugin-missing',
				'added'  => 0,
				'sent'   => 0,
			);
		}

		$slugs = FindingStore::distinct_slugs_latest();
		if ( ! $slugs ) {
			return array(
				'ok'     => true,
				'reason' => 'no-findings',
				'added'  => 0,
				'sent'   => 0,
			);
		}

		$map  = self::category_map();
		$rows = array();
		foreach ( $slugs as $slug ) {
			$reg = TrackerRegistry::get( (string) $slug );
			if ( ! $reg ) {
				continue;
			}
			if ( empty( $reg['consent_required'] ) ) {
				// Strictly necessary trackers don't need a consent entry.
				continue;
			}
			$source_cat  = (string) $reg['category'];
			$cc_category = $map[ $source_cat ] ?? 'marketing';
			$rows[]      = array(
				'slug'        => (string) $slug,
				'name'        => (string) $reg['name'],
				'vendor'      => (string) ( $reg['vendor'] ?? '' ),
				'category'    => $source_cat,
				'cc_category' => $cc_category,
			);
		}

		if ( ! $rows ) {
			return array(
				'ok'     => true,
				'reason' => 'no-consent-required-rows',
				'added'  => 0,
				'sent'   => 0,
			);
		}

		$added = (int) call_user_func(
			array( self::CC_SETTINGS_FQN, 'merge_trackers' ),
			$rows,
			'eprivacy'
		);

		return array(
			'ok'     => true,
			'reason' => 'ok',
			'added'  => $added,
			'sent'   => count( $rows ),
		);
	}
}
