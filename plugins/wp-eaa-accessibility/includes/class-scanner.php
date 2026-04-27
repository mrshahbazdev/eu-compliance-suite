<?php
/**
 * HTML scanner — runs machine-checkable WCAG rules against a rendered document.
 *
 * @package EuroComply\Eaa
 */

declare( strict_types = 1 );

namespace EuroComply\Eaa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Scanner {

	private static ?Scanner $instance = null;

	public static function instance() : Scanner {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function hooks() : void {
		add_action( 'save_post', array( $this, 'on_save_post' ), 20, 3 );
	}

	public function on_save_post( int $post_id, \WP_Post $post, bool $update ) : void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( 'publish' !== $post->post_status ) {
			return;
		}
		if ( ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
			return;
		}
		if ( empty( Settings::value( 'scan_on_save', 1 ) ) ) {
			return;
		}

		$url = get_permalink( $post_id );
		if ( ! is_string( $url ) || '' === $url ) {
			return;
		}

		$this->scan_url_into_store( $url, 'post', $post_id );
	}

	/**
	 * Fetch $url, run enabled rules, write issues to store.
	 *
	 * @return array{issues:array<int,array<string,string>>,fetched:bool,status:int}
	 */
	public function scan_url_into_store( string $url, string $object_type, int $object_id ) : array {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'   => 15,
				'sslverify' => apply_filters( 'eurocomply_eaa_sslverify', false ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return array( 'issues' => array(), 'fetched' => false, 'status' => 0 );
		}
		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = (string) wp_remote_retrieve_body( $response );
		if ( $status < 200 || $status >= 400 || '' === $body ) {
			return array( 'issues' => array(), 'fetched' => false, 'status' => $status );
		}

		$issues = $this->scan_html( $body );
		IssueStore::replace_for_object( $object_type, $object_id, $url, $issues );

		return array( 'issues' => $issues, 'fetched' => true, 'status' => $status );
	}

	/**
	 * Run rules against raw HTML. Pure — no WP / DB access.
	 *
	 * @return array<int,array<string,string>>
	 */
	public function scan_html( string $html ) : array {
		$enabled = (array) Settings::value( 'enabled_rules', array_keys( Rules::all() ) );
		$catalog = Rules::all();
		$issues  = array();

		if ( '' === trim( $html ) ) {
			return $issues;
		}

		libxml_use_internal_errors( true );
		$dom = new \DOMDocument();
		$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING );
		libxml_clear_errors();
		$xpath = new \DOMXPath( $dom );

		$add = static function ( string $rule, string $snippet ) use ( &$issues, $enabled, $catalog ) : void {
			if ( ! in_array( $rule, $enabled, true ) || ! isset( $catalog[ $rule ] ) ) {
				return;
			}
			$issues[] = array(
				'rule'     => $rule,
				'wcag'     => $catalog[ $rule ]['wcag'],
				'severity' => $catalog[ $rule ]['severity'],
				'snippet'  => self::truncate( $snippet, 240 ),
			);
		};

		// html_lang_missing.
		$html_nodes = $dom->getElementsByTagName( 'html' );
		if ( $html_nodes->length > 0 ) {
			$lang = trim( (string) $html_nodes->item( 0 )->getAttribute( 'lang' ) );
			if ( '' === $lang ) {
				$add( 'html_lang_missing', '<html>' );
			}
		}

		// img_alt_missing / img_alt_empty_non_decorative.
		foreach ( $xpath->query( '//img' ) as $img ) {
			if ( ! $img instanceof \DOMElement ) {
				continue;
			}
			$outer = self::outer_html( $dom, $img );
			if ( ! $img->hasAttribute( 'alt' ) ) {
				$add( 'img_alt_missing', $outer );
				continue;
			}
			$alt  = trim( $img->getAttribute( 'alt' ) );
			$role = trim( $img->getAttribute( 'role' ) );
			if ( '' === $alt && 'presentation' !== $role && 'none' !== $role ) {
				$add( 'img_alt_empty_non_decorative', $outer );
			}
		}

		// heading order + missing h1.
		$headings = $xpath->query( '//h1|//h2|//h3|//h4|//h5|//h6' );
		$h1_count = 0;
		$prev     = 0;
		foreach ( $headings as $h ) {
			if ( ! $h instanceof \DOMElement ) {
				continue;
			}
			$level = (int) substr( $h->tagName, 1 );
			if ( 1 === $level ) {
				$h1_count++;
			}
			if ( $prev > 0 && $level > $prev + 1 ) {
				$add( 'heading_order_skip', sprintf( 'h%d → h%d: %s', $prev, $level, self::text_excerpt( $h ) ) );
			}
			$prev = $level;
		}
		if ( 0 === $h1_count ) {
			$add( 'heading_h1_missing', '<h1> absent' );
		}

