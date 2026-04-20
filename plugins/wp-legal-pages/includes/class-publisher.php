<?php
/**
 * Publisher — creates/updates WordPress pages, registers shortcodes,
 *             exposes footer-menu helper.
 *
 * @package EuroComply\LegalPages
 */

namespace EuroComply\LegalPages;

defined( 'ABSPATH' ) || exit;

class Publisher {

	const PAGE_OPTION    = 'eurocomply_legal_pages'; // [ type => page_id ]
	const META_KEY       = '_eurocomply_legal_type';

	/** @var Generator */
	private $generator;

	/** @var Settings */
	private $settings;

	public function __construct( Generator $generator, Settings $settings ) {
		$this->generator = $generator;
		$this->settings  = $settings;
	}

	public function register_hooks() {
		add_shortcode( 'eurocomply_impressum',   array( $this, 'sc_impressum' ) );
		add_shortcode( 'eurocomply_datenschutz', array( $this, 'sc_datenschutz' ) );
		add_shortcode( 'eurocomply_agb',         array( $this, 'sc_agb' ) );
		add_shortcode( 'eurocomply_widerruf',    array( $this, 'sc_widerruf' ) );

		add_action( 'wp_footer', array( $this, 'render_footer_links' ) );
	}

	/**
	 * Generate the legal document and publish it as a WP page.
	 * If the page exists already (tracked via option), update it in place.
	 *
	 * @param string $type Templates::TYPE_*
	 * @return array{ok:bool, page_id?:int, url?:string, error?:string, paywall?:bool}
	 */
	public function publish( $type ) {
		$result = $this->generator->render( $type );
		if ( empty( $result['ok'] ) ) {
			return $result;
		}

		$pages = get_option( self::PAGE_OPTION, array() );
		if ( ! is_array( $pages ) ) {
			$pages = array();
		}
		$existing_id = isset( $pages[ $type ] ) ? (int) $pages[ $type ] : 0;

		$postarr = array(
			'post_title'   => $result['title'] ? $result['title'] : $this->default_title( $type ),
			'post_content' => $result['html'],
			'post_status'  => 'publish',
			'post_type'    => 'page',
		);

		if ( $existing_id && get_post( $existing_id ) ) {
			$postarr['ID'] = $existing_id;
			$page_id = wp_update_post( wp_slash( $postarr ), true );
		} else {
			$page_id = wp_insert_post( wp_slash( $postarr ), true );
		}

		if ( is_wp_error( $page_id ) ) {
			return array( 'ok' => false, 'error' => $page_id->get_error_message() );
		}

		update_post_meta( $page_id, self::META_KEY, $type );
		$pages[ $type ] = (int) $page_id;
		update_option( self::PAGE_OPTION, $pages, false );

		return array(
			'ok'      => true,
			'page_id' => (int) $page_id,
			'url'     => get_permalink( $page_id ),
		);
	}

	public function get_page_id( $type ) {
		$pages = get_option( self::PAGE_OPTION, array() );
		return is_array( $pages ) && isset( $pages[ $type ] ) ? (int) $pages[ $type ] : 0;
	}

	private function default_title( $type ) {
		switch ( $type ) {
			case Templates::TYPE_IMPRESSUM:   return __( 'Impressum', 'eurocomply-legal' );
			case Templates::TYPE_DATENSCHUTZ: return __( 'Datenschutzerklärung', 'eurocomply-legal' );
			case Templates::TYPE_AGB:         return __( 'Allgemeine Geschäftsbedingungen', 'eurocomply-legal' );
			case Templates::TYPE_WIDERRUF:    return __( 'Widerrufsbelehrung', 'eurocomply-legal' );
			default:                          return __( 'Legal notice', 'eurocomply-legal' );
		}
	}

	// -----------------------------------------------------------------------
	// Shortcodes
	// -----------------------------------------------------------------------

	public function sc_impressum() {
		return $this->shortcode_render( Templates::TYPE_IMPRESSUM );
	}

	public function sc_datenschutz() {
		return $this->shortcode_render( Templates::TYPE_DATENSCHUTZ );
	}

	public function sc_agb() {
		return $this->shortcode_render( Templates::TYPE_AGB );
	}

	public function sc_widerruf() {
		return $this->shortcode_render( Templates::TYPE_WIDERRUF );
	}

	private function shortcode_render( $type ) {
		$result = $this->generator->render( $type );
		if ( empty( $result['ok'] ) ) {
			if ( current_user_can( 'manage_options' ) ) {
				return '<div class="notice notice-warning"><p>' .
					esc_html( isset( $result['error'] ) ? $result['error'] : __( 'Unable to render.', 'eurocomply-legal' ) ) .
					'</p></div>';
			}
			return '';
		}
		return $result['html'];
	}

	// -----------------------------------------------------------------------
	// Footer links
	// -----------------------------------------------------------------------

	public function render_footer_links() {
		$settings = get_option( Settings::OPTION_KEY, array() );
		if ( empty( $settings['footer_links_enabled'] ) ) {
			return;
		}
		$pages = get_option( self::PAGE_OPTION, array() );
		if ( empty( $pages ) ) {
			return;
		}
		echo '<nav class="eurocomply-legal-footer" aria-label="' . esc_attr__( 'Legal', 'eurocomply-legal' ) . '">';
		foreach ( $pages as $type => $page_id ) {
			$permalink = get_permalink( $page_id );
			if ( ! $permalink ) {
				continue;
			}
			echo '<a href="' . esc_url( $permalink ) . '">' . esc_html( $this->default_title( $type ) ) . '</a> ';
		}
		echo '</nav>';
	}
}
