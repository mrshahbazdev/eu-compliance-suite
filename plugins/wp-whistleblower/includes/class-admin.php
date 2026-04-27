<?php
/**
 * Admin UI — 7-tab Dashboard / Reports / Recipients / Channels / Settings / Pro / License.
 *
 * @package EuroComply\Whistleblower
 */

declare( strict_types = 1 );

namespace EuroComply\Whistleblower;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin {

	public const MENU_SLUG     = 'eurocomply-wb';
	public const NONCE_SAVE    = 'eurocomply_wb_save';
	public const NONCE_LIC     = 'eurocomply_wb_license';
	public const NONCE_REPORT  = 'eurocomply_wb_report';
	public const NONCE_POLICY  = 'eurocomply_wb_policy';
	public const ACTION_SAVE   = 'eurocomply_wb_save';
	public const ACTION_LIC    = 'eurocomply_wb_license';
	public const ACTION_REPORT = 'eurocomply_wb_report';
	public const ACTION_POLICY = 'eurocomply_wb_policy';

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
		add_action( 'admin_post_' . self::ACTION_SAVE,   array( $this, 'handle_save' ) );
		add_action( 'admin_post_' . self::ACTION_LIC,    array( $this, 'handle_license' ) );
		add_action( 'admin_post_' . self::ACTION_REPORT, array( $this, 'handle_report' ) );
		add_action( 'admin_post_' . self::ACTION_POLICY, array( $this, 'handle_policy' ) );
	}

	public function register_menu() : void {
		$cap = Recipient::can_view() ? Recipient::CAP_VIEW : 'manage_options';
		add_menu_page(
			__( 'Whistleblower', 'eurocomply-whistleblower' ),
			__( 'Whistleblower', 'eurocomply-whistleblower' ),
			$cap,
			self::MENU_SLUG,
			array( $this, 'render' ),
			'dashicons-megaphone',
			77
		);
	}

	public function assets( string $hook ) : void {
		if ( false === strpos( $hook, self::MENU_SLUG ) ) {
			return;
		}
		wp_enqueue_style( self::MENU_SLUG . '-admin', EUROCOMPLY_WB_URL . 'assets/css/admin.css', array(), EUROCOMPLY_WB_VERSION );
	}

	public function render() : void {
		if ( ! Recipient::can_view() && ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( (string) $_GET['tab'] ) : 'dashboard'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tabs = array(
			'dashboard'  => __( 'Dashboard',  'eurocomply-whistleblower' ),
			'reports'    => __( 'Reports',    'eurocomply-whistleblower' ),
			'recipients' => __( 'Recipients', 'eurocomply-whistleblower' ),
			'channels'   => __( 'Channels',   'eurocomply-whistleblower' ),
			'access_log' => __( 'Access log', 'eurocomply-whistleblower' ),
			'settings'   => __( 'Settings',   'eurocomply-whistleblower' ),
			'pro'        => __( 'Pro',        'eurocomply-whistleblower' ),
			'license'    => __( 'License',    'eurocomply-whistleblower' ),
		);
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'dashboard';
		}

		echo '<div class="wrap eurocomply-wb-wrap">';
		echo '<h1>' . esc_html__( 'EuroComply Whistleblower', 'eurocomply-whistleblower' ) . '</h1>';

		if ( isset( $_GET['settings-updated'] ) && 'true' === (string) $_GET['settings-updated'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			settings_errors( 'eurocomply_wb' );
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
			case 'reports':    $this->render_reports();    break;
			case 'recipients': $this->render_recipients(); break;
			case 'channels':   $this->render_channels();   break;
			case 'access_log': $this->render_access_log(); break;
			case 'settings':   $this->render_settings();   break;
			case 'pro':        $this->render_pro();        break;
			case 'license':    $this->render_license();    break;
			default:           $this->render_dashboard();
		}
		echo '</div>';
	}

	private function render_dashboard() : void {
		$s        = Settings::get();
		$total    = ReportStore::count_total();
		$open     = ReportStore::count_open();
		$ack_due  = ReportStore::overdue_ack( (int) $s['ack_deadline_days'] );
		$fb_due   = ReportStore::overdue_feedback( (int) $s['feedback_deadline_days'] );
		$log      = AccessLog::count_total();
		$rec      = count( Recipient::get_recipients() );

		echo '<div class="eurocomply-wb-cards">';
		$this->card( __( 'Total reports', 'eurocomply-whistleblower' ), (string) $total );
		$this->card( __( 'Open',          'eurocomply-whistleblower' ), (string) $open );
		$this->card( __( 'Overdue ack (7d)',     'eurocomply-whistleblower' ), (string) $ack_due, $ack_due > 0 ? 'crit' : 'ok' );
		$this->card( __( 'Overdue feedback (3m)','eurocomply-whistleblower' ), (string) $fb_due,  $fb_due  > 0 ? 'crit' : 'ok' );
		$this->card( __( 'Designated recipients','eurocomply-whistleblower' ), (string) $rec, $rec > 0 ? 'ok' : 'warn' );
		$this->card( __( 'Access-log entries',   'eurocomply-whistleblower' ), (string) $log );
		echo '</div>';

		if ( 0 === $rec ) {
			echo '<div class="notice notice-warning"><p>';
			echo esc_html__( 'No designated recipients are configured. All reports currently route to the compliance email only — Art. 9(1)(b) requires impartial designated recipients.', 'eurocomply-whistleblower' );
			echo '</p></div>';
		}

		echo '<h2>' . esc_html__( 'Public shortcodes', 'eurocomply-whistleblower' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Shortcode', 'eurocomply-whistleblower' ) . '</th><th>' . esc_html__( 'Purpose', 'eurocomply-whistleblower' ) . '</th></tr></thead><tbody>';
		foreach ( array(
			'[eurocomply_whistleblower_form]'   => __( 'Public submission form (anonymous + identified)', 'eurocomply-whistleblower' ),
			'[eurocomply_whistleblower_status]' => __( 'Status check via follow-up token',                'eurocomply-whistleblower' ),
			'[eurocomply_whistleblower_policy]' => __( 'Auto-generated whistleblower policy',             'eurocomply-whistleblower' ),
		) as $sc => $desc ) {
			printf( '<tr><td><code>%s</code></td><td>%s</td></tr>', esc_html( $sc ), esc_html( $desc ) );
		}
		echo '</tbody></table>';
	}

	private function render_reports() : void {
		$report_id = isset( $_GET['report_id'] ) ? (int) $_GET['report_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $report_id > 0 ) {
			$this->render_report_detail( $report_id );
			return;
		}
		echo '<p><a class="button" href="' . esc_url( CsvExport::url( 'reports' ) ) . '">' . esc_html__( 'Export CSV', 'eurocomply-whistleblower' ) . '</a></p>';
		$rows     = ReportStore::recent( 200 );
		$statuses = ReportStore::statuses();
		echo '<table class="widefat striped"><thead><tr>';
		foreach ( array( '#', __( 'Submitted', 'eurocomply-whistleblower' ), __( 'Category', 'eurocomply-whistleblower' ), __( 'Subject', 'eurocomply-whistleblower' ), __( 'Status', 'eurocomply-whistleblower' ), __( 'Anon', 'eurocomply-whistleblower' ), '' ) as $h ) {
			echo '<th>' . esc_html( $h ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		if ( empty( $rows ) ) {
			echo '<tr><td colspan="7">' . esc_html__( 'No reports yet.', 'eurocomply-whistleblower' ) . '</td></tr>';
		}
		foreach ( $rows as $r ) {
			$url = add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'reports', 'report_id' => (int) $r['id'] ), admin_url( 'admin.php' ) );
			echo '<tr>';
			printf( '<td>#%d</td>', (int) $r['id'] );
			printf( '<td>%s</td>', esc_html( (string) $r['created_at'] ) );
			printf( '<td><code>%s</code></td>', esc_html( (string) $r['category'] ) );
			printf( '<td>%s</td>', esc_html( mb_substr( (string) $r['subject'], 0, 80 ) ) );
			printf( '<td>%s</td>', esc_html( $statuses[ (string) $r['status'] ] ?? (string) $r['status'] ) );
			printf( '<td>%s</td>', (int) $r['anonymous'] ? esc_html__( 'Yes', 'eurocomply-whistleblower' ) : '—' );
			printf( '<td><a class="button" href="%s">%s</a></td>', esc_url( $url ), esc_html__( 'Open', 'eurocomply-whistleblower' ) );
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private function render_report_detail( int $id ) : void {
		$report = ReportStore::get( $id );
		if ( ! $report ) {
			echo '<p>' . esc_html__( 'Report not found.', 'eurocomply-whistleblower' ) . '</p>';
			return;
		}
		AccessLog::record( $id, 'viewed', array() );

		$statuses = ReportStore::statuses();
		$files    = array();
		if ( ! empty( $report['files_json'] ) ) {
			$decoded = json_decode( (string) $report['files_json'], true );
			if ( is_array( $decoded ) ) {
				$files = $decoded;
			}
		}
		echo '<h2>' . esc_html( sprintf( /* translators: %d id */ __( 'Report #%d', 'eurocomply-whistleblower' ), $id ) ) . '</h2>';
		echo '<table class="form-table"><tbody>';
		printf( '<tr><th>%s</th><td>%s</td></tr>', esc_html__( 'Submitted', 'eurocomply-whistleblower' ), esc_html( (string) $report['created_at'] ) );
		printf( '<tr><th>%s</th><td><code>%s</code></td></tr>', esc_html__( 'Category', 'eurocomply-whistleblower' ), esc_html( (string) $report['category'] ) );
		printf( '<tr><th>%s</th><td>%s</td></tr>', esc_html__( 'Anonymous', 'eurocomply-whistleblower' ), (int) $report['anonymous'] ? esc_html__( 'Yes', 'eurocomply-whistleblower' ) : esc_html__( 'No', 'eurocomply-whistleblower' ) );
		if ( ! (int) $report['anonymous'] ) {
			printf( '<tr><th>%s</th><td><code>%s</code></td></tr>', esc_html__( 'Contact', 'eurocomply-whistleblower' ), esc_html( (string) $report['contact_value'] ) );
		}
		printf( '<tr><th>%s</th><td><strong>%s</strong></td></tr>', esc_html__( 'Subject', 'eurocomply-whistleblower' ), esc_html( (string) $report['subject'] ) );
		printf( '<tr><th>%s</th><td><div class="eurocomply-wb-body">%s</div></td></tr>', esc_html__( 'Body', 'eurocomply-whistleblower' ), wp_kses_post( (string) $report['body'] ) );
		echo '</tbody></table>';

		if ( ! empty( $files ) ) {
			echo '<h3>' . esc_html__( 'Attachments', 'eurocomply-whistleblower' ) . '</h3><ul>';
			foreach ( $files as $f ) {
				$name = (string) ( $f['name'] ?? '' );
				$url  = (string) ( $f['url'] ?? '' );
				if ( '' !== $url ) {
					printf( '<li><a href="%s">%s</a></li>', esc_url( $url ), esc_html( $name ) );
				}
			}
			echo '</ul>';
		}

		// Status / lifecycle controls.
		?>
		<h3><?php esc_html_e( 'Update status', 'eurocomply-whistleblower' ); ?></h3>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( self::NONCE_REPORT, '_wpnonce' ); ?>
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_REPORT ); ?>" />
			<input type="hidden" name="report_id" value="<?php echo esc_attr( (string) $id ); ?>" />
			<table class="form-table"><tbody>
				<tr><th><label for="status"><?php esc_html_e( 'Status', 'eurocomply-whistleblower' ); ?></label></th>
				<td><select id="status" name="status">
					<?php foreach ( $statuses as $k => $label ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>" <?php selected( (string) $report['status'], $k ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select></td></tr>
				<tr><th><?php esc_html_e( 'Mark stage sent', 'eurocomply-whistleblower' ); ?></th><td>
					<label><input type="checkbox" name="mark_acknowledged" value="1" <?php disabled( ! empty( $report['acknowledged_at'] ) ); ?> /> <?php esc_html_e( 'Acknowledgement sent (Art. 9(1)(b))', 'eurocomply-whistleblower' ); ?></label><br />
					<label><input type="checkbox" name="mark_feedback" value="1" <?php disabled( ! empty( $report['feedback_sent_at'] ) ); ?> /> <?php esc_html_e( 'Feedback sent (Art. 9(1)(f))', 'eurocomply-whistleblower' ); ?></label>
				</td></tr>
				<tr><th><label for="internal_notes"><?php esc_html_e( 'Internal notes', 'eurocomply-whistleblower' ); ?></label></th>
				<td><textarea id="internal_notes" name="internal_notes" rows="6" class="large-text"><?php echo esc_textarea( (string) ( $report['internal_notes'] ?? '' ) ); ?></textarea></td></tr>
			</tbody></table>
			<?php submit_button( __( 'Update report', 'eurocomply-whistleblower' ) ); ?>
		</form>

		<h3><?php esc_html_e( 'Access trail (this report)', 'eurocomply-whistleblower' ); ?></h3>
		<?php
		$trail = AccessLog::recent( 100, $id );
		echo '<table class="widefat striped"><thead><tr>';
		foreach ( array( __( 'When', 'eurocomply-whistleblower' ), __( 'User', 'eurocomply-whistleblower' ), __( 'Action', 'eurocomply-whistleblower' ) ) as $h ) {
			echo '<th>' . esc_html( $h ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $trail as $t ) {
			echo '<tr>';
			printf( '<td>%s</td>', esc_html( (string) $t['occurred_at'] ) );
			printf( '<td><code>%s</code></td>', esc_html( (string) $t['user_login'] ) );
			printf( '<td>%s</td>', esc_html( (string) $t['action'] ) );
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private function render_recipients() : void {
		$users      = get_users( array( 'fields' => array( 'ID', 'display_name', 'user_login', 'user_email' ), 'number' => 200 ) );
		$selected   = (array) Settings::get()['recipient_user_ids'];
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( self::NONCE_SAVE, '_wpnonce' ); ?>
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_SAVE ); ?>" />
			<input type="hidden" name="eurocomply_wb_target" value="recipients" />
			<p><?php esc_html_e( 'Select the user(s) authorised to view and process whistleblower reports. Designated recipients receive the new-report email and gain the eurocomply_wb_view + eurocomply_wb_manage capabilities.', 'eurocomply-whistleblower' ); ?></p>
			<table class="widefat striped"><thead><tr><th></th><th><?php esc_html_e( 'User', 'eurocomply-whistleblower' ); ?></th><th><?php esc_html_e( 'Email', 'eurocomply-whistleblower' ); ?></th></tr></thead><tbody>
				<?php foreach ( $users as $u ) : ?>
				<tr>
					<td><input type="checkbox" name="eurocomply_wb_settings[recipient_user_ids][]" value="<?php echo esc_attr( (string) (int) $u->ID ); ?>" <?php checked( in_array( (int) $u->ID, array_map( 'intval', $selected ), true ) ); ?> /></td>
					<td><strong><?php echo esc_html( (string) $u->display_name ); ?></strong> &middot; <code><?php echo esc_html( (string) $u->user_login ); ?></code></td>
					<td><?php echo esc_html( (string) $u->user_email ); ?></td>
				</tr>
				<?php endforeach; ?>
			</tbody></table>
			<?php submit_button( __( 'Save recipients', 'eurocomply-whistleblower' ) ); ?>
		</form>
		<?php
	}

	private function render_channels() : void {
		$auth = Settings::authorities();
		$s    = Settings::get();
		echo '<p>' . esc_html__( 'EU member-state external authorities competent to receive whistleblower reports under Art. 11. The country listed in Settings is highlighted; its authority is shown by the auto-generated policy.', 'eurocomply-whistleblower' ) . '</p>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Country', 'eurocomply-whistleblower' ) . '</th><th>' . esc_html__( 'Authority', 'eurocomply-whistleblower' ) . '</th><th>' . esc_html__( 'Email', 'eurocomply-whistleblower' ) . '</th><th>' . esc_html__( 'Website', 'eurocomply-whistleblower' ) . '</th></tr></thead><tbody>';
		foreach ( $auth as $cc => $row ) {
			$cls = $cc === strtoupper( (string) $s['organisation_country'] ) ? ' class="eurocomply-wb-row-active"' : '';
			echo '<tr' . $cls . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — $cls is hard-coded.
			printf( '<td><strong>%s</strong></td>', esc_html( $cc ) );
			printf( '<td>%s</td>', esc_html( (string) $row['authority'] ) );
			printf( '<td><a href="mailto:%s">%s</a></td>', esc_attr( (string) $row['email'] ), esc_html( (string) $row['email'] ) );
			printf( '<td><a href="%s" rel="noopener nofollow" target="_blank">%s</a></td>', esc_url( (string) $row['website'] ), esc_html( (string) $row['website'] ) );
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private function render_access_log() : void {
		echo '<p><a class="button" href="' . esc_url( CsvExport::url( 'access_log' ) ) . '">' . esc_html__( 'Export CSV', 'eurocomply-whistleblower' ) . '</a></p>';
		$rows = AccessLog::recent( 500 );
		echo '<table class="widefat striped"><thead><tr>';
		foreach ( array( '#', __( 'When', 'eurocomply-whistleblower' ), __( 'Report', 'eurocomply-whistleblower' ), __( 'User', 'eurocomply-whistleblower' ), __( 'Action', 'eurocomply-whistleblower' ) ) as $h ) {
			echo '<th>' . esc_html( $h ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $rows as $r ) {
			echo '<tr>';
			printf( '<td>#%d</td>', (int) $r['id'] );
			printf( '<td>%s</td>', esc_html( (string) $r['occurred_at'] ) );
			printf( '<td>#%d</td>', (int) $r['report_id'] );
			printf( '<td><code>%s</code></td>', esc_html( (string) $r['user_login'] ) );
			printf( '<td>%s</td>', esc_html( (string) $r['action'] ) );
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
			<input type="hidden" name="eurocomply_wb_target" value="settings" />
			<table class="form-table" role="presentation">
				<tr><th><?php esc_html_e( 'Reporting form', 'eurocomply-whistleblower' ); ?></th>
				<td>
					<label><input type="checkbox" name="eurocomply_wb_settings[enable_anonymous]" value="1" <?php checked( ! empty( $s['enable_anonymous'] ) ); ?> /> <?php esc_html_e( 'Allow anonymous submissions (Art. 6(2))', 'eurocomply-whistleblower' ); ?></label><br />
					<label><input type="checkbox" name="eurocomply_wb_settings[enable_status_check]" value="1" <?php checked( ! empty( $s['enable_status_check'] ) ); ?> /> <?php esc_html_e( 'Show follow-up status shortcode', 'eurocomply-whistleblower' ); ?></label><br />
					<label><input type="checkbox" name="eurocomply_wb_settings[enable_external_referral]" value="1" <?php checked( ! empty( $s['enable_external_referral'] ) ); ?> /> <?php esc_html_e( 'Surface external authority on policy page', 'eurocomply-whistleblower' ); ?></label>
				</td></tr>

				<tr><th><label><?php esc_html_e( 'Form title', 'eurocomply-whistleblower' ); ?></label></th>
				<td><input type="text" name="eurocomply_wb_settings[form_title]" class="regular-text" value="<?php echo esc_attr( (string) $s['form_title'] ); ?>" /></td></tr>

				<tr><th><label><?php esc_html_e( 'Form intro', 'eurocomply-whistleblower' ); ?></label></th>
				<td><textarea name="eurocomply_wb_settings[form_description]" rows="4" class="large-text"><?php echo esc_textarea( (string) $s['form_description'] ); ?></textarea></td></tr>

				<tr><th><label><?php esc_html_e( 'Acknowledgement deadline (days)', 'eurocomply-whistleblower' ); ?></label></th>
				<td><input type="number" min="1" max="7" name="eurocomply_wb_settings[ack_deadline_days]" value="<?php echo esc_attr( (string) (int) $s['ack_deadline_days'] ); ?>" class="small-text" /> <span class="description"><?php esc_html_e( 'Art. 9(1)(b) caps this at 7 days.', 'eurocomply-whistleblower' ); ?></span></td></tr>

				<tr><th><label><?php esc_html_e( 'Feedback deadline (days)', 'eurocomply-whistleblower' ); ?></label></th>
				<td><input type="number" min="30" max="180" name="eurocomply_wb_settings[feedback_deadline_days]" value="<?php echo esc_attr( (string) (int) $s['feedback_deadline_days'] ); ?>" class="small-text" /> <span class="description"><?php esc_html_e( 'Art. 9(1)(f) caps this at 3 months (90 days).', 'eurocomply-whistleblower' ); ?></span></td></tr>

				<tr><th><label><?php esc_html_e( 'Max file size (MB)', 'eurocomply-whistleblower' ); ?></label></th>
				<td><input type="number" min="1" max="200" name="eurocomply_wb_settings[max_file_size_mb]" value="<?php echo esc_attr( (string) (int) $s['max_file_size_mb'] ); ?>" class="small-text" /></td></tr>

				<tr><th><label><?php esc_html_e( 'Allowed file extensions', 'eurocomply-whistleblower' ); ?></label></th>
				<td><input type="text" name="eurocomply_wb_settings[allowed_file_types]" class="regular-text" value="<?php echo esc_attr( (string) $s['allowed_file_types'] ); ?>" /></td></tr>

				<tr><th><label><?php esc_html_e( 'Rate limit (per IP / hour)', 'eurocomply-whistleblower' ); ?></label></th>
				<td><input type="number" min="1" max="100" name="eurocomply_wb_settings[rate_limit_per_hour]" value="<?php echo esc_attr( (string) (int) $s['rate_limit_per_hour'] ); ?>" class="small-text" /></td></tr>

				<tr><th><label><?php esc_html_e( 'Organisation name', 'eurocomply-whistleblower' ); ?></label></th>
				<td><input type="text" name="eurocomply_wb_settings[organisation_name]" class="regular-text" value="<?php echo esc_attr( (string) $s['organisation_name'] ); ?>" /></td></tr>

				<tr><th><label><?php esc_html_e( 'Country (ISO alpha-2)', 'eurocomply-whistleblower' ); ?></label></th>
				<td><input type="text" name="eurocomply_wb_settings[organisation_country]" class="small-text" maxlength="2" value="<?php echo esc_attr( (string) $s['organisation_country'] ); ?>" /></td></tr>

				<tr><th><label><?php esc_html_e( 'Compliance email (fallback if no recipients set)', 'eurocomply-whistleblower' ); ?></label></th>
				<td><input type="email" name="eurocomply_wb_settings[compliance_email]" class="regular-text" value="<?php echo esc_attr( (string) $s['compliance_email'] ); ?>" /></td></tr>
			</table>
			<?php submit_button(); ?>
		</form>

		<hr />

		<h2><?php esc_html_e( 'Whistleblower policy page', 'eurocomply-whistleblower' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( self::NONCE_POLICY, '_wpnonce' ); ?>
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_POLICY ); ?>" />
			<?php
			$pid = (int) $s['public_policy_page_id'];
			if ( $pid > 0 && get_post( $pid ) ) {
				printf(
					'<p>%s <a href="%s">%s</a> &middot; <a href="%s">%s</a></p>',
					esc_html__( 'Policy page:', 'eurocomply-whistleblower' ),
					esc_url( get_edit_post_link( $pid ) ?: '#' ),
					esc_html__( 'Edit', 'eurocomply-whistleblower' ),
					esc_url( get_permalink( $pid ) ?: '#' ),
					esc_html__( 'View', 'eurocomply-whistleblower' )
				);
			}
			?>
			<?php submit_button( __( 'Create / refresh policy page', 'eurocomply-whistleblower' ), 'secondary' ); ?>
		</form>

		<h3><?php esc_html_e( 'Live preview', 'eurocomply-whistleblower' ); ?></h3>
		<div class="eurocomply-wb-preview"><?php echo PolicyPageGenerator::render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — already-escaped HTML. ?></div>
		<?php
	}

	private function render_pro() : void {
		echo '<p>' . esc_html__( 'Pro stubs (not implemented in free):', 'eurocomply-whistleblower' ) . '</p>';
		echo '<ul class="eurocomply-wb-pro-list">';
		foreach ( array(
			__( 'PGP-encrypted-at-rest report bodies & attachments', 'eurocomply-whistleblower' ),
			__( 'Off-site storage (S3 / SFTP) for attachments',       'eurocomply-whistleblower' ),
			__( 'Scheduled retention purge (Art. 18(2))',             'eurocomply-whistleblower' ),
			__( '2FA enforcement for designated recipients',          'eurocomply-whistleblower' ),
			__( 'Slack / Teams alert on new report',                  'eurocomply-whistleblower' ),
			__( 'External authority pre-filled webhook submission',   'eurocomply-whistleblower' ),
			__( 'Voice / phone reporting integration (Art. 9(2))',    'eurocomply-whistleblower' ),
			__( 'Signed PDF case bundle (chain-of-custody)',          'eurocomply-whistleblower' ),
			__( 'REST API: GET /wp-json/eurocomply/v1/whistleblower', 'eurocomply-whistleblower' ),
			__( '5,000-row CSV cap (vs 500 free)',                    'eurocomply-whistleblower' ),
			__( 'WPML / Polylang multi-language form',                'eurocomply-whistleblower' ),
			__( 'Multi-tenant for parent-company groups (Art. 8(6))', 'eurocomply-whistleblower' ),
		) as $i ) {
			echo '<li>' . esc_html( $i ) . '</li>';
		}
		echo '</ul>';
		echo '<p>' . ( License::is_pro() ? esc_html__( 'Pro is active.', 'eurocomply-whistleblower' ) : esc_html__( 'Activate a license in the License tab to unlock Pro stubs.', 'eurocomply-whistleblower' ) ) . '</p>';
	}

	private function render_license() : void {
		$license = License::get();
		$is_pro  = License::is_pro();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:520px;">
			<?php wp_nonce_field( self::NONCE_LIC, '_wpnonce' ); ?>
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_LIC ); ?>" />
			<table class="form-table" role="presentation">
				<tr><th><label for="license_key"><?php esc_html_e( 'License key', 'eurocomply-whistleblower' ); ?></label></th>
				<td><input type="text" id="license_key" name="license_key" value="<?php echo esc_attr( (string) ( $license['key'] ?? '' ) ); ?>" class="regular-text" placeholder="EC-XXXXXX" />
				<p class="description"><?php echo $is_pro ? esc_html__( 'Pro is active.', 'eurocomply-whistleblower' ) : esc_html__( 'Enter a key in the form EC-XXXXXX to unlock Pro stubs.', 'eurocomply-whistleblower' ); ?></p></td></tr>
			</table>
			<p class="submit">
				<button type="submit" name="license_op" value="activate" class="button button-primary"><?php esc_html_e( 'Activate', 'eurocomply-whistleblower' ); ?></button>
				<button type="submit" name="license_op" value="deactivate" class="button"><?php esc_html_e( 'Deactivate', 'eurocomply-whistleblower' ); ?></button>
			</p>
		</form>
		<?php
	}

	private function card( string $label, string $value, string $tone = '' ) : void {
		$cls = 'eurocomply-wb-card' . ( '' !== $tone ? ' eurocomply-wb-card--' . $tone : '' );
		printf(
			'<div class="%1$s"><div class="eurocomply-wb-card__value">%2$s</div><div class="eurocomply-wb-card__label">%3$s</div></div>',
			esc_attr( $cls ),
			esc_html( $value ),
			esc_html( $label )
		);
	}

	public function handle_save() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-whistleblower' ), 403 );
		}
		check_admin_referer( self::NONCE_SAVE );
		$target = isset( $_POST['eurocomply_wb_target'] ) ? sanitize_key( (string) $_POST['eurocomply_wb_target'] ) : 'settings';
		$input  = isset( $_POST['eurocomply_wb_settings'] ) && is_array( $_POST['eurocomply_wb_settings'] )
			? wp_unslash( (array) $_POST['eurocomply_wb_settings'] )
			: array();

		$existing = Settings::get();
		// Merge so a recipients-only form doesn't wipe other settings, and vice versa.
		$merged = array_merge( $existing, $input );
		// Preserve existing recipients on settings tab (it doesn't render the recipients matrix).
		if ( 'recipients' !== $target && ! isset( $input['recipient_user_ids'] ) ) {
			$merged['recipient_user_ids'] = (array) $existing['recipient_user_ids'];
		}
		update_option( Settings::OPTION_KEY, Settings::sanitize( $merged ), false );
		Recipient::ensure_role();

		add_settings_error( 'eurocomply_wb', 'saved', __( 'Saved.', 'eurocomply-whistleblower' ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );

		$tab = 'recipients' === $target ? 'recipients' : 'settings';
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => $tab, 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_license() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-whistleblower' ), 403 );
		}
		check_admin_referer( self::NONCE_LIC );

		$op  = isset( $_POST['license_op'] ) ? sanitize_key( (string) $_POST['license_op'] ) : '';
		$key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['license_key'] ) ) : '';

		if ( 'deactivate' === $op ) {
			License::deactivate();
			add_settings_error( 'eurocomply_wb', 'lic_off', __( 'License deactivated.', 'eurocomply-whistleblower' ), 'updated' );
		} else {
			$res = License::activate( $key );
			add_settings_error( 'eurocomply_wb', 'lic', $res['message'], $res['ok'] ? 'updated' : 'error' );
		}
		set_transient( 'settings_errors', get_settings_errors(), 30 );

		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'license', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_report() : void {
		if ( ! Recipient::can_manage() ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-whistleblower' ), 403 );
		}
		check_admin_referer( self::NONCE_REPORT );
		$id = isset( $_POST['report_id'] ) ? (int) $_POST['report_id'] : 0;
		if ( $id <= 0 ) {
			wp_die( esc_html__( 'Bad request.', 'eurocomply-whistleblower' ), 400 );
		}
		$status   = isset( $_POST['status'] ) ? sanitize_key( (string) $_POST['status'] ) : '';
		$statuses = ReportStore::statuses();
		$update   = array();
		if ( isset( $statuses[ $status ] ) ) {
			$update['status'] = $status;
			if ( 'closed' === $status ) {
				$update['closed_at'] = current_time( 'mysql' );
			}
		}
		if ( ! empty( $_POST['mark_acknowledged'] ) ) {
			$update['acknowledged_at'] = current_time( 'mysql' );
		}
		if ( ! empty( $_POST['mark_feedback'] ) ) {
			$update['feedback_sent_at'] = current_time( 'mysql' );
		}
		if ( isset( $_POST['internal_notes'] ) ) {
			$update['internal_notes'] = wp_kses_post( wp_unslash( (string) $_POST['internal_notes'] ) );
		}
		if ( ! empty( $update ) ) {
			ReportStore::update( $id, $update );
			AccessLog::record( $id, 'updated', array_keys( $update ) );
		}
		add_settings_error( 'eurocomply_wb', 'rep', __( 'Report updated.', 'eurocomply-whistleblower' ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'reports', 'report_id' => $id, 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_policy() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-whistleblower' ), 403 );
		}
		check_admin_referer( self::NONCE_POLICY );
		$id = PolicyPageGenerator::ensure_page();
		add_settings_error( 'eurocomply_wb', 'policy', $id > 0 ? __( 'Policy page ready.', 'eurocomply-whistleblower' ) : __( 'Could not create policy page.', 'eurocomply-whistleblower' ), $id > 0 ? 'updated' : 'error' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'settings', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}
}
