<?php
/**
 * Hooks into WordPress events and records them to the EventStore.
 *
 * @package EuroComply\NIS2
 */

declare( strict_types = 1 );

namespace EuroComply\NIS2;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EventLogger {

	private static ?EventLogger $instance = null;

	public static function instance() : EventLogger {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register();
		}
		return self::$instance;
	}

	private function register() : void {
		$s = Settings::get();

		if ( ! empty( $s['log_failed_logins'] ) ) {
			add_action( 'wp_login_failed', array( $this, 'on_login_failed' ), 10, 1 );
		}
		if ( ! empty( $s['log_successful_logins'] ) ) {
			add_action( 'wp_login', array( $this, 'on_login' ), 10, 2 );
		}
		if ( ! empty( $s['log_user_changes'] ) ) {
			add_action( 'user_register', array( $this, 'on_user_register' ), 10, 1 );
			add_action( 'deleted_user', array( $this, 'on_user_deleted' ), 10, 1 );
			add_action( 'set_user_role', array( $this, 'on_role_changed' ), 10, 3 );
		}
		if ( ! empty( $s['log_plugin_changes'] ) ) {
			add_action( 'activated_plugin', array( $this, 'on_plugin_activated' ), 10, 2 );
			add_action( 'deactivated_plugin', array( $this, 'on_plugin_deactivated' ), 10, 2 );
			add_action( 'upgrader_process_complete', array( $this, 'on_upgrader_complete' ), 10, 2 );
		}
		if ( ! empty( $s['log_theme_changes'] ) ) {
			add_action( 'switch_theme', array( $this, 'on_switch_theme' ), 10, 1 );
		}
		if ( ! empty( $s['log_option_changes'] ) ) {
			add_action( 'updated_option', array( $this, 'on_option_updated' ), 10, 3 );
		}
	}

	public function on_login_failed( string $username ) : void {
		EventStore::record(
			array(
				'category'    => 'auth',
				'severity'    => 'medium',
				'action'      => 'login_failed',
				'actor_login' => $username,
				'ip_hash'     => EventStore::ip_hash( self::client_ip() ),
				'user_agent'  => self::ua(),
				'target'      => $username,
			)
		);
	}

	/**
	 * @param \WP_User $user
	 */
	public function on_login( string $username, $user ) : void {
		$user_id = is_object( $user ) && isset( $user->ID ) ? (int) $user->ID : 0;
		EventStore::record(
			array(
				'category'      => 'auth',
				'severity'      => 'info',
				'action'        => 'login_success',
				'actor_user_id' => $user_id,
				'actor_login'   => $username,
				'ip_hash'       => EventStore::ip_hash( self::client_ip() ),
				'user_agent'    => self::ua(),
			)
		);
	}

	public function on_user_register( int $user_id ) : void {
		$user = get_userdata( $user_id );
		EventStore::record(
			array(
				'category'      => 'admin',
				'severity'      => 'medium',
				'action'        => 'user_registered',
				'actor_user_id' => (int) get_current_user_id(),
				'target'        => $user ? $user->user_login : (string) $user_id,
				'details'       => array( 'user_id' => $user_id ),
			)
		);
	}

	public function on_user_deleted( int $user_id ) : void {
		EventStore::record(
			array(
				'category'      => 'admin',
				'severity'      => 'high',
				'action'        => 'user_deleted',
				'actor_user_id' => (int) get_current_user_id(),
				'target'        => (string) $user_id,
			)
		);
	}

	/**
	 * @param array<int,string> $old_roles
	 */
	public function on_role_changed( int $user_id, string $role, array $old_roles ) : void {
		EventStore::record(
			array(
				'category'      => 'admin',
				'severity'      => 'administrator' === $role ? 'high' : 'medium',
				'action'        => 'user_role_changed',
				'actor_user_id' => (int) get_current_user_id(),
				'target'        => (string) $user_id,
				'details'       => array( 'new_role' => $role, 'old_roles' => $old_roles ),
			)
		);
	}

	public function on_plugin_activated( string $plugin, bool $network_wide ) : void {
		EventStore::record(
			array(
				'category'      => 'plugin',
				'severity'      => 'medium',
				'action'        => 'plugin_activated',
				'actor_user_id' => (int) get_current_user_id(),
				'target'        => $plugin,
				'details'       => array( 'network_wide' => $network_wide ),
			)
		);
	}

	public function on_plugin_deactivated( string $plugin, bool $network_wide ) : void {
		EventStore::record(
			array(
				'category'      => 'plugin',
				'severity'      => 'medium',
				'action'        => 'plugin_deactivated',
				'actor_user_id' => (int) get_current_user_id(),
				'target'        => $plugin,
				'details'       => array( 'network_wide' => $network_wide ),
			)
		);
	}

	/**
	 * @param object $upgrader
	 * @param array<string,mixed> $options
	 */
	public function on_upgrader_complete( $upgrader, array $options ) : void {
		$type   = isset( $options['type'] ) ? (string) $options['type'] : '';
		$action = isset( $options['action'] ) ? (string) $options['action'] : '';
		EventStore::record(
			array(
				'category'      => 'file',
				'severity'      => 'medium',
				'action'        => 'upgrader_' . ( $action ?: 'unknown' ),
				'actor_user_id' => (int) get_current_user_id(),
				'target'        => $type,
				'details'       => $options,
			)
		);
	}

	public function on_switch_theme( string $new_name ) : void {
		EventStore::record(
			array(
				'category'      => 'theme',
				'severity'      => 'medium',
				'action'        => 'theme_switched',
				'actor_user_id' => (int) get_current_user_id(),
				'target'        => $new_name,
			)
		);
	}

	/**
	 * @param mixed $old_value
	 * @param mixed $value
	 */
	public function on_option_updated( string $option, $old_value, $value ) : void {
		// Skip highly-trafficked / noisy options.
		static $skip = array( 'cron', 'doing_cron', 'uninstall_plugins', 'active_plugins', '_transient_', '_site_transient_' );
		foreach ( $skip as $prefix ) {
			if ( 0 === strpos( $option, $prefix ) ) {
				return;
			}
		}
		EventStore::record(
			array(
				'category'      => 'config',
				'severity'      => 'low',
				'action'        => 'option_updated',
				'actor_user_id' => (int) get_current_user_id(),
				'target'        => $option,
			)
		);
	}

	private static function client_ip() : string {
		$candidates = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );
		foreach ( $candidates as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$val = explode( ',', (string) $_SERVER[ $key ] );
				$ip  = trim( (string) $val[0] );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}
		return '';
	}

	private static function ua() : string {
		return isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( (string) $_SERVER['HTTP_USER_AGENT'], 0, 190 ) : '';
	}
}