		// form_label_missing.
		foreach ( $xpath->query( "//input[not(@type='hidden') and not(@type='submit') and not(@type='button') and not(@type='image') and not(@type='reset')] | //select | //textarea" ) as $ctrl ) {
			if ( ! $ctrl instanceof \DOMElement ) {
				continue;
			}
			if ( $ctrl->hasAttribute( 'aria-label' ) && '' !== trim( $ctrl->getAttribute( 'aria-label' ) ) ) {
				continue;
			}
			if ( $ctrl->hasAttribute( 'aria-labelledby' ) && '' !== trim( $ctrl->getAttribute( 'aria-labelledby' ) ) ) {
				continue;
			}
			if ( $ctrl->hasAttribute( 'title' ) && '' !== trim( $ctrl->getAttribute( 'title' ) ) ) {
				continue;
			}
			$id = trim( $ctrl->getAttribute( 'id' ) );
			if ( '' !== $id ) {
				$label_query = sprintf( "//label[@for='%s']", addslashes( $id ) );
				$labels      = $xpath->query( $label_query );
				if ( $labels && $labels->length > 0 ) {
					continue;
				}
			}
			// Wrapped label: control inside <label>.
			$parent = $ctrl->parentNode;
			$wrapped = false;
			while ( $parent instanceof \DOMElement ) {
				if ( 'label' === $parent->tagName ) {
					$wrapped = true;
					break;
				}
				$parent = $parent->parentNode;
			}
			if ( $wrapped ) {
				continue;
			}
			$add( 'form_label_missing', self::outer_html( $dom, $ctrl ) );
		}

		// link_empty_text + generic link text.
		$generic_terms = array( 'click here', 'read more', 'more', 'here', 'link' );
		foreach ( $xpath->query( '//a[@href]' ) as $a ) {
			if ( ! $a instanceof \DOMElement ) {
				continue;
			}
			$aria_label = trim( $a->getAttribute( 'aria-label' ) );
			$text       = trim( preg_replace( '/\s+/', ' ', (string) $a->textContent ) );
			$img_alt    = '';
			foreach ( $xpath->query( './/img', $a ) as $img ) {
				if ( $img instanceof \DOMElement && $img->hasAttribute( 'alt' ) ) {
					$img_alt .= ' ' . trim( $img->getAttribute( 'alt' ) );
				}
			}
			$accessible = trim( $aria_label . ' ' . $text . ' ' . $img_alt );
			if ( '' === $accessible ) {
				$add( 'link_empty_text', self::outer_html( $dom, $a ) );
				continue;
			}
			$normalised = strtolower( $accessible );
			if ( in_array( $normalised, $generic_terms, true ) ) {
				$add( 'link_generic_text', self::outer_html( $dom, $a ) );
			}
		}

		// button_empty_text.
		foreach ( $xpath->query( "//button | //input[@type='submit'] | //input[@type='button']" ) as $btn ) {
			if ( ! $btn instanceof \DOMElement ) {
				continue;
			}
			if ( $btn->hasAttribute( 'aria-label' ) && '' !== trim( $btn->getAttribute( 'aria-label' ) ) ) {
				continue;
			}
			$tag  = $btn->tagName;
			$text = 'input' === $tag
				? trim( (string) $btn->getAttribute( 'value' ) )
				: trim( preg_replace( '/\s+/', ' ', (string) $btn->textContent ) );
			if ( '' === $text ) {
				$add( 'button_empty_text', self::outer_html( $dom, $btn ) );
			}
		}

		// landmark_main_missing.
		$mains = $xpath->query( "//main | //*[@role='main']" );
		if ( ! $mains || 0 === $mains->length ) {
			$add( 'landmark_main_missing', '<main> / role="main" absent' );
		}

		// iframe_title_missing.
		foreach ( $xpath->query( '//iframe' ) as $if ) {
			if ( ! $if instanceof \DOMElement ) {
				continue;
			}
			$title = trim( $if->getAttribute( 'title' ) );
			if ( '' === $title ) {
				$add( 'iframe_title_missing', self::outer_html( $dom, $if ) );
			}
		}

		// duplicate_ids.
		$ids = array();
		foreach ( $xpath->query( '//*[@id]' ) as $el ) {
			if ( ! $el instanceof \DOMElement ) {
				continue;
			}
			$id = trim( $el->getAttribute( 'id' ) );
			if ( '' === $id ) {
				continue;
			}
			if ( isset( $ids[ $id ] ) ) {
				$ids[ $id ]++;
			} else {
				$ids[ $id ] = 1;
			}
		}
		foreach ( $ids as $id => $count ) {
			if ( $count > 1 ) {
				$add( 'duplicate_ids', sprintf( 'id="%s" used %d× times', $id, $count ) );
			}
		}

		return $issues;
	}

	private static function outer_html( \DOMDocument $dom, \DOMElement $el ) : string {
		$html = (string) $dom->saveHTML( $el );
		return self::truncate( $html, 240 );
	}

	private static function text_excerpt( \DOMElement $el ) : string {
		$text = trim( preg_replace( '/\s+/', ' ', (string) $el->textContent ) );
		return self::truncate( $text, 120 );
	}

	private static function truncate( string $s, int $max ) : string {
		if ( strlen( $s ) <= $max ) {
			return $s;
		}
		return substr( $s, 0, $max - 1 ) . '…';
	}
}
