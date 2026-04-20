<?php
/**
 * Plugin bootstrap — wires the other classes together.
 *
 * @package EuroComply\LegalPages
 */

namespace EuroComply\LegalPages;

defined( 'ABSPATH' ) || exit;

class Plugin {

	/** @var Plugin|null */
	private static $instance = null;

	/** @var Settings */
	public $settings;

	/** @var Admin */
	public $admin;

	/** @var Generator */
	public $generator;

	/** @var Publisher */
	public $publisher;

	/** @var License */
	public $license;

	/** @var Templates */
	public $templates;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function boot() {
		load_plugin_textdomain(
			'eurocomply-legal',
			false,
			dirname( EUROCOMPLY_LEGAL_BASENAME ) . '/languages'
		);

		$this->settings  = new Settings();
		$this->templates = new Templates();
		$this->license   = new License();
		$this->generator = new Generator( $this->settings, $this->templates, $this->license );
		$this->publisher = new Publisher( $this->generator, $this->settings );
		$this->admin     = new Admin( $this->settings, $this->templates, $this->generator, $this->publisher, $this->license );

		$this->admin->register_hooks();
		$this->publisher->register_hooks();
	}

	public static function activate() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		add_option( 'eurocomply_legal_version', EUROCOMPLY_LEGAL_VERSION );
	}

	public static function deactivate() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		// Keep settings on deactivation; clear only transients.
		delete_transient( 'eurocomply_legal_preview_cache' );
	}
}
