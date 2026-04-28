<?php
/**
 * Admin UI: 8-tab dashboard.
 *
 * @package EuroComply\EPrivacy
 */

declare( strict_types = 1 );

namespace EuroComply\EPrivacy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin {

	public const MENU_SLUG  = 'eurocomply-eprivacy';
	private const NONCE_SAVE    = 'eurocomply_eprivacy_save';
	private const NONCE_LICENSE = 'eurocomply_eprivacy_license';
	private const NONCE_SCAN    = 'eurocomply_eprivacy_scan';
	private const NONCE_APPLY   = 'eurocomply_eprivacy_apply_to_consent';

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
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_eurocomply_eprivacy_save',              array( $this, 'handle_save' ) );
		add_action( 'admin_post_eurocomply_eprivacy_license',           array( $this, 'handle_license' ) );
		add_action( 'admin_post_eurocomply_eprivacy_scan',              array( $this, 'handle_scan' ) );
		add_action( 'admin_post_eurocomply_eprivacy_apply_to_consent', array( $this, 'handle_apply_to_consent' ) );
	}

	public function menu() : void {
		add_menu_page(
			__( 'ePrivacy', 'eurocomply-eprivacy' ),
			__( 'ePrivacy', 'eurocomply-eprivacy' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render' ),
			'dashicons-shield-alt',
			78
		);
	}

	public function assets( string $hook ) : void {
		if ( false === strpos( (string) $hook, self::MENU_SLUG ) ) {
			return;
		}
		wp_enqueue_style(
			'eurocomply-eprivacy-admin',
			EUROCOMPLY_EPR_URL . 'assets/css/admin.css',
			array(),
			EUROCOMPLY_EPR_VERSION
		);
	}

	public function render() : void {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( (string) $_GET['tab'] ) : 'dashboard';
		echo '<div class="wrap eurocomply-eprivacy-wrap">';
		echo '<h1>' . esc_html__( 'EuroComply ePrivacy & Tracker Registry', 'eurocomply-eprivacy' ) . '</h1>';
		settings_errors();
		$this->tabs( $tab );
		switch ( $tab ) {
			case 'trackers':  $this->render_trackers();  break;
			case 'scan':      $this->render_scan();      break;
			case 'findings':  $this->render_findings();  break;
			case 'cookies':   $this->render_cookies();   break;
			case 'settings':  $this->render_settings();  break;
			case 'pro':       $this->render_pro();       break;
			case 'license':   $this->render_license();   break;
			case 'dashboard':
			default:          $this->render_dashboard(); break;
		}
		echo '</div>';
	}

	private function tabs( string $current ) : void {
		$tabs = array(
			'dashboard' => __( 'Dashboard', 'eurocomply-eprivacy' ),
			'trackers'  => __( 'Trackers',  'eurocomply-eprivacy' ),
			'scan'      => __( 'Scan',      'eurocomply-eprivacy' ),
			'findings'  => __( 'Findings',  'eurocomply-eprivacy' ),
			'cookies'   => __( 'Cookies',   'eurocomply-eprivacy' ),
			'settings'  => __( 'Settings',  'eurocomply-eprivacy' ),
			'pro'       => __( 'Pro',       'eurocomply-eprivacy' ),
			'license'   => __( 'License',   'eurocomply-eprivacy' ),
		);
		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $tabs as $slug => $label ) {
			$url = add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => $slug ), admin_url( 'admin.php' ) );
			$cls = 'nav-tab' . ( $current === $slug ? ' nav-tab-active' : '' );
			echo '<a class="' . esc_attr( $cls ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</h2>';
	}

	private function card( string $label, string $value, string $tone = '' ) : void {
		$cls = 'eurocomply-eprivacy-card' . ( '' !== $tone ? ' eurocomply-eprivacy-card-' . $tone : '' );
		echo '<div class="' . esc_attr( $cls ) . '">';
		echo '<div class="eurocomply-eprivacy-card-value">' . esc_html( $value ) . '</div>';
		echo '<div class="eurocomply-eprivacy-card-label">' . esc_html( $label ) . '</div>';
		echo '</div>';
	}

	private function render_dashboard() : void {
		$scans     = ScanStore::count_total();
		$findings  = FindingStore::count_total();
		$cookies   = CookieStore::count_total();
		$reg_total = count( TrackerRegistry::all() );
		$gaps      = Scanner::compliance_gaps();
		$gaps_n    = count( $gaps );

		echo '<div class="eurocomply-eprivacy-cards">';
		$this->card( __( 'Trackers in registry', 'eurocomply-eprivacy' ), (string) $reg_total );
		$this->card( __( 'Scans run',            'eurocomply-eprivacy' ), (string) $scans, $scans > 0 ? 'ok' : 'warn' );
		$this->card( __( 'Findings recorded',    'eurocomply-eprivacy' ), (string) $findings );
		$this->card( __( 'Cookies observed',     'eurocomply-eprivacy' ), (string) $cookies );
		$this->card( __( 'Consent gaps',         'eurocomply-eprivacy' ), (string) $gaps_n, $gaps_n > 0 ? 'crit' : 'ok' );
		$this->card( __( 'Cookie observer',      'eurocomply-eprivacy' ), Settings::get()['enable_cookie_observer'] ? __( 'On', 'eurocomply-eprivacy' ) : __( 'Off', 'eurocomply-eprivacy' ) );
		echo '</div>';

		echo '<h2>' . esc_html__( 'Compliance gap report', 'eurocomply-eprivacy' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Trackers detected on the latest scan that require consent under ePrivacy 2002/58 + GDPR Art. 7 but are not declared in your active consent banner.', 'eurocomply-eprivacy' ) . '</p>';
		if ( ! $gaps ) {
			echo '<p>' . esc_html__( 'No gaps detected. Run a scan to refresh.', 'eurocomply-eprivacy' ) . '</p>';
		} else {
			echo '<table class="widefat striped"><thead><tr>';
			echo '<th>' . esc_html__( 'Tracker', 'eurocomply-eprivacy' ) . '</th>';
			echo '<th>' . esc_html__( 'Category', 'eurocomply-eprivacy' ) . '</th>';
			echo '</tr></thead><tbody>';
			foreach ( $gaps as $g ) {
				echo '<tr>';
				echo '<td>' . esc_html( $g['name'] ) . '</td>';
				echo '<td>' . esc_html( $g['category'] ) . '</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
		}

		// Sister-plugin bridge: push detected trackers into Cookie Consent (#2).
		echo '<h2>' . esc_html__( 'Sister plugin: Cookie Consent (#2)', 'eurocomply-eprivacy' ) . '</h2>';
		if ( CookieConsentBridge::is_active() ) {
			$action = esc_url( admin_url( 'admin-post.php' ) );
			echo '<p class="description">' . esc_html__( 'Push every consent-requiring tracker observed on the latest scan into the EuroComply Cookie Consent inventory. Mapping uses ePrivacy categories → Consent Mode v2 buckets (analytics → statistics; advertising/social → marketing; functional/preferences → preferences).', 'eurocomply-eprivacy' ) . '</p>';
			echo '<form method="post" action="' . $action . '">';
			echo '<input type="hidden" name="action" value="eurocomply_eprivacy_apply_to_consent" />';
			wp_nonce_field( self::NONCE_APPLY );
			submit_button( __( 'Apply detected trackers to Cookie Consent', 'eurocomply-eprivacy' ), 'secondary', 'submit', false );
			echo '</form>';
		} else {
			echo '<p class="description">' . esc_html__( 'EuroComply Cookie Consent (#2) is not active on this site, so the bridge is unavailable. Install and activate it to surface detected trackers in your consent banner inventory.', 'eurocomply-eprivacy' ) . '</p>';
		}
	}

	public function handle_apply_to_consent() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-eprivacy' ), 403 );
		}
		check_admin_referer( self::NONCE_APPLY );
		$result = CookieConsentBridge::apply_findings();
		if ( ! $result['ok'] ) {
			add_settings_error(
				'eurocomply_eprivacy',
				'sister_missing',
				__( 'Cookie Consent (#2) is not active. Install/activate it before applying trackers.', 'eurocomply-eprivacy' ),
				'error'
			);
		} elseif ( 'no-findings' === $result['reason'] ) {
			add_settings_error(
				'eurocomply_eprivacy',
				'no_findings',
				__( 'No findings to push. Run a scan first, then try again.', 'eurocomply-eprivacy' ),
				'warning'
			);
		} else {
			add_settings_error(
				'eurocomply_eprivacy',
				'applied',
				sprintf(
					/* translators: 1: trackers added, 2: trackers sent. */
					__( 'Cookie Consent inventory updated: %1$d new entries (%2$d total trackers sent).', 'eurocomply-eprivacy' ),
					(int) $result['added'],
					(int) $result['sent']
				),
				'success'
			);
		}
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'dashboard', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	private function render_trackers() : void {
		$cats  = TrackerRegistry::categories();
		$rows  = TrackerRegistry::all();
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Slug',     'eurocomply-eprivacy' ) . '</th>';
		echo '<th>' . esc_html__( 'Tracker',  'eurocomply-eprivacy' ) . '</th>';
		echo '<th>' . esc_html__( 'Vendor',   'eurocomply-eprivacy' ) . '</th>';
		echo '<th>' . esc_html__( 'Country',  'eurocomply-eprivacy' ) . '</th>';
		echo '<th>' . esc_html__( 'Category', 'eurocomply-eprivacy' ) . '</th>';
		echo '<th>' . esc_html__( 'Consent?', 'eurocomply-eprivacy' ) . '</th>';
		echo '<th>' . esc_html__( 'Cookies',  'eurocomply-eprivacy' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( $rows as $slug => $r ) {
			echo '<tr>';
			echo '<td><code>' . esc_html( (string) $slug ) . '</code></td>';
			echo '<td><a href="' . esc_url( $r['docs'] ) . '" target="_blank" rel="noopener">' . esc_html( $r['name'] ) . '</a></td>';
			echo '<td>' . esc_html( $r['vendor'] ) . '</td>';
			echo '<td>' . esc_html( $r['country'] ) . '</td>';
			echo '<td>' . esc_html( $cats[ $r['category'] ] ?? $r['category'] ) . '</td>';
			echo '<td>' . ( $r['consent_required'] ? '<span class="eurocomply-eprivacy-pill crit">' . esc_html__( 'Required', 'eurocomply-eprivacy' ) . '</span>' : '<span class="eurocomply-eprivacy-pill ok">' . esc_html__( 'Strictly necessary', 'eurocomply-eprivacy' ) . '</span>' ) . '</td>';
			echo '<td>' . esc_html( implode( ', ', array_slice( $r['cookies'], 0, 6 ) ) ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private function render_scan() : void {
		$action = esc_url( admin_url( 'admin-post.php' ) );
		echo '<form method="post" action="' . $action . '">';
		echo '<input type="hidden" name="action" value="eurocomply_eprivacy_scan" />';
		wp_nonce_field( self::NONCE_SCAN );
		submit_button( __( 'Run scan now', 'eurocomply-eprivacy' ), 'primary', 'submit', false );
		echo '</form>';

		$rows = ScanStore::recent( 30 );
		echo '<h2>' . esc_html__( 'Recent scans', 'eurocomply-eprivacy' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>#</th>';
		echo '<th>' . esc_html__( 'Started',  'eurocomply-eprivacy' ) . '</th>';
		echo '<th>' . esc_html__( 'Finished', 'eurocomply-eprivacy' ) . '</th>';
		echo '<th>' . esc_html__( 'Status',   'eurocomply-eprivacy' ) . '</th>';
		echo '<th>' . esc_html__( 'URLs',     'eurocomply-eprivacy' ) . '</th>';
		echo '<th>' . esc_html__( 'Findings', 'eurocomply-eprivacy' ) . '</th>';
		echo '<th>' . esc_html__( 'Notes',    'eurocomply-eprivacy' ) . '</th>';
		echo '</tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="7">' . esc_html__( 'No scans yet.', 'eurocomply-eprivacy' ) . '</td></tr>';
		}
		foreach ( $rows as $r ) {
			echo '<tr>';
			echo '<td>' . esc_html( (string) $r['id'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['started_at'] ) . '</td>';
			echo '<td>' . esc_html( (string) ( $r['finished_at'] ?? '' ) ) . '</td>';
			echo '<td>' . esc_html( (string) $r['status'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['urls_scanned'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['findings_count'] ) . '</td>';
			echo '<td>' . esc_html( (string) ( $r['notes'] ?? '' ) ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private function render_findings() : void {
		$rows = FindingStore::recent( 200 );

		// CSV button.
		$action = esc_url( admin_url( 'admin-post.php' ) );
		echo '<form method="post" action="' . $action . '" style="margin-bottom:1em;">';
		echo '<input type="hidden" name="action" value="eurocomply_eprivacy_export" />';
		echo '<input type="hidden" name="dataset" value="findings" />';
		wp_nonce_field( 'eurocomply_eprivacy_export' );
		submit_button( __( 'Export findings CSV', 'eurocomply-eprivacy' ), 'secondary', 'submit', false );
		echo '</form>';

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Observed', 'eurocomply-eprivacy' ) . '</th>';
		echo '<th>' . esc_html__( 'URL',      'eurocomply-eprivacy' ) . '</th>';
		echo '<th>' . esc_html__( 'Tracker',  'eurocomply-eprivacy' ) . '</th>';
		echo '<th>' . esc_html__( 'Category', 'eurocomply-eprivacy' ) . '</th>';
		echo '<th>' . esc_html__( 'Evidence', 'eurocomply-eprivacy' ) . '</th>';
		echo '</tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="5">' . esc_html__( 'No findings yet.', 'eurocomply-eprivacy' ) . '</td></tr>';
		}
		foreach ( $rows as $r ) {
			$reg = TrackerRegistry::get( (string) $r['tracker_slug'] );
			echo '<tr>';
			echo '<td>' . esc_html( (string) $r['observed_at'] ) . '</td>';
			echo '<td><code>' . esc_html( (string) $r['page_url'] ) . '</code></td>';
			echo '<td>' . esc_html( $reg ? $reg['name'] : (string) $r['tracker_slug'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['category'] ) . '</td>';
			echo '<td><code>' . esc_html( (string) ( $r['evidence'] ?? '' ) ) . '</code></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private function render_cookies() : void {
		$rows = CookieStore::recent( 200 );

		$action = esc_url( admin_url( 'admin-post.php' ) );
		echo '<form method="post" action="' . $action . '" style="margin-bottom:1em;">';
		echo '<input type="hidden" name="action" value="eurocomply_eprivacy_export" />';
		echo '<input type="hidden" name="dataset" value="cookies" />';
		wp_nonce_field( 'eurocomply_eprivacy_export' );
		submit_button( __( 'Export cookies CSV', 'eurocomply-eprivacy' ), 'secondary', 'submit', false );
		echo '</form>';

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Observed', 'eurocomply-eprivacy' ) . '</th>';
		echo '<th>' . esc_html__( 'Name',     'eurocomply-eprivacy' ) . '</th>';
		echo '<th>' . esc_html__( 'Domain',   'eurocomply-eprivacy' ) . '</th>';
		echo '<th>' . esc_html__( 'Tracker',  'eurocomply-eprivacy' ) . '</th>';
		echo '<th>' . esc_html__( 'Category', 'eurocomply-eprivacy' ) . '</th>';
		echo '<th>' . esc_html__( 'Page',     'eurocomply-eprivacy' ) . '</th>';
		echo '</tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No cookies observed yet. Visit your site as an anonymous user.', 'eurocomply-eprivacy' ) . '</td></tr>';
		}
		foreach ( $rows as $r ) {
			$reg = '' !== (string) $r['tracker_slug'] ? TrackerRegistry::get( (string) $r['tracker_slug'] ) : null;
			echo '<tr>';
			echo '<td>' . esc_html( (string) $r['observed_at'] ) . '</td>';
			echo '<td><code>' . esc_html( (string) $r['cookie_name'] ) . '</code></td>';
			echo '<td>' . esc_html( (string) $r['cookie_domain'] ) . '</td>';
			echo '<td>' . esc_html( $reg ? $reg['name'] : __( 'Unknown', 'eurocomply-eprivacy' ) ) . '</td>';
			echo '<td>' . esc_html( (string) $r['category'] ) . '</td>';
			echo '<td><code>' . esc_html( (string) $r['page_url'] ) . '</code></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private function render_settings() : void {
		$s = Settings::get();
		$action = esc_url( admin_url( 'admin-post.php' ) );
		echo '<form method="post" action="' . $action . '">';
		echo '<input type="hidden" name="action" value="eurocomply_eprivacy_save" />';
		wp_nonce_field( self::NONCE_SAVE );
		echo '<table class="form-table" role="presentation"><tbody>';

		echo '<tr><th><label for="scan_urls">' . esc_html__( 'URLs to scan', 'eurocomply-eprivacy' ) . '</label></th>';
		echo '<td><textarea id="scan_urls" name="eurocomply_eprivacy_settings[scan_urls]" rows="6" class="large-text code">' . esc_textarea( implode( "\n", (array) $s['scan_urls'] ) ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'One per line. Use a leading "/" for a path on this site, or a full https://… URL. Up to 50 entries.', 'eurocomply-eprivacy' ) . '</p></td></tr>';

		echo '<tr><th><label>' . esc_html__( 'Live cookie observer', 'eurocomply-eprivacy' ) . '</label></th>';
		echo '<td><label><input type="checkbox" name="eurocomply_eprivacy_settings[enable_cookie_observer]" value="1"' . checked( ! empty( $s['enable_cookie_observer'] ), true, false ) . ' /> ' . esc_html__( 'Inject a JS sniffer in wp_footer to capture cookie names (never values).', 'eurocomply-eprivacy' ) . '</label></td></tr>';

		echo '<tr><th><label for="observer_sample_rate">' . esc_html__( 'Observer sample %', 'eurocomply-eprivacy' ) . '</label></th>';
		echo '<td><input type="number" id="observer_sample_rate" name="eurocomply_eprivacy_settings[observer_sample_rate]" value="' . esc_attr( (string) $s['observer_sample_rate'] ) . '" min="1" max="100" /></td></tr>';

		echo '<tr><th><label for="http_timeout">' . esc_html__( 'HTTP timeout (s)', 'eurocomply-eprivacy' ) . '</label></th>';
		echo '<td><input type="number" id="http_timeout" name="eurocomply_eprivacy_settings[http_timeout]" value="' . esc_attr( (string) $s['http_timeout'] ) . '" min="3" max="60" /></td></tr>';

		echo '<tr><th><label for="http_user_agent">' . esc_html__( 'Scanner user-agent', 'eurocomply-eprivacy' ) . '</label></th>';
		echo '<td><input type="text" id="http_user_agent" name="eurocomply_eprivacy_settings[http_user_agent]" value="' . esc_attr( (string) $s['http_user_agent'] ) . '" class="regular-text" /></td></tr>';

		echo '<tr><th><label>' . esc_html__( 'Follow redirects', 'eurocomply-eprivacy' ) . '</label></th>';
		echo '<td><label><input type="checkbox" name="eurocomply_eprivacy_settings[follow_redirects]" value="1"' . checked( ! empty( $s['follow_redirects'] ), true, false ) . ' /> ' . esc_html__( 'Up to 5 redirects per fetch.', 'eurocomply-eprivacy' ) . '</label></td></tr>';

		echo '<tr><th><label for="gap_email_recipients">' . esc_html__( 'Gap email recipients', 'eurocomply-eprivacy' ) . '</label></th>';
		echo '<td><input type="text" id="gap_email_recipients" name="eurocomply_eprivacy_settings[gap_email_recipients]" value="' . esc_attr( (string) $s['gap_email_recipients'] ) . '" class="regular-text" />';
		echo '<p class="description">' . esc_html__( 'Pro: comma-separated list of addresses notified when scanning surfaces a new compliance gap.', 'eurocomply-eprivacy' ) . '</p></td></tr>';

		echo '<tr><th><label for="organisation_country">' . esc_html__( 'Organisation country', 'eurocomply-eprivacy' ) . '</label></th>';
		echo '<td><input type="text" id="organisation_country" name="eurocomply_eprivacy_settings[organisation_country]" value="' . esc_attr( (string) $s['organisation_country'] ) . '" maxlength="2" /></td></tr>';

		echo '</tbody></table>';
		submit_button();
		echo '</form>';
	}

	private function render_pro() : void {
		echo '<h2>' . esc_html__( 'Pro features', 'eurocomply-eprivacy' ) . '</h2>';
		echo '<ul class="ul-disc" style="margin-left:1.4em;">';
		foreach ( array(
			__( 'Hourly WP-Cron scan with email digest of new compliance gaps',           'eurocomply-eprivacy' ),
			__( 'Headless Chrome / browserless deep scan (executes JS, follows network)', 'eurocomply-eprivacy' ),
			__( 'JS event capture (gtag(), fbq(), dataLayer.push)',                       'eurocomply-eprivacy' ),
			__( 'IAB TCF v2.2 stub + GVL vendor lookup',                                  'eurocomply-eprivacy' ),
			__( 'Slack / Teams / PagerDuty webhook on new tracker detection',             'eurocomply-eprivacy' ),
			__( 'Signed PDF audit report (DPO-ready)',                                    'eurocomply-eprivacy' ),
			__( 'REST API: /eurocomply/v1/eprivacy/{scans,findings,cookies}',             'eurocomply-eprivacy' ),
			__( '5,000-row CSV export cap',                                               'eurocomply-eprivacy' ),
			__( 'WPML / Polylang multi-language scan profiles',                           'eurocomply-eprivacy' ),
			__( 'Auto-fix: write missing categories into Cookie Consent #2',              'eurocomply-eprivacy' ),
			__( 'Multi-site network aggregator',                                          'eurocomply-eprivacy' ),
		) as $line ) {
			echo '<li>' . esc_html( $line ) . '</li>';
		}
		echo '</ul>';
	}

	private function render_license() : void {
		$d      = License::get();
		$status = ! empty( $d['status'] ) ? (string) $d['status'] : 'inactive';
		$key    = ! empty( $d['key'] )    ? (string) $d['key']    : '';
		$action = esc_url( admin_url( 'admin-post.php' ) );
		echo '<form method="post" action="' . $action . '">';
		echo '<input type="hidden" name="action" value="eurocomply_eprivacy_license" />';
		wp_nonce_field( self::NONCE_LICENSE );
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th><label for="license_key">' . esc_html__( 'License key', 'eurocomply-eprivacy' ) . '</label></th>';
		echo '<td><input type="text" id="license_key" name="license_key" value="' . esc_attr( $key ) . '" class="regular-text" placeholder="EC-XXXXXX" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Status', 'eurocomply-eprivacy' ) . '</th><td><code>' . esc_html( $status ) . '</code></td></tr>';
		echo '</tbody></table>';
		submit_button( License::is_pro() ? __( 'Deactivate', 'eurocomply-eprivacy' ) : __( 'Activate', 'eurocomply-eprivacy' ) );
		echo '</form>';
	}

	public function handle_save() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-eprivacy' ), 403 );
		}
		check_admin_referer( self::NONCE_SAVE );
		$input = isset( $_POST['eurocomply_eprivacy_settings'] ) && is_array( $_POST['eurocomply_eprivacy_settings'] )
			? wp_unslash( (array) $_POST['eurocomply_eprivacy_settings'] )
			: array();
		update_option( Settings::OPTION_KEY, Settings::sanitize( $input ), false );
		add_settings_error( 'eurocomply_eprivacy', 'saved', __( 'Saved.', 'eurocomply-eprivacy' ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'settings', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_license() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-eprivacy' ), 403 );
		}
		check_admin_referer( self::NONCE_LICENSE );
		$key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['license_key'] ) ) : '';
		if ( License::is_pro() ) {
			License::deactivate();
			add_settings_error( 'eurocomply_eprivacy', 'lic-off', __( 'License deactivated.', 'eurocomply-eprivacy' ), 'updated' );
		} else {
			$res = License::activate( $key );
			add_settings_error( 'eurocomply_eprivacy', 'lic', $res['message'], $res['ok'] ? 'updated' : 'error' );
		}
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'license', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_scan() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-eprivacy' ), 403 );
		}
		check_admin_referer( self::NONCE_SCAN );
		$res = Scanner::run();
		add_settings_error(
			'eurocomply_eprivacy',
			'scan',
			sprintf(
				/* translators: 1: number of URLs, 2: number of trackers detected */
				esc_html__( 'Scanned %1$d URLs, detected %2$d trackers.', 'eurocomply-eprivacy' ),
				(int) $res['urls'],
				(int) $res['findings']
			),
			'updated'
		);
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'scan', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}
}
