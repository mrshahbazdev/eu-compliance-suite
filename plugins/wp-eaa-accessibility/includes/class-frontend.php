<?php
/**
 * Frontend enhancements — skip-to-content link + optional focus polyfill.
 *
 * @package EuroComply\Eaa
 */

declare( strict_types = 1 );

namespace EuroComply\Eaa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Frontend {

	private static ?Frontend $instance = null;

	public static function instance() : Frontend {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function hooks() : void {
		add_action( 'wp_body_open', array( $this, 'render_skip_link' ), 5 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function render_skip_link() : void {
		if ( empty( Settings::value( 'inject_skip_link', 1 ) ) ) {
			return;
		}
		echo '<a class="eurocomply-eaa-skip-link" href="#content">' . esc_html__( 'Skip to content', 'eurocomply-eaa' ) . '</a>';
	}

	public function enqueue_assets() : void {
		if ( empty( Settings::value( 'inject_skip_link', 1 ) ) && empty( Settings::value( 'focus_outline_polyfill', 0 ) ) ) {
			return;
		}
		wp_enqueue_style(
			'eurocomply-eaa-frontend',
			EUROCOMPLY_EAA_URL . 'assets/css/frontend.css',
			array(),
			EUROCOMPLY_EAA_VERSION
		);
	}
}
