<?php
/**
 * Admin UI for EuroComply Age Verification.
 *
 * 6 tabs: Dashboard, Settings, Verification Log, Categories, Pro, License.
 *
 * @package EuroComply\AgeVerification
 */

declare( strict_types = 1 );

namespace EuroComply\AgeVerification;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin {

	public const MENU_SLUG     = 'eurocomply-av';
	public const NONCE_SAVE    = 'eurocomply_av_save_settings';
	public const NONCE_LICENSE = 'eurocomply_av_license';
	public const NONCE_CATS    = 'eurocomply_av_categories';

	private static ?Admin $instance = null;

	public static function instance() : Admin {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register();
		}
		return self::$instance;
	}

	private function register() : void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_eurocomply_av_save', array( $this, 'handle_save' ) );
		add_action( 'admin_post_eurocomply_av_license', array( $this, 'handle_license' ) );
		add_action( 'admin_post_eurocomply_av_categories', array( $this, 'handle_categories' ) );
	}

	public function menu() : void {
		add_menu_page(
			__( 'Age Verification', 'eurocomply-age-verification' ),
			__( 'Age Verification', 'eurocomply-age-verification' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_page' ),
			'dashicons-id',
			79
		);
	}

	public function enqueue( string $hook ) : void {
		if ( false === strpos( $hook, self::MENU_SLUG ) ) {
			return;
		}
		wp_enqueue_style(
			'eurocomply-av-admin',
			EUROCOMPLY_AV_URL . 'assets/css/admin.css',
			array(),
			EUROCOMPLY_AV_VERSION
		);
	}

	public function render_page() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$tab = isset( $_GET['tab'] ) ? sanitize_key( (string) $_GET['tab'] ) : 'dashboard'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$tabs = array(
			'dashboard'  => __( 'Dashboard', 'eurocomply-age-verification' ),
			'settings'   => __( 'Settings', 'eurocomply-age-verification' ),
			'log'        => __( 'Verification Log', 'eurocomply-age-verification' ),
			'categories' => __( 'Categories', 'eurocomply-age-verification' ),
			'pro'        => __( 'Pro', 'eurocomply-age-verification' ),
			'license'    => __( 'License', 'eurocomply-age-verification' ),
		);
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'dashboard';
		}

		echo '<div class="wrap eurocomply-av-admin">';
		echo '<h1>' . esc_html__( 'EuroComply Age Verification', 'eurocomply-age-verification' )
			. ' <span class="eurocomply-av-version">v' . esc_html( EUROCOMPLY_AV_VERSION ) . '</span></h1>';

		settings_errors( 'eurocomply_av' );

		echo '<nav class="nav-tab-wrapper eurocomply-av-tabs">';
		foreach ( $tabs as $slug => $label ) {
			$url = add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => $slug ), admin_url( 'admin.php' ) );
			$cls = 'nav-tab' . ( $slug === $tab ? ' nav-tab-active' : '' );
			echo '<a class="' . esc_attr( $cls ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';

		switch ( $tab ) {
			case 'settings':
				$this->render_settings();
				break;
			case 'log':
				$this->render_log();
				break;
			case 'categories':
				$this->render_categories();
				break;
			case 'pro':
				$this->render_pro();
				break;
			case 'license':
				$this->render_license();
				break;
			case 'dashboard':
			default:
				$this->render_dashboard();
		}

		echo '</div>';
	}

	private function render_dashboard() : void {
		$passed_30 = VerificationStore::count_since( time() - ( 30 * DAY_IN_SECONDS ), true );
		$blocked_30 = VerificationStore::count_since( time() - ( 30 * DAY_IN_SECONDS ), false );
		$total_30   = $passed_30 + $blocked_30;
		$pass_rate  = $total_30 > 0 ? round( 100.0 * $passed_30 / $total_30, 1 ) : 0.0;

		echo '<div class="eurocomply-av-cards">';
		$this->card( __( 'Passed (30d)', 'eurocomply-age-verification' ), (string) $passed_30 );
		$this->card( __( 'Blocked (30d)', 'eurocomply-age-verification' ), (string) $blocked_30 );
		$this->card( __( 'Pass rate (30d)', 'eurocomply-age-verification' ), $pass_rate . ' %' );
		echo '</div>';

		echo '<p>' . esc_html__( 'Add a page-level gate anywhere with the shortcode:', 'eurocomply-age-verification' ) . ' <code>[eurocomply_age_gate min_age="18"]</code></p>';

		$recent = VerificationStore::recent( 10 );
		echo '<h2>' . esc_html__( 'Recent verification attempts', 'eurocomply-age-verification' ) . '</h2>';
		$this->render_log_table( $recent );
	}

	private function render_settings() : void {
		$s = Settings::get();
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( self::NONCE_SAVE, '_eurocomply_av_save_nonce' );
		echo '<input type="hidden" name="action" value="eurocomply_av_save" />';
		echo '<table class="form-table" role="presentation"><tbody>';

		echo '<tr><th>' . esc_html__( 'Gate mode', 'eurocomply-age-verification' ) . '</th><td><select name="gate_mode">';
		$modes = array(
			'site'           => __( 'Site-wide (every page shows the modal until verified)', 'eurocomply-age-verification' ),
			'category'       => __( 'Category-only (WooCommerce restricted categories)', 'eurocomply-age-verification' ),
			'shortcode_only' => __( 'Shortcode only (manual placement via [eurocomply_age_gate])', 'eurocomply-age-verification' ),
		);
		foreach ( $modes as $val => $label ) {
			echo '<option value="' . esc_attr( $val ) . '"' . selected( $s['gate_mode'], $val, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr>';

		echo '<tr><th>' . esc_html__( 'Default minimum age', 'eurocomply-age-verification' ) . '</th><td><input type="number" min="13" max="21" name="default_min_age" value="' . esc_attr( (string) $s['default_min_age'] ) . '" /></td></tr>';

		echo '<tr><th>' . esc_html__( 'Verification method', 'eurocomply-age-verification' ) . '</th><td><select name="verification_method">';
		echo '<option value="dob"' . selected( $s['verification_method'], 'dob', false ) . '>' . esc_html__( 'Date of birth (JMStV / ARCOM compliant)', 'eurocomply-age-verification' ) . '</option>';
		echo '<option value="checkbox"' . selected( $s['verification_method'], 'checkbox', false ) . '>' . esc_html__( 'Simple checkbox (weaker — not JMStV-compliant)', 'eurocomply-age-verification' ) . '</option>';
		echo '</select></td></tr>';

		echo '<tr><th>' . esc_html__( 'Session duration (days)', 'eurocomply-age-verification' ) . '</th><td><input type="number" min="0" max="365" name="cookie_days" value="' . esc_attr( (string) $s['cookie_days'] ) . '" /> <em>' . esc_html__( '0 = per-session only', 'eurocomply-age-verification' ) . '</em></td></tr>';

		echo '<tr><th>' . esc_html__( 'Modal title', 'eurocomply-age-verification' ) . '</th><td><input type="text" class="regular-text" name="modal_title" value="' . esc_attr( (string) $s['modal_title'] ) . '" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Modal body', 'eurocomply-age-verification' ) . '</th><td><textarea rows="3" class="large-text" name="modal_body">' . esc_textarea( (string) $s['modal_body'] ) . '</textarea></td></tr>';
		echo '<tr><th>' . esc_html__( 'Pass message', 'eurocomply-age-verification' ) . '</th><td><input type="text" class="regular-text" name="pass_message" value="' . esc_attr( (string) $s['pass_message'] ) . '" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Blocked message', 'eurocomply-age-verification' ) . '</th><td><input type="text" class="regular-text" name="blocked_message" value="' . esc_attr( (string) $s['blocked_message'] ) . '" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Blocked redirect URL', 'eurocomply-age-verification' ) . '</th><td><input type="url" class="regular-text" name="blocked_redirect_url" value="' . esc_attr( (string) $s['blocked_redirect_url'] ) . '" /></td></tr>';

		echo '<tr><th>' . esc_html__( 'Logging & exemptions', 'eurocomply-age-verification' ) . '</th><td>';
		echo '<label><input type="checkbox" name="log_blocked_attempts" value="1" ' . checked( ! empty( $s['log_blocked_attempts'] ), true, false ) . ' /> ' . esc_html__( 'Log blocked attempts (recommended for regulator audit)', 'eurocomply-age-verification' ) . '</label><br />';
		echo '<label><input type="checkbox" name="exclude_admin_users" value="1" ' . checked( ! empty( $s['exclude_admin_users'] ), true, false ) . ' /> ' . esc_html__( 'Exempt admins (users with manage_options)', 'eurocomply-age-verification' ) . '</label><br />';
		echo '<label><input type="checkbox" name="show_checkout_block" value="1" ' . checked( ! empty( $s['show_checkout_block'] ), true, false ) . ' /> ' . esc_html__( 'Show age-verification block on WooCommerce checkout', 'eurocomply-age-verification' ) . '</label>';
		echo '</td></tr>';

		echo '<tr><th>' . esc_html__( 'Per-country minimum ages', 'eurocomply-age-verification' ) . '</th><td>';
		echo '<div class="eurocomply-av-country-grid">';
		foreach ( (array) $s['country_rules'] as $cc => $age ) {
			echo '<label><strong>' . esc_html( (string) $cc ) . '</strong> <input type="number" min="13" max="21" name="country_rules[' . esc_attr( (string) $cc ) . ']" value="' . esc_attr( (string) $age ) . '" /></label>';
		}
		echo '</div>';
		echo '<p class="description">' . esc_html__( 'Used when the visitor\'s country is known (e.g. via a geo-IP plugin). Falls back to the default minimum age.', 'eurocomply-age-verification' ) . '</p>';
		echo '</td></tr>';

		echo '</tbody></table>';
		submit_button( __( 'Save settings', 'eurocomply-age-verification' ) );
		echo '</form>';
	}

	private function render_log() : void {
		$rows       = VerificationStore::recent( 200 );
		$export_url = wp_nonce_url(
			add_query_arg( array( 'action' => CsvExport::ACTION ), admin_url( 'admin-post.php' ) ),
			CsvExport::NONCE
		);
		echo '<p><a class="button" href="' . esc_url( $export_url ) . '">'
			. esc_html__( 'Export CSV', 'eurocomply-age-verification' ) . '</a></p>';
		$this->render_log_table( $rows );
	}

	/**
	 * @param array<int,array<string,mixed>> $rows
	 */
	private function render_log_table( array $rows ) : void {
		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No verification attempts recorded yet.', 'eurocomply-age-verification' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>#</th><th>' . esc_html__( 'When', 'eurocomply-age-verification' ) . '</th>';
		echo '<th>' . esc_html__( 'Result', 'eurocomply-age-verification' ) . '</th>';
		echo '<th>' . esc_html__( 'Method', 'eurocomply-age-verification' ) . '</th>';
		echo '<th>' . esc_html__( 'Age', 'eurocomply-age-verification' ) . '</th>';
		echo '<th>' . esc_html__( 'Required', 'eurocomply-age-verification' ) . '</th>';
		echo '<th>' . esc_html__( 'Context', 'eurocomply-age-verification' ) . '</th>';
		echo '<th>' . esc_html__( 'Country', 'eurocomply-age-verification' ) . '</th>';
		echo '<th>' . esc_html__( 'IP (hashed)', 'eurocomply-age-verification' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$passed = ! empty( $row['passed'] );
			echo '<tr>';
			echo '<td>' . (int) $row['id'] . '</td>';
			echo '<td>' . esc_html( (string) $row['attempted_at'] ) . '</td>';
			echo '<td>' . ( $passed
				? '<span style="color:#008a20;">' . esc_html__( 'PASS', 'eurocomply-age-verification' ) . '</span>'
				: '<span style="color:#d63638;">' . esc_html__( 'BLOCK', 'eurocomply-age-verification' ) . '</span>' )
				. '</td>';
			echo '<td>' . esc_html( (string) $row['method'] ) . '</td>';
			echo '<td>' . (int) $row['computed_age'] . '</td>';
			echo '<td>' . (int) $row['required_age'] . '</td>';
			echo '<td>' . esc_html( (string) $row['context'] . ( $row['context_ref'] ? ':' . $row['context_ref'] : '' ) ) . '</td>';
			echo '<td>' . esc_html( (string) $row['country'] ) . '</td>';
			echo '<td><code>' . esc_html( substr( (string) $row['ip_hash'], 0, 10 ) ) . '…</code></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private function render_categories() : void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			echo '<p>' . esc_html__( 'WooCommerce is not active. Category gating requires WooCommerce.', 'eurocomply-age-verification' ) . '</p>';
			return;
		}
		$s     = Settings::get();
		$terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			echo '<p>' . esc_html__( 'No product categories found.', 'eurocomply-age-verification' ) . '</p>';
			return;
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( self::NONCE_CATS, '_eurocomply_av_cats_nonce' );
		echo '<input type="hidden" name="action" value="eurocomply_av_categories" />';

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Restricted?', 'eurocomply-age-verification' ) . '</th>';
		echo '<th>' . esc_html__( 'Category', 'eurocomply-age-verification' ) . '</th>';
		echo '<th>' . esc_html__( 'Minimum age', 'eurocomply-age-verification' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( $terms as $term ) {
			$tid       = (int) $term->term_id;
			$selected  = in_array( $tid, (array) $s['restricted_categories'], true );
			$age       = $s['restricted_min_ages'][ $tid ] ?? $s['default_min_age'];
			echo '<tr>';
			echo '<td><input type="checkbox" name="restricted_categories[]" value="' . esc_attr( (string) $tid ) . '" ' . checked( $selected, true, false ) . ' /></td>';
			echo '<td>' . esc_html( $term->name ) . '</td>';
			echo '<td><input type="number" min="13" max="21" name="restricted_min_ages[' . esc_attr( (string) $tid ) . ']" value="' . esc_attr( (string) $age ) . '" /></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';

		submit_button( __( 'Save category rules', 'eurocomply-age-verification' ) );
		echo '</form>';
	}

	private function render_pro() : void {
		echo '<p>' . esc_html__( 'Unlock Pro features with an EC-XXXXXX license key under the License tab.', 'eurocomply-age-verification' ) . '</p>';
		echo '<ul class="eurocomply-av-pro-list">';
		echo '<li>' . esc_html__( 'AusweisIdent / eID integration (German national ID card).', 'eurocomply-age-verification' ) . '</li>';
		echo '<li>' . esc_html__( 'SCHUFA age-check (Germany) — returns only a pass/fail.', 'eurocomply-age-verification' ) . '</li>';
		echo '<li>' . esc_html__( 'Veriff / Onfido biometric + ID-document upload (FR ARCOM double-blind).', 'eurocomply-age-verification' ) . '</li>';
		echo '<li>' . esc_html__( 'SMS OTP fallback with country-code routing.', 'eurocomply-age-verification' ) . '</li>';
		echo '<li>' . esc_html__( 'Parental consent workflow for users aged 13–15 (UK OSA / COPPA-style).', 'eurocomply-age-verification' ) . '</li>';
		echo '<li>' . esc_html__( 'WooCommerce variations: per-variation age override.', 'eurocomply-age-verification' ) . '</li>';
		echo '<li>' . esc_html__( 'WPML / Polylang multilingual modal.', 'eurocomply-age-verification' ) . '</li>';
		echo '<li>' . esc_html__( 'Per-post / per-page gating (not only product categories).', 'eurocomply-age-verification' ) . '</li>';
		echo '<li>' . esc_html__( 'Signed PDF audit reports for DE KJM / FR ARCOM submission.', 'eurocomply-age-verification' ) . '</li>';
		echo '<li>' . esc_html__( 'Higher CSV export cap (5,000 rows vs 500 free).', 'eurocomply-age-verification' ) . '</li>';
		echo '</ul>';
	}

	private function render_license() : void {
		$license = License::get();
		$active  = License::is_pro();
		echo '<p>' . esc_html__( 'Status:', 'eurocomply-age-verification' ) . ' <strong>'
			. ( $active ? esc_html__( 'Active (Pro)', 'eurocomply-age-verification' ) : esc_html__( 'Free', 'eurocomply-age-verification' ) )
			. '</strong></p>';

		if ( $active ) {
			echo '<p><code>' . esc_html( (string) ( $license['key'] ?? '' ) ) . '</code></p>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( self::NONCE_LICENSE, '_eurocomply_av_license_nonce' );
			echo '<input type="hidden" name="action" value="eurocomply_av_license" />';
			echo '<input type="hidden" name="mode" value="deactivate" />';
			submit_button( __( 'Deactivate license', 'eurocomply-age-verification' ), 'secondary' );
			echo '</form>';
		} else {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( self::NONCE_LICENSE, '_eurocomply_av_license_nonce' );
			echo '<input type="hidden" name="action" value="eurocomply_av_license" />';
			echo '<input type="hidden" name="mode" value="activate" />';
			echo '<p><label for="eurocomply-av-key">' . esc_html__( 'License key (EC-XXXXXX)', 'eurocomply-age-verification' ) . '</label>';
			echo ' <input id="eurocomply-av-key" type="text" name="license_key" class="regular-text" /></p>';
			submit_button( __( 'Activate Pro license', 'eurocomply-age-verification' ) );
			echo '</form>';
		}
	}

	private function card( string $label, string $value ) : void {
		echo '<div class="eurocomply-av-card"><div class="eurocomply-av-card-value">' . esc_html( $value )
			. '</div><div class="eurocomply-av-card-label">' . esc_html( $label ) . '</div></div>';
	}

	public function handle_save() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'eurocomply-age-verification' ) );
		}
		check_admin_referer( self::NONCE_SAVE, '_eurocomply_av_save_nonce' );

		$input = array();
		foreach ( array_keys( Settings::defaults() ) as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				$raw = wp_unslash( $_POST[ $key ] );
				$input[ $key ] = is_array( $raw ) ? $raw : (string) $raw;
			}
		}
		// Preserve category arrays managed on a separate tab.
		$existing = Settings::get();
		$input['restricted_categories'] = $existing['restricted_categories'];
		$input['restricted_min_ages']   = $existing['restricted_min_ages'];

		update_option( Settings::OPTION_KEY, Settings::sanitize( $input ), false );
		add_settings_error( 'eurocomply_av', 'saved', __( 'Settings saved.', 'eurocomply-age-verification' ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'             => self::MENU_SLUG,
					'tab'              => 'settings',
					'settings-updated' => 'true',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function handle_categories() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'eurocomply-age-verification' ) );
		}
		check_admin_referer( self::NONCE_CATS, '_eurocomply_av_cats_nonce' );

		$existing = Settings::get();
		$existing['restricted_categories'] = isset( $_POST['restricted_categories'] ) && is_array( $_POST['restricted_categories'] )
			? array_map( 'intval', wp_unslash( (array) $_POST['restricted_categories'] ) )
			: array();
		$existing['restricted_min_ages'] = isset( $_POST['restricted_min_ages'] ) && is_array( $_POST['restricted_min_ages'] )
			? wp_unslash( (array) $_POST['restricted_min_ages'] )
			: array();

		update_option( Settings::OPTION_KEY, Settings::sanitize( $existing ), false );
		add_settings_error( 'eurocomply_av', 'cats_saved', __( 'Category rules saved.', 'eurocomply-age-verification' ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'             => self::MENU_SLUG,
					'tab'              => 'categories',
					'settings-updated' => 'true',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function handle_license() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'eurocomply-age-verification' ) );
		}
		check_admin_referer( self::NONCE_LICENSE, '_eurocomply_av_license_nonce' );

		$mode = isset( $_POST['mode'] ) ? sanitize_key( (string) $_POST['mode'] ) : '';
		if ( 'deactivate' === $mode ) {
			License::deactivate();
			add_settings_error( 'eurocomply_av', 'license_deactivated', __( 'License deactivated.', 'eurocomply-age-verification' ), 'updated' );
		} else {
			$key    = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['license_key'] ) ) : '';
			$result = License::activate( $key );
			$type   = $result['ok'] ? 'updated' : 'error';
			add_settings_error( 'eurocomply_av', 'license_result', $result['message'], $type );
		}
		set_transient( 'settings_errors', get_settings_errors(), 30 );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'             => self::MENU_SLUG,
					'tab'              => 'license',
					'settings-updated' => 'true',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
