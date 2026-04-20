<?php
/**
 * Templates — registry of (country, type) → template file.
 *
 * @package EuroComply\LegalPages
 */

namespace EuroComply\LegalPages;

defined( 'ABSPATH' ) || exit;

class Templates {

	const TYPE_IMPRESSUM   = 'impressum';
	const TYPE_DATENSCHUTZ = 'datenschutz';
	const TYPE_AGB         = 'agb';
	const TYPE_WIDERRUF    = 'widerruf';

	/** Types available on the Free tier. */
	const FREE_TYPES = array( self::TYPE_IMPRESSUM, self::TYPE_DATENSCHUTZ );

	/** Countries available on the Free tier. */
	const FREE_COUNTRIES = array( 'DE', 'AT', 'CH' );

	public function types() {
		return array(
			self::TYPE_IMPRESSUM   => __( 'Impressum / Legal notice', 'eurocomply-legal' ),
			self::TYPE_DATENSCHUTZ => __( 'Datenschutzerklärung / Privacy policy', 'eurocomply-legal' ),
			self::TYPE_AGB         => __( 'AGB / General terms (Pro)', 'eurocomply-legal' ),
			self::TYPE_WIDERRUF    => __( 'Widerrufsbelehrung / Withdrawal notice (Pro)', 'eurocomply-legal' ),
		);
	}

	public function is_free_type( $type ) {
		return in_array( $type, self::FREE_TYPES, true );
	}

	public function is_free_country( $country ) {
		return in_array( $country, self::FREE_COUNTRIES, true );
	}

	/**
	 * Resolve a template file path for (country, type).
	 *
	 * Falls back: requested country → "en" (Pro EU generic) → null.
	 *
	 * @return string|null absolute file path or null if not found
	 */
	public function resolve( $country, $type ) {
		$country = strtoupper( (string) $country );
		$type    = strtolower( (string) $type );

		$base = EUROCOMPLY_LEGAL_DIR . 'templates/' . $type . '/';
		$candidates = array(
			$base . strtolower( $country ) . '.php',
			$base . 'en.php', // generic EU fallback (Pro-built)
		);
		foreach ( $candidates as $candidate ) {
			if ( file_exists( $candidate ) ) {
				return $candidate;
			}
		}
		return null;
	}
}
