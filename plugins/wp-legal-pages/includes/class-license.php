<?php
/**
 * License — Pro license stub.
 *
 * For the 0.1.0 MVP this is a local-only key check: any non-empty key saved
 * unlocks Pro features. Before public launch, replace verify() with a real
 * HTTPS call to the EuroComply licensing server.
 *
 * @package EuroComply\LegalPages
 */

namespace EuroComply\LegalPages;

defined( 'ABSPATH' ) || exit;

class License {

	const OPTION_KEY = 'eurocomply_legal_license';

	public function get_key() {
		$stored = get_option( self::OPTION_KEY, array() );
		return is_array( $stored ) && ! empty( $stored['key'] ) ? (string) $stored['key'] : '';
	}

	public function save_key( $key ) {
		$key = sanitize_text_field( (string) $key );
		update_option(
			self::OPTION_KEY,
			array(
				'key'         => $key,
				'verified_at' => time(),
				'status'      => $this->verify( $key ) ? 'active' : 'invalid',
			),
			false
		);
	}

	public function status() {
		$stored = get_option( self::OPTION_KEY, array() );
		return is_array( $stored ) && ! empty( $stored['status'] ) ? (string) $stored['status'] : 'inactive';
	}

	/**
	 * Verify a license key.
	 *
	 * MVP: accepts any key matching /^EC-[A-Z0-9]{6,}$/ as valid.
	 * Production: replace with remote validation against licensing API.
	 */
	public function verify( $key ) {
		$key = (string) $key;
		if ( '' === $key ) {
			return false;
		}
		return (bool) preg_match( '/^EC-[A-Z0-9]{6,}$/', $key );
	}

	public function is_pro() {
		if ( defined( 'EUROCOMPLY_LEGAL_PRO' ) && EUROCOMPLY_LEGAL_PRO ) {
			return true;
		}
		return 'active' === $this->status();
	}
}
