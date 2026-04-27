<?php
/**
 * Bootstrap.
 *
 * @package EuroComply\ToySafety
 */

declare( strict_types = 1 );

namespace EuroComply\ToySafety;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {

	private static ?Plugin $instance = null;

	public static function instance() : Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register();
		}
		return self::$instance;
	}

	private function register() : void {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		ToyStore::maybe_upgrade();
		SubstanceStore::maybe_upgrade();
		AssessmentStore::maybe_upgrade();
		IncidentStore::maybe_upgrade();
		OperatorStore::maybe_upgrade();
		Admin::instance();
		CsvExport::instance();
	}

	public static function activate() : void {
		ToyStore::install();
		SubstanceStore::install();
		AssessmentStore::install();
		IncidentStore::install();
		OperatorStore::install();
		if ( ! get_option( Settings::OPTION_KEY ) ) {
			add_option( Settings::OPTION_KEY, Settings::defaults(), '', false );
		}
	}

	public static function deactivate() : void {}

	public function load_textdomain() : void {
		load_plugin_textdomain( 'eurocomply-toy-safety', false, dirname( EUROCOMPLY_TOY_BASENAME ) . '/languages' );
	}
}
