<?php
/**
 * Static HTML scanner: fetches each configured URL and matches the
 * response body against the tracker registry.
 *
 * @package EuroComply\EPrivacy
 */

declare( strict_types = 1 );

namespace EuroComply\EPrivacy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Scanner {

	/**
	 * @return array{scan_id:int,urls:int,findings:int,errors:array<int,string>}
	 */
	public static function run() : array {
		$settings = Settings::get();
		$urls     = self::resolve_urls( (array) $settings['scan_urls'] );
		$scan_id  = ScanStore::start();

		$total_findings = 0;
		$errors         = array();

		foreach ( $urls as $url ) {
			$response = wp_safe_remote_get(
				$url,
				array(
					'timeout'     => (int) $settings['http_timeout'],
					'redirection' => $settings['follow_redirects'] ? 5 : 0,
					'sslverify'   => true,
					'user-agent'  => (string) $settings['http_user_agent'],
					'headers'     => array(
						'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
						'Accept-Language' => 'en-GB,en;q=0.7',
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				$errors[] = sprintf( '%s: %s', $url, $response->get_error_message() );
				continue;
			}
			$code = (int) wp_remote_retrieve_response_code( $response );
			if ( $code < 200 || $code >= 400 ) {
				$errors[] = sprintf( '%s: HTTP %d', $url, $code );
				continue;
			}

			$body = (string) wp_remote_retrieve_body( $response );
			$hits = TrackerRegistry::match_html( $body );
			foreach ( $hits as $slug => $evidence ) {
				$row = TrackerRegistry::get( (string) $slug );
				FindingStore::record(
					$scan_id,
					$url,
					(string) $slug,
					$row ? (string) $row['category'] : '',
					(string) $evidence
				);
				$total_findings++;
			}
		}

		ScanStore::finish(
			$scan_id,
			count( $urls ),
			$total_findings,
			0,
			implode( ' | ', array_slice( $errors, 0, 5 ) )
		);

		return array(
			'scan_id'  => $scan_id,
			'urls'     => count( $urls ),
			'findings' => $total_findings,
			'errors'   => $errors,
		);
	}

	/**
	 * Convert relative paths like "/" into absolute home_url() URLs and drop
	 * anything that doesn't resolve to a usable URL.
	 *
	 * @param array<int,string> $entries
	 * @return array<int,string>
	 */
	public static function resolve_urls( array $entries ) : array {
		$out = array();
		foreach ( $entries as $entry ) {
			$entry = trim( (string) $entry );
			if ( '' === $entry ) {
				continue;
			}
			if ( '/' === substr( $entry, 0, 1 ) ) {
				$out[] = home_url( $entry );
			} elseif ( filter_var( $entry, FILTER_VALIDATE_URL ) ) {
				$out[] = $entry;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Compliance gap: trackers detected on the latest scan but missing from
	 * the EuroComply Cookie Consent #2 plugin's category map (best-effort
	 * sister-plugin integration; gracefully degrades when #2 is not active).
	 *
	 * @return array<int,array{slug:string,name:string,category:string}>
	 */
	public static function compliance_gaps() : array {
		$slugs    = FindingStore::distinct_slugs_latest();
		$declared = self::declared_categories_in_consent();
		$gaps     = array();
		foreach ( $slugs as $slug ) {
			$row = TrackerRegistry::get( (string) $slug );
			if ( ! $row || ! $row['consent_required'] ) {
				continue;
			}
			$cat = (string) $row['category'];
			if ( ! in_array( $cat, $declared, true ) ) {
				$gaps[] = array(
					'slug'     => (string) $slug,
					'name'     => (string) $row['name'],
					'category' => $cat,
				);
			}
		}
		return $gaps;
	}

	/**
	 * @return array<int,string>
	 */
	private static function declared_categories_in_consent() : array {
		// EuroComply Cookie Consent #2 stores its settings under
		// `eurocomply_cc_settings`. We also accept two earlier option keys
		// for backward compatibility with sites running pre-release builds.
		$o = get_option( 'eurocomply_cc_settings' );
		if ( ! is_array( $o ) ) {
			$o = get_option( 'eurocomply_cookie_consent_settings' );
		}
		if ( ! is_array( $o ) ) {
			$o = get_option( 'eurocomply_cookie_settings' );
		}
		if ( ! is_array( $o ) ) {
			return array_keys( TrackerRegistry::categories() );
		}
		$cats = array();
		if ( isset( $o['categories'] ) && is_array( $o['categories'] ) ) {
			foreach ( $o['categories'] as $k => $row ) {
				if ( is_array( $row ) && isset( $row['enabled'] ) && empty( $row['enabled'] ) ) {
					// Skip categories the operator has explicitly disabled.
					continue;
				}
				$cats[] = (string) $k;
			}
		}
		return $cats ? $cats : array_keys( TrackerRegistry::categories() );
	}
}
