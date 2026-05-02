<?php
/**
 * Banned-claim scanner — flags unsubstantiated generic environmental claims.
 *
 * @package EuroComply\GreenClaims
 */

declare( strict_types = 1 );

namespace EuroComply\GreenClaims;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Scanner {

	private static ?Scanner $instance = null;

	public static function instance() : Scanner {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register();
		}
		return self::$instance;
	}

	private function register() : void {
		add_filter( 'the_content', array( $this, 'maybe_disclaim' ), 50 );
	}

	/**
	 * @return array<int,array{phrase:string,offset:int}>
	 */
	public static function find_phrases( string $text ) : array {
		$phrases = Settings::banned_phrases();
		$lower   = mb_strtolower( $text );
		$found   = array();
		foreach ( $phrases as $p ) {
			$offset = mb_stripos( $lower, $p );
			if ( false !== $offset ) {
				$found[] = array( 'phrase' => $p, 'offset' => (int) $offset );
			}
		}
		return $found;
	}

	/**
	 * Returns array of banned phrases that have no verified ClaimStore row for the given post.
	 *
	 * @return array<int,string>
	 */
	public static function unsubstantiated( int $post_id, string $text ) : array {
		$found = self::find_phrases( $text );
		if ( empty( $found ) ) {
			return array();
		}
		$verified = ClaimStore::by_product( $post_id, 'verified' );
		if ( empty( $verified ) ) {
			return array_unique( wp_list_pluck( $found, 'phrase' ) );
		}
		$verified_text = mb_strtolower( implode( ' | ', wp_list_pluck( $verified, 'claim_text' ) ) );
		$out           = array();
		foreach ( $found as $f ) {
			if ( false === mb_stripos( $verified_text, $f['phrase'] ) ) {
				$out[] = $f['phrase'];
			}
		}
		return array_values( array_unique( $out ) );
	}

	public function maybe_disclaim( string $content ) : string {
		$d = Settings::get();
		if ( empty( $d['enable_scanner'] ) ) {
			return $content;
		}
		if ( ! is_singular() ) {
			return $content;
		}
		$post_id = (int) get_queried_object_id();
		if ( ! $post_id ) {
			return $content;
		}
		$missing = self::unsubstantiated( $post_id, wp_strip_all_tags( $content ) );
		if ( empty( $missing ) ) {
			return $content;
		}
		if ( ! empty( $d['block_unverified'] ) ) {
			$notice = '<p class="eurocomply-gc-blocked"><strong>' . esc_html__( 'Notice:', 'eurocomply-green-claims' ) . '</strong> ' . esc_html__( 'Unsubstantiated environmental claims have been suppressed pending verification (Dir. (EU) 2024/825).', 'eurocomply-green-claims' ) . '</p>';
			$lower  = mb_strtolower( $content );
			foreach ( $missing as $phrase ) {
				$pos = mb_stripos( $lower, $phrase );
				while ( false !== $pos ) {
					$content = mb_substr( $content, 0, $pos ) . str_repeat( '·', mb_strlen( $phrase ) ) . mb_substr( $content, $pos + mb_strlen( $phrase ) );
					$lower   = mb_strtolower( $content );
					$pos     = mb_stripos( $lower, $phrase );
				}
			}
			return $notice . $content;
		}
		$disclaimer  = '<aside class="eurocomply-gc-disclaimer"><p>';
		$disclaimer .= sprintf(
			/* translators: %s: comma-separated phrases. */
			esc_html__( 'Generic environmental claims (%s) on this page are not yet third-party substantiated. The operator is working on verification per Dir. (EU) 2024/825.', 'eurocomply-green-claims' ),
			esc_html( implode( ', ', $missing ) )
		);
		$disclaimer .= '</p></aside>';
		return $content . $disclaimer;
	}
}
