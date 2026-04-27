<?php
/**
 * Admin UI.
 *
 * @package EuroComply\Dashboard
 */

declare( strict_types = 1 );

namespace EuroComply\Dashboard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin {

	public const MENU_SLUG    = 'eurocomply-dashboard';
	public const NONCE_SAVE   = 'eurocomply_dashboard_save';
	public const NONCE_LIC    = 'eurocomply_dashboard_license';
	public const NONCE_RUN    = 'eurocomply_dashboard_run';
	public const ACTION_SAVE  = 'eurocomply_dashboard_save';
	public const ACTION_LIC   = 'eurocomply_dashboard_license';
	public const ACTION_RUN   = 'eurocomply_dashboard_run_snapshot';

	private static ?Admin $instance = null;

	public static function instance() : Admin {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register();
		}
		return self::$instance;
	}

	private function register() : void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_' . self::ACTION_SAVE, array( $this, 'handle_save' ) );
		add_action( 'admin_post_' . self::ACTION_LIC, array( $this, 'handle_license' ) );
		add_action( 'admin_post_' . self::ACTION_RUN, array( $this, 'handle_run' ) );
	}

	public function register_menu() : void {
		add_menu_page(
			__( 'EuroComply', 'eurocomply-dashboard' ),
			__( 'EuroComply', 'eurocomply-dashboard' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render' ),
			'dashicons-shield-alt',
			75
		);
	}

	public function assets( string $hook ) : void {
		if ( false === strpos( $hook, self::MENU_SLUG ) ) {
			return;
		}
		wp_enqueue_style( self::MENU_SLUG . '-admin', EUROCOMPLY_DASH_URL . 'assets/css/admin.css', array(), EUROCOMPLY_DASH_VERSION );
	}

	public function render() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( (string) $_GET['tab'] ) : 'overview'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tabs = array(
			'overview' => __( 'Overview', 'eurocomply-dashboard' ),
			'plugins'  => __( 'Plugins', 'eurocomply-dashboard' ),
			'alerts'   => __( 'Alerts', 'eurocomply-dashboard' ),
			'calendar' => __( 'Calendar', 'eurocomply-dashboard' ),
			'history'  => __( 'History', 'eurocomply-dashboard' ),
			'settings' => __( 'Settings', 'eurocomply-dashboard' ),
			'pro'      => __( 'Pro', 'eurocomply-dashboard' ),
			'license'  => __( 'License', 'eurocomply-dashboard' ),
		);
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'overview';
		}

		echo '<div class="wrap eurocomply-dash-wrap">';
		echo '<h1>' . esc_html__( 'EuroComply Compliance Dashboard', 'eurocomply-dashboard' ) . '</h1>';

		if ( isset( $_GET['settings-updated'] ) && 'true' === (string) $_GET['settings-updated'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			settings_errors( 'eurocomply_dashboard' );
		}

		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $tabs as $slug => $label ) {
			$url = add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => $slug ), admin_url( 'admin.php' ) );
			printf(
				'<a class="nav-tab%1$s" href="%2$s">%3$s</a>',
				$tab === $slug ? ' nav-tab-active' : '',
				esc_url( $url ),
				esc_html( $label )
			);
		}
		echo '</h2>';

		switch ( $tab ) {
			case 'plugins':  $this->render_plugins();  break;
			case 'alerts':   $this->render_alerts();   break;
			case 'calendar': $this->render_calendar(); break;
			case 'history':  $this->render_history();  break;
			case 'settings': $this->render_settings(); break;
			case 'pro':      $this->render_pro();      break;
			case 'license':  $this->render_license();  break;
			default:         $this->render_overview();
		}

		echo '</div>';
	}

	private function render_overview() : void {
		$payload = Aggregator::snapshot_payload();
		$score   = (int) $payload['overall'];
		$label   = Aggregator::score_label( $score );

		echo '<div class="eurocomply-dash-hero eurocomply-dash-hero--' . esc_attr( $label ) . '">';
		echo '<div class="eurocomply-dash-hero__score">' . esc_html( (string) $score ) . '</div>';
		echo '<div class="eurocomply-dash-hero__caption">';
		echo '<h2>' . esc_html__( 'Compliance score', 'eurocomply-dashboard' ) . '</h2>';
		echo '<p>' . sprintf(
			/* translators: 1: active 2: total */
			esc_html__( '%1$d of %2$d EuroComply plugins active.', 'eurocomply-dashboard' ),
			(int) $payload['active_count'],
			(int) $payload['total_count']
		) . '</p>';
		if ( (int) $payload['alert_count'] > 0 ) {
			echo '<p><a href="' . esc_url( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'alerts' ), admin_url( 'admin.php' ) ) ) . '">' . esc_html( sprintf( /* translators: %d count */ _n( '%d open alert', '%d open alerts', (int) $payload['alert_count'], 'eurocomply-dashboard' ), (int) $payload['alert_count'] ) ) . '</a></p>';
		}
		echo '</div>';
		echo '</div>';

		echo '<div class="eurocomply-dash-grid">';
		foreach ( $payload['plugins'] as $p ) {
			$cls   = 'eurocomply-dash-card eurocomply-dash-card--' . esc_attr( Aggregator::score_label( (int) $p['score'] ) );
			if ( ! $p['active'] ) {
				$cls .= ' eurocomply-dash-card--inactive';
			}
			echo '<div class="' . esc_attr( $cls ) . '">';
			echo '<div class="eurocomply-dash-card__head">';
			echo '<a class="eurocomply-dash-card__title" href="' . esc_url( (string) $p['menu_url'] ) . '">' . esc_html( (string) $p['name'] ) . '</a>';
			echo '<span class="eurocomply-dash-card__score">' . ( $p['active'] ? esc_html( (string) $p['score'] ) : '—' ) . '</span>';
			echo '</div>';
			echo '<div class="eurocomply-dash-card__ref">' . esc_html( (string) $p['reference'] ) . '</div>';
			if ( ! empty( $p['metrics'] ) ) {
				echo '<ul class="eurocomply-dash-card__metrics">';
				foreach ( (array) $p['metrics'] as $m ) {
					printf( '<li><strong>%s</strong> <span>%s</span></li>', esc_html( (string) $m['value'] ), esc_html( (string) $m['label'] ) );
				}
				echo '</ul>';
			}
			if ( ! empty( $p['alerts'] ) ) {
				echo '<ul class="eurocomply-dash-card__alerts">';
				foreach ( (array) $p['alerts'] as $a ) {
					$ac = Aggregator::severity_class( (string) ( $a['severity'] ?? 'info' ) );
					printf(
						'<li class="eurocomply-dash-alert %s">%s</li>',
						esc_attr( $ac ),
						esc_html( (string) ( $a['message'] ?? '' ) )
					);
				}
				echo '</ul>';
			}
			if ( ! $p['active'] ) {
				echo '<p class="eurocomply-dash-card__inactive">' . esc_html__( 'Plugin not detected. Install or activate to include in the score.', 'eurocomply-dashboard' ) . '</p>';
			}
			echo '</div>';
		}
		echo '</div>';
	}

	private function render_plugins() : void {
		$payload = Aggregator::snapshot_payload();
		echo '<p><a class="button" href="' . esc_url( CsvExport::url( 'plugins' ) ) . '">' . esc_html__( 'Export CSV', 'eurocomply-dashboard' ) . '</a></p>';
		echo '<table class="widefat striped"><thead><tr>';
		foreach ( array( __( 'Plugin', 'eurocomply-dashboard' ), __( 'Reference', 'eurocomply-dashboard' ), __( 'Active', 'eurocomply-dashboard' ), __( 'Pro', 'eurocomply-dashboard' ), __( 'Score', 'eurocomply-dashboard' ), __( 'Alerts', 'eurocomply-dashboard' ), '' ) as $h ) {
			echo '<th>' . esc_html( $h ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $payload['plugins'] as $p ) {
			echo '<tr>';
			printf( '<td><strong>%s</strong></td>', esc_html( (string) $p['name'] ) );
			printf( '<td><code>%s</code></td>', esc_html( (string) $p['reference'] ) );
			printf( '<td>%s</td>', $p['active'] ? esc_html__( 'Yes', 'eurocomply-dashboard' ) : '—' );
			printf( '<td>%s</td>', $p['pro'] ? esc_html__( 'Yes', 'eurocomply-dashboard' ) : '—' );
			printf( '<td>%s</td>', $p['active'] ? esc_html( (string) $p['score'] ) : '—' );
			printf( '<td>%d</td>', count( (array) $p['alerts'] ) );
			printf( '<td><a class="button" href="%s">%s</a></td>', esc_url( (string) $p['menu_url'] ), esc_html__( 'Open', 'eurocomply-dashboard' ) );
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private function render_alerts() : void {
		$payload = Aggregator::snapshot_payload();
		echo '<p><a class="button" href="' . esc_url( CsvExport::url( 'alerts' ) ) . '">' . esc_html__( 'Export CSV', 'eurocomply-dashboard' ) . '</a></p>';
		if ( empty( $payload['alerts'] ) ) {
			echo '<p>' . esc_html__( 'No open alerts. Compliance posture is clean across active plugins.', 'eurocomply-dashboard' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr>';
		foreach ( array( __( 'Severity', 'eurocomply-dashboard' ), __( 'Plugin', 'eurocomply-dashboard' ), __( 'Message', 'eurocomply-dashboard' ), '' ) as $h ) {
			echo '<th>' . esc_html( $h ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $payload['alerts'] as $a ) {
			$sev = (string) ( $a['severity'] ?? 'info' );
			echo '<tr>';
			printf( '<td><span class="eurocomply-dash-alert %s">%s</span></td>', esc_attr( Aggregator::severity_class( $sev ) ), esc_html( strtoupper( $sev ) ) );
			printf( '<td>%s</td>', esc_html( (string) ( $a['plugin'] ?? '' ) ) );
			printf( '<td>%s</td>', esc_html( (string) ( $a['message'] ?? '' ) ) );
			$link = (string) ( $a['link'] ?? '' );
			printf( '<td>%s</td>', '' !== $link ? '<a class="button" href="' . esc_url( $link ) . '">' . esc_html__( 'Open', 'eurocomply-dashboard' ) . '</a>' : '' );
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private function render_calendar() : void {
		echo '<p>' . esc_html__( 'Upcoming statutory deadlines aggregated from active plugins.', 'eurocomply-dashboard' ) . '</p>';
		$items = array(
			array( '24 h',  'NIS2 Art. 23',          __( 'Early-warning notification to CSIRT after a significant incident.', 'eurocomply-dashboard' ) ),
			array( '72 h',  'NIS2 Art. 23',          __( 'Full incident notification to CSIRT.', 'eurocomply-dashboard' ) ),
			array( '30 d',  'GDPR Art. 12(3)',       __( 'Response deadline for DSAR (extendable once by 2 months).', 'eurocomply-dashboard' ) ),
			array( '30 d',  'NIS2 Art. 23',          __( 'Intermediate report on incident handling.', 'eurocomply-dashboard' ) ),
			array( '30 d',  'NIS2 Art. 23',          __( 'Final report after incident handling concludes.', 'eurocomply-dashboard' ) ),
			array( 'Q+30',  'EPR (DE/FR/IT/ES)',     __( 'Quarterly producer-responsibility return.', 'eurocomply-dashboard' ) ),
			array( '14 d',  'GPSR Art. 20',          __( 'Recall / safety-incident notification via Safety Gate.', 'eurocomply-dashboard' ) ),
			array( '6 m',   'EAA / EN 301 549',      __( 'Bi-annual accessibility statement review.', 'eurocomply-dashboard' ) ),
			array( '12 m',  'DSA Art. 15 / 24',      __( 'Annual transparency report.', 'eurocomply-dashboard' ) ),
			array( '12 m',  'EU AI Act Art. 50',     __( 'Provider-registry review and AI policy refresh.', 'eurocomply-dashboard' ) ),
			array( '7 d',   'Whistleblower 9(1)(b)', __( 'Acknowledge whistleblower report receipt.', 'eurocomply-dashboard' ) ),
			array( '3 m',   'Whistleblower 9(1)(f)', __( 'Provide feedback to whistleblower on action taken.', 'eurocomply-dashboard' ) ),
			array( '2 m',   'Pay Transp. Art. 7(1)', __( 'Respond to a worker pay-information request.', 'eurocomply-dashboard' ) ),
			array( '12 m',  'Pay Transp. Art. 9',    __( 'Annual gender pay-gap report (employers ≥ 250).', 'eurocomply-dashboard' ) ),
			array( 'Q',     'CBAM Reg. 2023/1773',   __( 'Quarterly CBAM declaration (transitional).', 'eurocomply-dashboard' ) ),
			array( '12 m',  'CSRD / ESRS',           __( 'Annual sustainability statement, third-party assurance.', 'eurocomply-dashboard' ) ),
			array( '4 q',   'PSD2 RTS 2018/389',     __( 'Quarterly fraud-rate review for SCA TRA exemption.', 'eurocomply-dashboard' ) ),
			array( 'Per',   'EUDR Art. 4',           __( 'Submit DDS to TRACES NT before placing on market.', 'eurocomply-dashboard' ) ),
		);
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Window', 'eurocomply-dashboard' ) . '</th><th>' . esc_html__( 'Reference', 'eurocomply-dashboard' ) . '</th><th>' . esc_html__( 'Obligation', 'eurocomply-dashboard' ) . '</th></tr></thead><tbody>';
		foreach ( $items as $row ) {
			echo '<tr>';
			printf( '<td><code>%s</code></td>', esc_html( $row[0] ) );
			printf( '<td>%s</td>', esc_html( $row[1] ) );
			printf( '<td>%s</td>', esc_html( $row[2] ) );
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private function render_history() : void {
		$rows = SnapshotStore::recent( 90 );
		echo '<p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin-right:8px;">';
		wp_nonce_field( self::NONCE_RUN, '_wpnonce' );
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_RUN ) . '" />';
		submit_button( __( 'Capture snapshot now', 'eurocomply-dashboard' ), 'primary', 'submit', false );
		echo '</form>';
		echo ' <a class="button" href="' . esc_url( CsvExport::url( 'snapshots' ) ) . '">' . esc_html__( 'Export CSV', 'eurocomply-dashboard' ) . '</a>';
		echo '</p>';

		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No snapshots captured yet. Pro enables daily auto-capture; the button above triggers a manual capture.', 'eurocomply-dashboard' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr>';
		foreach ( array( 'ID', __( 'Captured at', 'eurocomply-dashboard' ), __( 'Score', 'eurocomply-dashboard' ), __( 'Active plugins', 'eurocomply-dashboard' ), __( 'Alerts', 'eurocomply-dashboard' ) ) as $h ) {
			echo '<th>' . esc_html( $h ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $rows as $r ) {
			echo '<tr>';
			printf( '<td>#%d</td>', (int) $r['id'] );
			printf( '<td>%s</td>', esc_html( (string) $r['occurred_at'] ) );
			printf( '<td><strong>%d</strong></td>', (int) $r['score'] );
			printf( '<td>%d</td>', (int) $r['active_count'] );
			printf( '<td>%d</td>', (int) $r['alert_count'] );
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private function render_settings() : void {
		$s = Settings::get();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( self::NONCE_SAVE, '_wpnonce' ); ?>
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_SAVE ); ?>" />
			<table class="form-table" role="presentation">
				<tr><th><?php esc_html_e( 'Display', 'eurocomply-dashboard' ); ?></th>
				<td>
					<label><input type="checkbox" name="eurocomply_dashboard[show_inactive_plugins]" value="1" <?php checked( ! empty( $s['show_inactive_plugins'] ) ); ?> /> <?php esc_html_e( 'Show inactive plugins on the overview', 'eurocomply-dashboard' ); ?></label><br />
					<label><input type="checkbox" name="eurocomply_dashboard[auto_clear_dismissed]" value="1" <?php checked( ! empty( $s['auto_clear_dismissed'] ) ); ?> /> <?php esc_html_e( 'Auto-clear dismissed alerts after 24 h', 'eurocomply-dashboard' ); ?></label>
				</td></tr>

				<tr><th><?php esc_html_e( 'Snapshot history', 'eurocomply-dashboard' ); ?></th>
				<td>
					<label><input type="checkbox" name="eurocomply_dashboard[enable_daily_snapshot]" value="1" <?php checked( ! empty( $s['enable_daily_snapshot'] ) ); ?> /> <?php esc_html_e( 'Capture a daily snapshot via WP-Cron (Pro)', 'eurocomply-dashboard' ); ?></label><br />
					<label><?php esc_html_e( 'Retention (days)', 'eurocomply-dashboard' ); ?> <input type="number" min="7" max="3650" name="eurocomply_dashboard[snapshot_retention_days]" value="<?php echo esc_attr( (string) (int) $s['snapshot_retention_days'] ); ?>" class="small-text" /></label>
				</td></tr>

				<tr><th><label><?php esc_html_e( 'Organisation name', 'eurocomply-dashboard' ); ?></label></th>
				<td><input type="text" name="eurocomply_dashboard[organisation_name]" class="regular-text" value="<?php echo esc_attr( (string) $s['organisation_name'] ); ?>" /></td></tr>

				<tr><th><label><?php esc_html_e( 'Country (ISO alpha-2)', 'eurocomply-dashboard' ); ?></label></th>
				<td><input type="text" name="eurocomply_dashboard[organisation_country]" class="small-text" maxlength="2" value="<?php echo esc_attr( (string) $s['organisation_country'] ); ?>" /></td></tr>

				<tr><th><label><?php esc_html_e( 'Compliance officer email', 'eurocomply-dashboard' ); ?></label></th>
				<td><input type="email" name="eurocomply_dashboard[compliance_officer_email]" class="regular-text" value="<?php echo esc_attr( (string) $s['compliance_officer_email'] ); ?>" /></td></tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	private function render_pro() : void {
		echo '<p>' . esc_html__( 'Pro turns the dashboard into the source-of-truth for compliance reporting:', 'eurocomply-dashboard' ) . '</p>';
		echo '<ul class="eurocomply-dash-pro-list">';
		$items = array(
			__( 'Daily WP-Cron compliance-score snapshot with retention controls', 'eurocomply-dashboard' ),
			__( 'Email digest to compliance officer (weekly / monthly)', 'eurocomply-dashboard' ),
			__( 'Slack / Teams / PagerDuty webhook on alert', 'eurocomply-dashboard' ),
			__( 'SIEM forwarding (Splunk / ELK / Datadog) for snapshots and alerts', 'eurocomply-dashboard' ),
			__( 'Multisite aggregator — roll up scores across a network', 'eurocomply-dashboard' ),
			__( 'Signed PDF compliance report with all 20 plugin sections', 'eurocomply-dashboard' ),
			__( 'REST API: GET /wp-json/eurocomply/v1/compliance', 'eurocomply-dashboard' ),
			__( '5,000-row CSV cap (vs 500 free)', 'eurocomply-dashboard' ),
			__( 'WPML / Polylang multi-language report templates', 'eurocomply-dashboard' ),
			__( 'Suite-wide upgrade nudges & changelog feed', 'eurocomply-dashboard' ),
		);
		foreach ( $items as $i ) {
			echo '<li>' . esc_html( $i ) . '</li>';
		}
		echo '</ul>';
		echo '<p>' . ( License::is_pro() ? esc_html__( 'Pro is active.', 'eurocomply-dashboard' ) : esc_html__( 'Activate a license in the License tab to unlock Pro stubs.', 'eurocomply-dashboard' ) ) . '</p>';
	}

	private function render_license() : void {
		$license = License::get();
		$is_pro  = License::is_pro();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:520px;">
			<?php wp_nonce_field( self::NONCE_LIC, '_wpnonce' ); ?>
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_LIC ); ?>" />
			<table class="form-table" role="presentation">
				<tr><th><label for="license_key"><?php esc_html_e( 'License key', 'eurocomply-dashboard' ); ?></label></th>
				<td><input type="text" id="license_key" name="license_key" value="<?php echo esc_attr( (string) ( $license['key'] ?? '' ) ); ?>" class="regular-text" placeholder="EC-XXXXXX" />
				<p class="description"><?php echo $is_pro ? esc_html__( 'Pro is active.', 'eurocomply-dashboard' ) : esc_html__( 'Enter a key in the form EC-XXXXXX to unlock Pro stubs.', 'eurocomply-dashboard' ); ?></p></td></tr>
			</table>
			<p class="submit">
				<button type="submit" name="license_op" value="activate" class="button button-primary"><?php esc_html_e( 'Activate', 'eurocomply-dashboard' ); ?></button>
				<button type="submit" name="license_op" value="deactivate" class="button"><?php esc_html_e( 'Deactivate', 'eurocomply-dashboard' ); ?></button>
			</p>
		</form>
		<?php
	}

	public function handle_save() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-dashboard' ), 403 );
		}
		check_admin_referer( self::NONCE_SAVE );
		$input = isset( $_POST['eurocomply_dashboard'] ) && is_array( $_POST['eurocomply_dashboard'] )
			? wp_unslash( (array) $_POST['eurocomply_dashboard'] )
			: array();
		update_option( Settings::OPTION_KEY, Settings::sanitize( $input ), false );

		add_settings_error( 'eurocomply_dashboard', 'saved', __( 'Settings saved.', 'eurocomply-dashboard' ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );

		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'settings', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_license() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-dashboard' ), 403 );
		}
		check_admin_referer( self::NONCE_LIC );

		$op  = isset( $_POST['license_op'] ) ? sanitize_key( (string) $_POST['license_op'] ) : '';
		$key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['license_key'] ) ) : '';

		if ( 'deactivate' === $op ) {
			License::deactivate();
			add_settings_error( 'eurocomply_dashboard', 'lic_off', __( 'License deactivated.', 'eurocomply-dashboard' ), 'updated' );
		} else {
			$res = License::activate( $key );
			add_settings_error( 'eurocomply_dashboard', 'lic', $res['message'], $res['ok'] ? 'updated' : 'error' );
		}
		set_transient( 'settings_errors', get_settings_errors(), 30 );

		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'license', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_run() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-dashboard' ), 403 );
		}
		check_admin_referer( self::NONCE_RUN );
		Aggregator::snapshot();
		add_settings_error( 'eurocomply_dashboard', 'snap', __( 'Snapshot captured.', 'eurocomply-dashboard' ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'history', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}
}
