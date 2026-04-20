<?php
/**
 * Google Consent Mode v2 integration.
 *
 * @package EuroComply\CookieConsent
 */

declare( strict_types = 1 );

namespace EuroComply\CookieConsent;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Emits the default Consent Mode v2 signal as early in `<head>` as possible so
 * Google tags load with `denied` defaults before anything else runs.
 */
final class Gcm {

	private static ?Gcm $instance = null;

	public static function instance() : Gcm {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}
		return self::$instance;
	}

	private function boot() : void {
		// Priority 1 on wp_head so the default command lands before any GA / Meta / Ads snippets.
		add_action( 'wp_head', array( $this, 'print_default_signal' ), 1 );
	}

	/**
	 * Print the dataLayer init + `consent default` call.
	 */
	public function print_default_signal() : void {
		if ( is_admin() ) {
			return;
		}
		$settings = Settings::get();
		if ( empty( $settings['gcm_enabled'] ) ) {
			return;
		}

		$wait_for_update      = (int) ( $settings['gcm_wait_for_update'] ?? 500 );
		$ads_data_redaction   = ! empty( $settings['gcm_ads_data_redaction'] );
		$url_passthrough      = ! empty( $settings['gcm_url_passthrough'] );

		$defaults = array(
			'ad_storage'             => 'denied',
			'ad_user_data'           => 'denied',
			'ad_personalization'     => 'denied',
			'analytics_storage'      => 'denied',
			'functionality_storage'  => 'denied',
			'personalization_storage' => 'denied',
			'security_storage'       => 'granted',
			'wait_for_update'        => $wait_for_update,
		);

		$region_signal = strtoupper( (string) ( $settings['regions'] ?? 'eea' ) );
		if ( 'EEA' === $region_signal ) {
			$defaults['region'] = array( 'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE', 'IS', 'LI', 'NO', 'CH', 'GB' );
		}

		$defaults_json = wp_json_encode( $defaults );
		if ( false === $defaults_json ) {
			return;
		}

		echo "<!-- EuroComply Cookie Consent: Google Consent Mode v2 default -->\n";
		echo "<script id=\"eurocomply-cc-gcm-default\">\n";
		echo "window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}\n";
		echo "gtag('consent','default'," . $defaults_json . ");\n";
		if ( $url_passthrough ) {
			echo "gtag('set','url_passthrough',true);\n";
		}
		if ( $ads_data_redaction ) {
			echo "gtag('set','ads_data_redaction',true);\n";
		}
		echo "</script>\n";
	}
}
