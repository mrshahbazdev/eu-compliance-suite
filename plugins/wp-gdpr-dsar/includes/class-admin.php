<?php
/**
 * Admin UI.
 *
 * @package EuroComply\DSAR
 */

declare( strict_types = 1 );

namespace EuroComply\DSAR;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin {

	public const MENU_SLUG   = 'eurocomply-dsar';
	public const NONCE_SAVE  = 'eurocomply_dsar_save';
	public const NONCE_LIC   = 'eurocomply_dsar_license';
	public const NONCE_REQ   = 'eurocomply_dsar_request';
	public const ACTION_SAVE = 'eurocomply_dsar_save';
	public const ACTION_LIC  = 'eurocomply_dsar_license';
	public const ACTION_REQ  = 'eurocomply_dsar_request';

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
		add_action( 'admin_post_' . self::ACTION_REQ, array( $this, 'handle_request_action' ) );
	}

	public function register_menu() : void {
		add_menu_page(
			__( 'EuroComply DSAR', 'eurocomply-dsar' ),
			__( 'EuroComply DSAR', 'eurocomply-dsar' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render' ),
			'dashicons-privacy',
			80
		);
	}

	public function assets( string $hook ) : void {
		if ( false === strpos( $hook, self::MENU_SLUG ) ) {
			return;
		}
		wp_enqueue_style(
			self::MENU_SLUG . '-admin',
			EUROCOMPLY_DSAR_URL . 'assets/css/admin.css',
			array(),
			EUROCOMPLY_DSAR_VERSION
		);
	}

	public function render() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_key( (string) $_GET['tab'] ) : 'dashboard'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tabs = array(
			'dashboard' => __( 'Dashboard', 'eurocomply-dsar' ),
			'requests'  => __( 'Requests', 'eurocomply-dsar' ),
			'settings'  => __( 'Settings', 'eurocomply-dsar' ),
			'exporters' => __( 'Exporters', 'eurocomply-dsar' ),
			'pro'       => __( 'Pro', 'eurocomply-dsar' ),
			'license'   => __( 'License', 'eurocomply-dsar' ),
		);
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'dashboard';
		}

		echo '<div class="wrap eurocomply-dsar-wrap">';
		echo '<h1>' . esc_html__( 'EuroComply GDPR DSAR', 'eurocomply-dsar' ) . '</h1>';

		if ( isset( $_GET['settings-updated'] ) && 'true' === (string) $_GET['settings-updated'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			settings_errors( 'eurocomply_dsar' );
		}

		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $tabs as $slug => $label ) {
			$url = add_query_arg(
				array(
					'page' => self::MENU_SLUG,
					'tab'  => $slug,
				),
				admin_url( 'admin.php' )
			);
			printf(
				'<a class="nav-tab%1$s" href="%2$s">%3$s</a>',
				$tab === $slug ? ' nav-tab-active' : '',
				esc_url( $url ),
				esc_html( $label )
			);
		}
		echo '</h2>';

		switch ( $tab ) {
			case 'requests':
				$this->render_requests();
				break;
			case 'settings':
				$this->render_settings();
				break;
			case 'exporters':
				$this->render_exporters();
				break;
			case 'pro':
				$this->render_pro();
				break;
			case 'license':
				$this->render_license();
				break;
			default:
				$this->render_dashboard();
		}

		echo '</div>';
	}

	private function render_dashboard() : void {
		$counts   = RequestStore::status_counts();
		$overdue  = RequestStore::count_overdue();
		$received = $counts['received'] ?? 0;
		$in_prog  = $counts['in_progress'] ?? 0;
		$done     = $counts['completed'] ?? 0;

		echo '<div class="eurocomply-dsar-cards">';
		$cards = array(
			array( __( 'Received', 'eurocomply-dsar' ), $received ),
			array( __( 'In progress', 'eurocomply-dsar' ), $in_prog ),
			array( __( 'Completed', 'eurocomply-dsar' ), $done ),
			array( __( 'Overdue', 'eurocomply-dsar' ), $overdue ),
		);
		foreach ( $cards as $card ) {
			printf(
				'<div class="eurocomply-dsar-card"><div class="eurocomply-dsar-card__value">%1$s</div><div class="eurocomply-dsar-card__label">%2$s</div></div>',
				esc_html( (string) $card[1] ),
				esc_html( (string) $card[0] )
			);
		}
		echo '</div>';

		echo '<h2>' . esc_html__( 'Latest requests', 'eurocomply-dsar' ) . '</h2>';
		$this->render_requests_table( RequestStore::recent( 10 ) );
	}

	private function render_requests() : void {
		$status_filter = isset( $_GET['status'] ) ? sanitize_key( (string) $_GET['status'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$rows          = RequestStore::recent( 200, $status_filter );

		echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '" style="margin:12px 0;">';
		echo '<input type="hidden" name="page" value="' . esc_attr( self::MENU_SLUG ) . '" />';
		echo '<input type="hidden" name="tab" value="requests" />';
		echo '<label>' . esc_html__( 'Status', 'eurocomply-dsar' ) . ' ';
		echo '<select name="status">';
		echo '<option value="">' . esc_html__( 'All', 'eurocomply-dsar' ) . '</option>';
		foreach ( RequestStore::STATUSES as $s ) {
			printf(
				'<option value="%1$s"%3$s>%2$s</option>',
				esc_attr( $s ),
				esc_html( $s ),
				selected( $status_filter, $s, false )
			);
		}
		echo '</select></label> ';
		submit_button( __( 'Filter', 'eurocomply-dsar' ), 'secondary', '', false );
		echo ' <a class="button" href="' . esc_url( CsvExport::url() ) . '">' . esc_html__( 'Export CSV', 'eurocomply-dsar' ) . '</a>';
		echo '</form>';

		$this->render_requests_table( $rows );
	}

	/**
	 * @param array<int,array<string,mixed>> $rows
	 */
	private function render_requests_table( array $rows ) : void {
		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No requests yet.', 'eurocomply-dsar' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped"><thead><tr>';
		foreach ( array( __( 'ID', 'eurocomply-dsar' ), __( 'Submitted', 'eurocomply-dsar' ), __( 'Type', 'eurocomply-dsar' ), __( 'Email', 'eurocomply-dsar' ), __( 'Status', 'eurocomply-dsar' ), __( 'Verified', 'eurocomply-dsar' ), __( 'Deadline', 'eurocomply-dsar' ), __( 'Breach', 'eurocomply-dsar' ), __( 'Actions', 'eurocomply-dsar' ) ) as $label ) {
			echo '<th>' . esc_html( $label ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			$id         = (int) $row['id'];
			$email      = (string) $row['requester_email'];
			$type       = (string) $row['request_type'];
			$status     = (string) $row['status'];
			$overdue    = ! empty( $row['deadline_at'] ) && strtotime( (string) $row['deadline_at'] ) < time() && ! in_array( $status, array( 'completed', 'rejected', 'cancelled', 'expired' ), true );
			$verified   = ! empty( $row['verified'] );

			echo '<tr>';
			printf( '<td>#%d</td>', $id );
			printf( '<td>%s</td>', esc_html( (string) $row['submitted_at'] ) );
			printf( '<td>%s</td>', esc_html( RequestForm::type_label( $type ) ) );
			printf( '<td>%s</td>', esc_html( $email ) );
			printf( '<td><code>%s</code></td>', esc_html( $status ) );
			printf( '<td>%s</td>', $verified ? esc_html__( 'Yes', 'eurocomply-dsar' ) : esc_html__( 'No', 'eurocomply-dsar' ) );
			printf( '<td%s>%s</td>', $overdue ? ' style="color:#d63638;font-weight:600"' : '', esc_html( (string) ( $row['deadline_at'] ?? '' ) ) );
			$breach_flag = ! empty( $row['breach_flag'] );
			$nis2_id     = (int) ( $row['nis2_incident_id'] ?? 0 );
			if ( $breach_flag ) {
				$label = $nis2_id > 0
					/* translators: %d: NIS2 incident id. */
					? sprintf( __( 'Yes → NIS2 #%d', 'eurocomply-dsar' ), $nis2_id )
					: __( 'Yes', 'eurocomply-dsar' );
				printf( '<td><strong style="color:#d63638">%s</strong></td>', esc_html( $label ) );
			} else {
				echo '<td>—</td>';
			}
			echo '<td>';
			$this->render_row_actions( $id, $type, $status, $breach_flag );
			echo '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private function render_row_actions( int $id, string $type, string $status, bool $breach_flag = false ) : void {
		$base = admin_url( 'admin-post.php?action=' . self::ACTION_REQ );
		$url  = static function ( int $id, string $op ) use ( $base ) : string {
			return wp_nonce_url(
				add_query_arg(
					array( 'request_id' => $id, 'op' => $op ),
					$base
				),
				self::NONCE_REQ,
				'_wpnonce'
			);
		};

		if ( in_array( $type, array( 'access', 'portability' ), true ) && 'completed' !== $status ) {
			printf( '<a class="button button-primary" href="%s">%s</a> ', esc_url( $url( $id, 'export' ) ), esc_html__( 'Build export', 'eurocomply-dsar' ) );
		}
		if ( 'erase' === $type && 'completed' !== $status ) {
			printf( '<a class="button" href="%s" onclick="return confirm(\'%s\');">%s</a> ', esc_url( $url( $id, 'erase' ) ), esc_js( __( 'This will run all personal-data erasers and delete the user account after the grace period. Continue?', 'eurocomply-dsar' ) ), esc_html__( 'Run erasure', 'eurocomply-dsar' ) );
		}
		if ( ! in_array( $status, array( 'completed', 'rejected', 'cancelled' ), true ) ) {
			printf( '<a class="button" href="%s">%s</a> ', esc_url( $url( $id, 'complete' ) ), esc_html__( 'Mark complete', 'eurocomply-dsar' ) );
			printf( '<a class="button" href="%s">%s</a> ', esc_url( $url( $id, 'reject' ) ), esc_html__( 'Reject', 'eurocomply-dsar' ) );
		}
		if ( $breach_flag ) {
			printf( '<a class="button" href="%s">%s</a> ', esc_url( $url( $id, 'unflag_breach' ) ), esc_html__( 'Unflag breach', 'eurocomply-dsar' ) );
		} else {
			printf( '<a class="button" href="%s" onclick="return confirm(\'%s\');">%s</a> ', esc_url( $url( $id, 'flag_breach' ) ), esc_js( __( 'Mark this DSAR request as a personal-data breach? This will create a linked NIS2 incident with 24h/72h Art. 23 deadlines if EuroComply NIS2 is active.', 'eurocomply-dsar' ) ), esc_html__( 'Flag as breach', 'eurocomply-dsar' ) );
		}
	}

	private function render_settings() : void {
		$s = Settings::get();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( self::NONCE_SAVE, '_wpnonce' ); ?>
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_SAVE ); ?>" />

			<h2><?php esc_html_e( 'Deadlines & verification', 'eurocomply-dsar' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="response_deadline_days"><?php esc_html_e( 'Response deadline (days)', 'eurocomply-dsar' ); ?></label></th>
					<td>
						<input type="number" id="response_deadline_days" name="eurocomply_dsar[response_deadline_days]" value="<?php echo esc_attr( (string) $s['response_deadline_days'] ); ?>" min="7" max="90" />
						<p class="description"><?php esc_html_e( 'GDPR Art. 12(3): 30 days. Extensible to +2 months under Pro.', 'eurocomply-dsar' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="verification_required"><?php esc_html_e( 'Require email verification', 'eurocomply-dsar' ); ?></label></th>
					<td><label><input type="checkbox" id="verification_required" name="eurocomply_dsar[verification_required]" value="1" <?php checked( ! empty( $s['verification_required'] ) ); ?> /> <?php esc_html_e( 'Require the requester to click a confirmation link (Art. 12(6)).', 'eurocomply-dsar' ); ?></label></td>
				</tr>
				<tr>
					<th><label for="verification_token_ttl_h"><?php esc_html_e( 'Verification link validity (hours)', 'eurocomply-dsar' ); ?></label></th>
					<td><input type="number" id="verification_token_ttl_h" name="eurocomply_dsar[verification_token_ttl_h]" value="<?php echo esc_attr( (string) $s['verification_token_ttl_h'] ); ?>" min="1" max="720" /></td>
				</tr>
				<tr>
					<th><label for="allow_anonymous_requests"><?php esc_html_e( 'Allow anonymous requests', 'eurocomply-dsar' ); ?></label></th>
					<td><label><input type="checkbox" id="allow_anonymous_requests" name="eurocomply_dsar[allow_anonymous_requests]" value="1" <?php checked( ! empty( $s['allow_anonymous_requests'] ) ); ?> /> <?php esc_html_e( 'Allow users without a WordPress account to submit requests.', 'eurocomply-dsar' ); ?></label></td>
				</tr>
				<tr>
					<th><label for="rate_limit_per_hour"><?php esc_html_e( 'Rate limit (requests / IP / hour)', 'eurocomply-dsar' ); ?></label></th>
					<td><input type="number" id="rate_limit_per_hour" name="eurocomply_dsar[rate_limit_per_hour]" value="<?php echo esc_attr( (string) $s['rate_limit_per_hour'] ); ?>" min="0" max="100" /></td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Email', 'eurocomply-dsar' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="from_name"><?php esc_html_e( 'From name', 'eurocomply-dsar' ); ?></label></th>
					<td><input type="text" id="from_name" name="eurocomply_dsar[from_name]" value="<?php echo esc_attr( (string) $s['from_name'] ); ?>" class="regular-text" /></td>
				</tr>
				<tr>
					<th><label for="from_email"><?php esc_html_e( 'From email', 'eurocomply-dsar' ); ?></label></th>
					<td><input type="email" id="from_email" name="eurocomply_dsar[from_email]" value="<?php echo esc_attr( (string) $s['from_email'] ); ?>" class="regular-text" /></td>
				</tr>
				<tr>
					<th><label for="notification_emails"><?php esc_html_e( 'Admin notification emails', 'eurocomply-dsar' ); ?></label></th>
					<td>
						<textarea id="notification_emails" name="eurocomply_dsar[notification_emails]" rows="3" class="large-text" placeholder="dpo@example.com, privacy@example.com"><?php echo esc_textarea( implode( ', ', (array) $s['notification_emails'] ) ); ?></textarea>
					</td>
				</tr>
				<tr>
					<th><label for="auto_ack_email"><?php esc_html_e( 'Send acknowledgement email', 'eurocomply-dsar' ); ?></label></th>
					<td><label><input type="checkbox" id="auto_ack_email" name="eurocomply_dsar[auto_ack_email]" value="1" <?php checked( ! empty( $s['auto_ack_email'] ) ); ?> /> <?php esc_html_e( 'Automatically email the requester when a request is received (if verification is disabled).', 'eurocomply-dsar' ); ?></label></td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Data categories', 'eurocomply-dsar' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th><?php esc_html_e( 'Include', 'eurocomply-dsar' ); ?></th>
					<td>
						<label><input type="checkbox" name="eurocomply_dsar[include_user_meta]" value="1" <?php checked( ! empty( $s['include_user_meta'] ) ); ?> /> <?php esc_html_e( 'User profile & meta', 'eurocomply-dsar' ); ?></label><br />
						<label><input type="checkbox" name="eurocomply_dsar[include_post_authorship]" value="1" <?php checked( ! empty( $s['include_post_authorship'] ) ); ?> /> <?php esc_html_e( 'Post authorship records', 'eurocomply-dsar' ); ?></label><br />
						<label><input type="checkbox" name="eurocomply_dsar[include_comments]" value="1" <?php checked( ! empty( $s['include_comments'] ) ); ?> /> <?php esc_html_e( 'Comments', 'eurocomply-dsar' ); ?></label><br />
						<label><input type="checkbox" name="eurocomply_dsar[include_wc_orders]" value="1" <?php checked( ! empty( $s['include_wc_orders'] ) ); ?> /> <?php esc_html_e( 'WooCommerce orders & customers', 'eurocomply-dsar' ); ?></label>
						<p class="description"><?php esc_html_e( 'Data aggregation is delegated to WordPress\'s built-in privacy exporter registry; toggling these off only filters the bundled defaults.', 'eurocomply-dsar' ); ?></p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Erasure', 'eurocomply-dsar' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="erasure_grace_days"><?php esc_html_e( 'Hard-delete grace period (days)', 'eurocomply-dsar' ); ?></label></th>
					<td><input type="number" id="erasure_grace_days" name="eurocomply_dsar[erasure_grace_days]" value="<?php echo esc_attr( (string) $s['erasure_grace_days'] ); ?>" min="0" max="30" />
					<p class="description"><?php esc_html_e( 'After running erasers, the WordPress user account is hard-deleted after this many days. Set to 0 to delete immediately.', 'eurocomply-dsar' ); ?></p></td>
				</tr>
				<tr>
					<th><label for="retain_completed_days"><?php esc_html_e( 'Retain completed request log (days)', 'eurocomply-dsar' ); ?></label></th>
					<td><input type="number" id="retain_completed_days" name="eurocomply_dsar[retain_completed_days]" value="<?php echo esc_attr( (string) $s['retain_completed_days'] ); ?>" min="7" max="3650" /></td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Contact', 'eurocomply-dsar' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="dpo_contact"><?php esc_html_e( 'DPO / privacy contact', 'eurocomply-dsar' ); ?></label></th>
					<td><textarea id="dpo_contact" name="eurocomply_dsar[dpo_contact]" rows="3" class="large-text"><?php echo esc_textarea( (string) $s['dpo_contact'] ); ?></textarea></td>
				</tr>
				<tr>
					<th><label for="privacy_policy_url"><?php esc_html_e( 'Privacy policy URL', 'eurocomply-dsar' ); ?></label></th>
					<td><input type="url" id="privacy_policy_url" name="eurocomply_dsar[privacy_policy_url]" value="<?php echo esc_attr( (string) $s['privacy_policy_url'] ); ?>" class="regular-text" /></td>
				</tr>
				<tr>
					<th><label for="page_id"><?php esc_html_e( 'DSAR request page (for shortcode)', 'eurocomply-dsar' ); ?></label></th>
					<td>
						<?php
						wp_dropdown_pages( array(
							'name'             => 'eurocomply_dsar[page_id]',
							'selected'         => (int) $s['page_id'],
							'show_option_none' => __( '— Select —', 'eurocomply-dsar' ),
							'option_none_value' => 0,
						) );
						?>
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>
		<?php
	}

	private function render_exporters() : void {
		$exporters = apply_filters( 'wp_privacy_personal_data_exporters', array() );
		$erasers   = apply_filters( 'wp_privacy_personal_data_erasers', array() );

		echo '<h2>' . esc_html__( 'Registered exporters', 'eurocomply-dsar' ) . '</h2>';
		echo '<p>' . esc_html__( 'The plugin automatically includes every exporter registered via WordPress\'s built-in privacy hook — this lets other plugins (Gravity Forms, Contact Form 7, etc.) contribute data without custom glue code.', 'eurocomply-dsar' ) . '</p>';
		$this->render_registry_table( is_array( $exporters ) ? $exporters : array() );

		echo '<h2>' . esc_html__( 'Registered erasers', 'eurocomply-dsar' ) . '</h2>';
		$this->render_registry_table( is_array( $erasers ) ? $erasers : array() );
	}

	/**
	 * @param array<string,mixed> $registry
	 */
	private function render_registry_table( array $registry ) : void {
		if ( empty( $registry ) ) {
			echo '<p>' . esc_html__( 'None registered.', 'eurocomply-dsar' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Slug', 'eurocomply-dsar' ) . '</th><th>' . esc_html__( 'Friendly name', 'eurocomply-dsar' ) . '</th></tr></thead><tbody>';
		foreach ( $registry as $slug => $item ) {
			$name = is_array( $item ) && isset( $item['exporter_friendly_name'] ) ? (string) $item['exporter_friendly_name'] : ( is_array( $item ) && isset( $item['eraser_friendly_name'] ) ? (string) $item['eraser_friendly_name'] : (string) $slug );
			echo '<tr><td><code>' . esc_html( (string) $slug ) . '</code></td><td>' . esc_html( $name ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	private function render_pro() : void {
		$pro = License::is_pro();
		echo '<p>' . esc_html__( 'Pro unlocks enterprise-grade GDPR compliance workflows:', 'eurocomply-dsar' ) . '</p>';
		$items = array(
			__( 'CRM eraser integrations (HubSpot, Mailchimp, Stripe, ActiveCampaign, Klaviyo)', 'eurocomply-dsar' ),
			__( 'SFTP / encrypted email delivery of export ZIPs', 'eurocomply-dsar' ),
			__( 'Signed PDF audit report (DPA-ready)', 'eurocomply-dsar' ),
			__( 'MFA verification (SMS OTP + TOTP)', 'eurocomply-dsar' ),
			__( 'Extension of deadline by up to 2 months (Art. 12(3) second paragraph)', 'eurocomply-dsar' ),
			__( 'Multi-site aggregator across WordPress network', 'eurocomply-dsar' ),
			__( 'Helpdesk import (Zendesk / Freshdesk / Help Scout)', 'eurocomply-dsar' ),
			__( 'REST API for DSAR submission & status polling', 'eurocomply-dsar' ),
			__( '5,000-row CSV export cap (vs 500 in free)', 'eurocomply-dsar' ),
			__( 'WPML / Polylang multilingual email templates', 'eurocomply-dsar' ),
		);
		echo '<ul class="eurocomply-dsar-pro-list">';
		foreach ( $items as $item ) {
			echo '<li>' . esc_html( $item ) . '</li>';
		}
		echo '</ul>';
		echo '<p>' . ( $pro ? esc_html__( 'Pro is active.', 'eurocomply-dsar' ) : esc_html__( 'Activate a license in the License tab to unlock Pro stubs.', 'eurocomply-dsar' ) ) . '</p>';
	}

	private function render_license() : void {
		$license = License::get();
		$is_pro  = License::is_pro();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:520px;">
			<?php wp_nonce_field( self::NONCE_LIC, '_wpnonce' ); ?>
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_LIC ); ?>" />
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="license_key"><?php esc_html_e( 'License key', 'eurocomply-dsar' ); ?></label></th>
					<td>
						<input type="text" id="license_key" name="license_key" value="<?php echo esc_attr( (string) ( $license['key'] ?? '' ) ); ?>" class="regular-text" placeholder="EC-XXXXXX" />
						<p class="description"><?php echo $is_pro ? esc_html__( 'Pro is active.', 'eurocomply-dsar' ) : esc_html__( 'Enter a license key in the form EC-XXXXXX to activate Pro stubs.', 'eurocomply-dsar' ); ?></p>
					</td>
				</tr>
			</table>
			<p class="submit">
				<button type="submit" name="license_op" value="activate" class="button button-primary"><?php esc_html_e( 'Activate', 'eurocomply-dsar' ); ?></button>
				<button type="submit" name="license_op" value="deactivate" class="button"><?php esc_html_e( 'Deactivate', 'eurocomply-dsar' ); ?></button>
			</p>
		</form>
		<?php
	}

	public function handle_save() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-dsar' ), 403 );
		}
		check_admin_referer( self::NONCE_SAVE );

		$input = isset( $_POST['eurocomply_dsar'] ) && is_array( $_POST['eurocomply_dsar'] )
			? wp_unslash( (array) $_POST['eurocomply_dsar'] )
			: array();

		$sanitized = Settings::sanitize( $input );
		update_option( Settings::OPTION_KEY, $sanitized, false );

		add_settings_error( 'eurocomply_dsar', 'saved', __( 'Settings saved.', 'eurocomply-dsar' ), 'updated' );
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

	public function handle_license() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-dsar' ), 403 );
		}
		check_admin_referer( self::NONCE_LIC );

		$op  = isset( $_POST['license_op'] ) ? sanitize_key( (string) $_POST['license_op'] ) : '';
		$key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['license_key'] ) ) : '';

		if ( 'deactivate' === $op ) {
			License::deactivate();
			add_settings_error( 'eurocomply_dsar', 'lic_off', __( 'License deactivated.', 'eurocomply-dsar' ), 'updated' );
		} else {
			$res = License::activate( $key );
			add_settings_error( 'eurocomply_dsar', 'lic', $res['message'], $res['ok'] ? 'updated' : 'error' );
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

	public function handle_request_action() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-dsar' ), 403 );
		}
		check_admin_referer( self::NONCE_REQ );

		$id = isset( $_GET['request_id'] ) ? (int) $_GET['request_id'] : 0;
		$op = isset( $_GET['op'] ) ? sanitize_key( (string) $_GET['op'] ) : '';
		if ( $id <= 0 || '' === $op ) {
			wp_die( esc_html__( 'Invalid request.', 'eurocomply-dsar' ), 400 );
		}

		$message = '';
		$type    = 'updated';

		switch ( $op ) {
			case 'export':
				$res = ExportBuilder::build( $id );
				if ( $res['ok'] ) {
					RequestStore::update(
						$id,
						array(
							'status'          => 'completed',
							'completed_at'    => current_time( 'mysql' ),
							'handler_user_id' => get_current_user_id(),
						)
					);
					$message = sprintf( /* translators: %s: url */ __( 'Export ready: %s', 'eurocomply-dsar' ), $res['url'] );
				} else {
					$type    = 'error';
					$message = $res['message'];
				}
				break;
			case 'erase':
				$res = ErasureManager::run( $id );
				if ( $res['ok'] ) {
					RequestStore::update(
						$id,
						array(
							'status'          => 'in_progress',
							'handler_user_id' => get_current_user_id(),
							'admin_notes'     => implode( "\n", $res['messages'] ),
						)
					);
					$message = sprintf( /* translators: 1: erased count, 2: retained count */ __( 'Erasure run: %1$d items removed, %2$d retained.', 'eurocomply-dsar' ), $res['erased'], $res['retained'] );
				} else {
					$type    = 'error';
					$message = $res['message'];
				}
				break;
			case 'complete':
				RequestStore::update(
					$id,
					array(
						'status'          => 'completed',
						'completed_at'    => current_time( 'mysql' ),
						'handler_user_id' => get_current_user_id(),
					)
				);
				$message = __( 'Request marked as completed.', 'eurocomply-dsar' );
				break;
			case 'reject':
				RequestStore::update(
					$id,
					array(
						'status'          => 'rejected',
						'completed_at'    => current_time( 'mysql' ),
						'handler_user_id' => get_current_user_id(),
					)
				);
				$message = __( 'Request rejected.', 'eurocomply-dsar' );
				break;
			case 'flag_breach':
				$res = Nis2Bridge::flag_request_as_breach( $id );
				if ( ! $res['ok'] ) {
					$type    = 'error';
					$message = __( 'Could not flag request: not found.', 'eurocomply-dsar' );
				} elseif ( ! $res['nis2_active'] ) {
					$type    = 'updated';
					$message = __( 'Request flagged as breach. EuroComply NIS2 (#12) is not active, so no incident was created. Activate it to auto-track Art. 23 24h/72h deadlines.', 'eurocomply-dsar' );
				} elseif ( $res['incident_id'] > 0 ) {
					/* translators: %d: NIS2 incident id. */
					$message = sprintf( __( 'Request flagged as breach. Linked NIS2 incident #%d created with 24h early-warning + 72h notification deadlines.', 'eurocomply-dsar' ), (int) $res['incident_id'] );
				} else {
					$message = __( 'Request flagged as breach.', 'eurocomply-dsar' );
				}
				break;
			case 'unflag_breach':
				Nis2Bridge::unflag_request( $id );
				$message = __( 'Breach flag removed. Any linked NIS2 incident is preserved and must be closed in the NIS2 plugin.', 'eurocomply-dsar' );
				break;
			default:
				wp_die( esc_html__( 'Unknown action.', 'eurocomply-dsar' ), 400 );
		}

		add_settings_error( 'eurocomply_dsar', 'req', $message, $type );
		set_transient( 'settings_errors', get_settings_errors(), 30 );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'             => self::MENU_SLUG,
					'tab'              => 'requests',
					'settings-updated' => 'true',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
