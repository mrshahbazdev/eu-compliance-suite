<?php
/**
 * Admin UI.
 *
 * @package EuroComply\NIS2
 */

declare( strict_types = 1 );

namespace EuroComply\NIS2;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin {

	public const MENU_SLUG   = 'eurocomply-nis2';
	public const NONCE_SAVE  = 'eurocomply_nis2_save';
	public const NONCE_LIC   = 'eurocomply_nis2_license';
	public const NONCE_INC   = 'eurocomply_nis2_incident';
	public const ACTION_SAVE = 'eurocomply_nis2_save';
	public const ACTION_LIC  = 'eurocomply_nis2_license';
	public const ACTION_INC  = 'eurocomply_nis2_incident';

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
		add_action( 'admin_post_' . self::ACTION_INC, array( $this, 'handle_incident' ) );
	}

	public function register_menu() : void {
		add_menu_page(
			__( 'EuroComply NIS2', 'eurocomply-nis2' ),
			__( 'EuroComply NIS2', 'eurocomply-nis2' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render' ),
			'dashicons-shield',
			81
		);
	}

	public function assets( string $hook ) : void {
		if ( false === strpos( $hook, self::MENU_SLUG ) ) {
			return;
		}
		wp_enqueue_style( self::MENU_SLUG . '-admin', EUROCOMPLY_NIS2_URL . 'assets/css/admin.css', array(), EUROCOMPLY_NIS2_VERSION );
	}

	public function render() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tab  = isset( $_GET['tab'] ) ? sanitize_key( (string) $_GET['tab'] ) : 'dashboard'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tabs = array(
			'dashboard' => __( 'Dashboard', 'eurocomply-nis2' ),
			'events'    => __( 'Security events', 'eurocomply-nis2' ),
			'incidents' => __( 'Incidents', 'eurocomply-nis2' ),
			'csirts'    => __( 'CSIRTs', 'eurocomply-nis2' ),
			'settings'  => __( 'Settings', 'eurocomply-nis2' ),
			'pro'       => __( 'Pro', 'eurocomply-nis2' ),
			'license'   => __( 'License', 'eurocomply-nis2' ),
		);
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'dashboard';
		}

		echo '<div class="wrap eurocomply-nis2-wrap">';
		echo '<h1>' . esc_html__( 'EuroComply NIS2 & CRA', 'eurocomply-nis2' ) . '</h1>';

		if ( isset( $_GET['settings-updated'] ) && 'true' === (string) $_GET['settings-updated'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			settings_errors( 'eurocomply_nis2' );
		}

		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $tabs as $slug => $label ) {
			$url = add_query_arg(
				array( 'page' => self::MENU_SLUG, 'tab' => $slug ),
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
			case 'events':
				$this->render_events();
				break;
			case 'incidents':
				if ( isset( $_GET['edit'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					$this->render_incident_editor( (int) $_GET['edit'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				} else {
					$this->render_incidents();
				}
				break;
			case 'csirts':
				$this->render_csirts();
				break;
			case 'settings':
				$this->render_settings();
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
		$event_counts    = EventStore::severity_counts( 24 );
		$incident_counts = IncidentStore::status_counts();
		$overdue         = IncidentStore::count_overdue();

		echo '<div class="eurocomply-nis2-cards">';
		$cards = array(
			array( __( 'Critical events (24h)', 'eurocomply-nis2' ), $event_counts['critical'] ?? 0 ),
			array( __( 'High events (24h)', 'eurocomply-nis2' ), $event_counts['high'] ?? 0 ),
			array( __( 'Open incidents', 'eurocomply-nis2' ), array_sum( $incident_counts ) - ( $incident_counts['closed'] ?? 0 ) ),
			array( __( 'Overdue NIS2 deadlines', 'eurocomply-nis2' ), $overdue ),
		);
		foreach ( $cards as $card ) {
			printf(
				'<div class="eurocomply-nis2-card"><div class="eurocomply-nis2-card__value">%1$s</div><div class="eurocomply-nis2-card__label">%2$s</div></div>',
				esc_html( (string) $card[1] ),
				esc_html( (string) $card[0] )
			);
		}
		echo '</div>';

		echo '<h2>' . esc_html__( 'Latest incidents', 'eurocomply-nis2' ) . '</h2>';
		$this->render_incidents_table( IncidentStore::recent( 10 ) );
		echo '<h2>' . esc_html__( 'Latest security events', 'eurocomply-nis2' ) . '</h2>';
		$this->render_events_table( EventStore::recent( 15 ) );
	}

	private function render_events() : void {
		$cat = isset( $_GET['category'] ) ? sanitize_key( (string) $_GET['category'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$sev = isset( $_GET['severity'] ) ? sanitize_key( (string) $_GET['severity'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$rows = EventStore::recent( 300, $cat, $sev );

		echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '" style="margin:12px 0;">';
		echo '<input type="hidden" name="page" value="' . esc_attr( self::MENU_SLUG ) . '" />';
		echo '<input type="hidden" name="tab" value="events" />';

		echo '<label>' . esc_html__( 'Category', 'eurocomply-nis2' ) . ' <select name="category"><option value="">' . esc_html__( 'All', 'eurocomply-nis2' ) . '</option>';
		foreach ( EventStore::CATEGORIES as $c ) {
			printf( '<option value="%1$s"%3$s>%2$s</option>', esc_attr( $c ), esc_html( $c ), selected( $cat, $c, false ) );
		}
		echo '</select></label> ';

		echo '<label>' . esc_html__( 'Severity', 'eurocomply-nis2' ) . ' <select name="severity"><option value="">' . esc_html__( 'All', 'eurocomply-nis2' ) . '</option>';
		foreach ( EventStore::SEVERITIES as $s ) {
			printf( '<option value="%1$s"%3$s>%2$s</option>', esc_attr( $s ), esc_html( $s ), selected( $sev, $s, false ) );
		}
		echo '</select></label> ';

		submit_button( __( 'Filter', 'eurocomply-nis2' ), 'secondary', '', false );
		echo ' <a class="button" href="' . esc_url( CsvExport::url( 'events' ) ) . '">' . esc_html__( 'Export CSV', 'eurocomply-nis2' ) . '</a>';
		echo '</form>';

		$this->render_events_table( $rows );
	}

	/**
	 * @param array<int,array<string,mixed>> $rows
	 */
	private function render_events_table( array $rows ) : void {
		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No events recorded yet.', 'eurocomply-nis2' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr>';
		foreach ( array( 'ID', __( 'Time', 'eurocomply-nis2' ), __( 'Category', 'eurocomply-nis2' ), __( 'Severity', 'eurocomply-nis2' ), __( 'Action', 'eurocomply-nis2' ), __( 'Actor', 'eurocomply-nis2' ), __( 'Target', 'eurocomply-nis2' ) ) as $label ) {
			echo '<th>' . esc_html( $label ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $rows as $row ) {
			echo '<tr>';
			printf( '<td>#%d</td>', (int) $row['id'] );
			printf( '<td>%s</td>', esc_html( (string) $row['occurred_at'] ) );
			printf( '<td><code>%s</code></td>', esc_html( (string) $row['category'] ) );
			printf( '<td><code>%s</code></td>', esc_html( (string) $row['severity'] ) );
			printf( '<td>%s</td>', esc_html( (string) $row['action'] ) );
			printf( '<td>%s</td>', esc_html( trim( (string) $row['actor_login'] . ' #' . (int) $row['actor_user_id'] ) ) );
			printf( '<td>%s</td>', esc_html( (string) $row['target'] ) );
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private function render_incidents() : void {
		$rows = IncidentStore::recent( 200 );

		echo '<p><a class="button button-primary" href="' . esc_url( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'incidents', 'edit' => 'new' ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Create incident', 'eurocomply-nis2' ) . '</a> <a class="button" href="' . esc_url( CsvExport::url( 'incidents' ) ) . '">' . esc_html__( 'Export CSV', 'eurocomply-nis2' ) . '</a></p>';

		$this->render_incidents_table( $rows );
	}

	/**
	 * @param array<int,array<string,mixed>> $rows
	 */
	private function render_incidents_table( array $rows ) : void {
		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No incidents recorded yet.', 'eurocomply-nis2' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr>';
		foreach ( array( 'ID', __( 'Created', 'eurocomply-nis2' ), __( 'Title', 'eurocomply-nis2' ), __( 'Category', 'eurocomply-nis2' ), __( 'Severity', 'eurocomply-nis2' ), __( 'Status', 'eurocomply-nis2' ), __( 'Art. 23 deadlines', 'eurocomply-nis2' ), '' ) as $label ) {
			echo '<th>' . esc_html( $label ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			$d       = IncidentStore::deadlines( $row );
			$overdue = $d['overdue'];
			echo '<tr>';
			printf( '<td>#%d</td>', (int) $row['id'] );
			printf( '<td>%s</td>', esc_html( (string) $row['created_at'] ) );
			printf( '<td>%s</td>', esc_html( (string) $row['title'] ) );
			printf( '<td><code>%s</code></td>', esc_html( (string) $row['category'] ) );
			printf( '<td><code>%s</code></td>', esc_html( (string) $row['severity'] ) );
			printf( '<td><code>%s</code></td>', esc_html( (string) $row['status'] ) );

			$badges = array();
			foreach ( array( 'early_warning' => '24h', 'notification' => '72h', 'intermediate' => '30d', 'final' => 'final' ) as $stage => $label ) {
				if ( '' === (string) ( $d[ $stage ] ?? '' ) ) {
					continue;
				}
				$is_overdue = in_array( $stage, $overdue, true );
				$color      = $is_overdue ? '#d63638' : '#50575e';
				$badges[]   = sprintf( '<span style="color:%s"><abbr title="%s">%s</abbr></span>', esc_attr( $color ), esc_attr( (string) $d[ $stage ] ), esc_html( $label ) );
			}
			echo '<td>' . implode( ' · ', $badges ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

			$edit = esc_url( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'incidents', 'edit' => (int) $row['id'] ), admin_url( 'admin.php' ) ) );
			printf( '<td><a class="button" href="%s">%s</a></td>', $edit, esc_html__( 'Open', 'eurocomply-nis2' ) );
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private function render_incident_editor( int $id_or_new ) : void {
		$is_new   = 'new' === (string) ( $_GET['edit'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$incident = $is_new ? array() : (array) IncidentStore::get( $id_or_new );
		if ( ! $is_new && empty( $incident ) ) {
			echo '<p>' . esc_html__( 'Incident not found.', 'eurocomply-nis2' ) . '</p>';
			return;
		}

		$id = (int) ( $incident['id'] ?? 0 );

		if ( ! $is_new ) {
			$d       = IncidentStore::deadlines( $incident );
			$overdue = $d['overdue'];

			echo '<h2>' . esc_html( sprintf( /* translators: %d: incident id */ __( 'Incident #%d', 'eurocomply-nis2' ), $id ) ) . '</h2>';
			echo '<div class="eurocomply-nis2-deadlines">';
			foreach ( array(
				'early_warning' => __( 'Early warning (24h)', 'eurocomply-nis2' ),
				'notification'  => __( 'Incident notification (72h)', 'eurocomply-nis2' ),
				'intermediate'  => __( 'Intermediate report (30d)', 'eurocomply-nis2' ),
				'final'         => __( 'Final report (30d post-handling)', 'eurocomply-nis2' ),
			) as $stage => $label ) {
				$sent_col  = $stage . '_sent_at';
				$sent      = (string) ( $incident[ $sent_col ] ?? '' );
				$deadline  = (string) ( $d[ $stage ] ?? '' );
				$class     = '' !== $sent ? 'sent' : ( in_array( $stage, $overdue, true ) ? 'overdue' : 'pending' );
				$action    = '' !== $sent ? esc_html__( 'Sent at', 'eurocomply-nis2' ) . ' ' . esc_html( $sent ) : ( '' === $deadline ? esc_html__( 'Pending — set "Aware at" first.', 'eurocomply-nis2' ) : esc_html__( 'Due', 'eurocomply-nis2' ) . ' ' . esc_html( $deadline ) );
				$build_url = wp_nonce_url(
					add_query_arg(
						array( 'request_id' => $id, 'op' => 'build', 'stage' => $stage ),
						admin_url( 'admin-post.php?action=' . self::ACTION_INC )
					),
					self::NONCE_INC,
					'_wpnonce'
				);
				$mark_url  = wp_nonce_url(
					add_query_arg(
						array( 'request_id' => $id, 'op' => 'mark_sent', 'stage' => $stage ),
						admin_url( 'admin-post.php?action=' . self::ACTION_INC )
					),
					self::NONCE_INC,
					'_wpnonce'
				);

				echo '<div class="eurocomply-nis2-deadline eurocomply-nis2-deadline--' . esc_attr( $class ) . '">';
				echo '<strong>' . esc_html( $label ) . '</strong><br />';
				echo '<span>' . $action . '</span><br />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				if ( '' === $sent ) {
					echo '<a class="button button-primary" href="' . esc_url( $build_url ) . '">' . esc_html__( 'Build report', 'eurocomply-nis2' ) . '</a> ';
					echo '<a class="button" href="' . esc_url( $mark_url ) . '">' . esc_html__( 'Mark sent', 'eurocomply-nis2' ) . '</a>';
				}
				echo '</div>';
			}
			echo '</div>';
		}

		if ( ! empty( $_GET['report_body'] ) && ! empty( $_GET['report_stage'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$rep_body = (string) base64_decode( sanitize_text_field( wp_unslash( (string) $_GET['report_body'] ) ), true ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$rep_json = ! empty( $_GET['report_json'] ) ? (string) base64_decode( sanitize_text_field( wp_unslash( (string) $_GET['report_json'] ) ), true ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="eurocomply-nis2-report-preview">';
			echo '<h3>' . esc_html( IncidentReportBuilder::stage_label( sanitize_key( (string) $_GET['report_stage'] ) ) ) . '</h3>'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<h4>' . esc_html__( 'Plain-text template', 'eurocomply-nis2' ) . '</h4>';
			echo '<textarea rows="12" class="large-text code" readonly="readonly">' . esc_textarea( $rep_body ) . '</textarea>';
			echo '<h4>' . esc_html__( 'JSON payload', 'eurocomply-nis2' ) . '</h4>';
			echo '<textarea rows="10" class="large-text code" readonly="readonly">' . esc_textarea( $rep_json ) . '</textarea>';
			echo '</div>';
		}

		$row = wp_parse_args(
			$incident,
			array(
				'title'                   => '',
				'category'                => 'other',
				'severity'                => 'medium',
				'status'                  => 'draft',
				'aware_at'                => '',
				'resolved_at'             => '',
				'impact_summary'          => '',
				'affected_systems'        => '',
				'affected_users_estimate' => 0,
				'root_cause'              => '',
				'mitigation'              => '',
				'csirt_case_ref'          => '',
				'notes'                   => '',
			)
		);
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( self::NONCE_INC, '_wpnonce' ); ?>
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_INC ); ?>" />
			<input type="hidden" name="op" value="save" />
			<input type="hidden" name="request_id" value="<?php echo esc_attr( (string) $id ); ?>" />

			<table class="form-table" role="presentation">
				<tr><th><label for="inc-title"><?php esc_html_e( 'Title', 'eurocomply-nis2' ); ?></label></th>
				<td><input type="text" id="inc-title" name="title" class="large-text" value="<?php echo esc_attr( (string) $row['title'] ); ?>" maxlength="255" required="required" /></td></tr>

				<tr><th><label for="inc-category"><?php esc_html_e( 'Category', 'eurocomply-nis2' ); ?></label></th>
				<td><select id="inc-category" name="category"><?php foreach ( IncidentStore::CATEGORIES as $c ) : ?>
					<option value="<?php echo esc_attr( $c ); ?>" <?php selected( (string) $row['category'], $c ); ?>><?php echo esc_html( $c ); ?></option>
				<?php endforeach; ?></select></td></tr>

				<tr><th><label for="inc-severity"><?php esc_html_e( 'Severity', 'eurocomply-nis2' ); ?></label></th>
				<td><select id="inc-severity" name="severity"><?php foreach ( IncidentStore::SEVERITIES as $sv ) : ?>
					<option value="<?php echo esc_attr( $sv ); ?>" <?php selected( (string) $row['severity'], $sv ); ?>><?php echo esc_html( $sv ); ?></option>
				<?php endforeach; ?></select></td></tr>

				<tr><th><label for="inc-status"><?php esc_html_e( 'Status', 'eurocomply-nis2' ); ?></label></th>
				<td><select id="inc-status" name="status"><?php foreach ( IncidentStore::STATUSES as $st ) : ?>
					<option value="<?php echo esc_attr( $st ); ?>" <?php selected( (string) $row['status'], $st ); ?>><?php echo esc_html( $st ); ?></option>
				<?php endforeach; ?></select></td></tr>

				<tr><th><label for="inc-aware"><?php esc_html_e( 'Aware at (UTC)', 'eurocomply-nis2' ); ?></label></th>
				<td><input type="text" id="inc-aware" name="aware_at" class="regular-text" value="<?php echo esc_attr( (string) $row['aware_at'] ); ?>" placeholder="YYYY-MM-DD HH:MM:SS" />
				<p class="description"><?php esc_html_e( 'Sets the 24h / 72h / 30d clocks (Art. 23).', 'eurocomply-nis2' ); ?></p></td></tr>

				<tr><th><label for="inc-resolved"><?php esc_html_e( 'Resolved at (UTC)', 'eurocomply-nis2' ); ?></label></th>
				<td><input type="text" id="inc-resolved" name="resolved_at" class="regular-text" value="<?php echo esc_attr( (string) $row['resolved_at'] ); ?>" placeholder="YYYY-MM-DD HH:MM:SS" />
				<p class="description"><?php esc_html_e( 'Sets the Final-report clock (Art. 23(4)(d)).', 'eurocomply-nis2' ); ?></p></td></tr>

				<tr><th><label for="inc-impact"><?php esc_html_e( 'Impact summary', 'eurocomply-nis2' ); ?></label></th>
				<td><textarea id="inc-impact" name="impact_summary" rows="3" class="large-text"><?php echo esc_textarea( (string) $row['impact_summary'] ); ?></textarea></td></tr>

				<tr><th><label for="inc-systems"><?php esc_html_e( 'Affected systems', 'eurocomply-nis2' ); ?></label></th>
				<td><textarea id="inc-systems" name="affected_systems" rows="3" class="large-text"><?php echo esc_textarea( (string) $row['affected_systems'] ); ?></textarea></td></tr>

				<tr><th><label for="inc-users"><?php esc_html_e( 'Affected users (estimate)', 'eurocomply-nis2' ); ?></label></th>
				<td><input type="number" id="inc-users" name="affected_users_estimate" min="0" value="<?php echo esc_attr( (string) $row['affected_users_estimate'] ); ?>" /></td></tr>

				<tr><th><label for="inc-cause"><?php esc_html_e( 'Root cause', 'eurocomply-nis2' ); ?></label></th>
				<td><textarea id="inc-cause" name="root_cause" rows="3" class="large-text"><?php echo esc_textarea( (string) $row['root_cause'] ); ?></textarea></td></tr>

				<tr><th><label for="inc-mitigation"><?php esc_html_e( 'Mitigation', 'eurocomply-nis2' ); ?></label></th>
				<td><textarea id="inc-mitigation" name="mitigation" rows="3" class="large-text"><?php echo esc_textarea( (string) $row['mitigation'] ); ?></textarea></td></tr>

				<tr><th><label for="inc-case"><?php esc_html_e( 'CSIRT case reference', 'eurocomply-nis2' ); ?></label></th>
				<td><input type="text" id="inc-case" name="csirt_case_ref" class="regular-text" value="<?php echo esc_attr( (string) $row['csirt_case_ref'] ); ?>" maxlength="64" /></td></tr>

				<tr><th><label for="inc-notes"><?php esc_html_e( 'Notes', 'eurocomply-nis2' ); ?></label></th>
				<td><textarea id="inc-notes" name="notes" rows="5" class="large-text"><?php echo esc_textarea( (string) $row['notes'] ); ?></textarea></td></tr>
			</table>
			<?php submit_button( $is_new ? __( 'Create incident', 'eurocomply-nis2' ) : __( 'Save incident', 'eurocomply-nis2' ) ); ?>
		</form>
		<?php
	}

	private function render_csirts() : void {
		$all = CsirtDirectory::all();
		echo '<h2>' . esc_html__( 'EU CSIRT contact directory', 'eurocomply-nis2' ) . '</h2>';
		echo '<p>' . esc_html__( 'Reporting addresses for national CSIRTs and competent authorities under NIS2 Art. 10 / 23. Verify details on nis.europa.eu before use.', 'eurocomply-nis2' ) . '</p>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Country', 'eurocomply-nis2' ) . '</th><th>' . esc_html__( 'CSIRT', 'eurocomply-nis2' ) . '</th><th>' . esc_html__( 'Email', 'eurocomply-nis2' ) . '</th><th>' . esc_html__( 'Website', 'eurocomply-nis2' ) . '</th><th>' . esc_html__( 'Portal', 'eurocomply-nis2' ) . '</th></tr></thead><tbody>';
		foreach ( $all as $cc => $row ) {
			echo '<tr>';
			printf( '<td><code>%s</code></td>', esc_html( $cc ) );
			printf( '<td>%s</td>', esc_html( (string) $row['name'] ) );
			printf( '<td><a href="mailto:%1$s">%1$s</a></td>', esc_attr( (string) $row['email'] ) );
			printf( '<td><a href="%1$s" target="_blank" rel="noopener noreferrer">%1$s</a></td>', esc_url( (string) $row['website'] ) );
			if ( ! empty( $row['portal'] ) ) {
				printf( '<td><a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a></td>', esc_url( (string) $row['portal'] ), esc_html__( 'Submit', 'eurocomply-nis2' ) );
			} else {
				echo '<td>—</td>';
			}
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

			<h2><?php esc_html_e( 'Entity', 'eurocomply-nis2' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr><th><label for="org_name"><?php esc_html_e( 'Organisation name', 'eurocomply-nis2' ); ?></label></th>
				<td><input type="text" id="org_name" name="eurocomply_nis2[organisation_name]" value="<?php echo esc_attr( (string) $s['organisation_name'] ); ?>" class="regular-text" /></td></tr>

				<tr><th><label for="entity_type"><?php esc_html_e( 'Entity type', 'eurocomply-nis2' ); ?></label></th>
				<td><select id="entity_type" name="eurocomply_nis2[entity_type]">
					<option value="essential" <?php selected( (string) $s['entity_type'], 'essential' ); ?>><?php esc_html_e( 'Essential entity (Annex I)', 'eurocomply-nis2' ); ?></option>
					<option value="important" <?php selected( (string) $s['entity_type'], 'important' ); ?>><?php esc_html_e( 'Important entity (Annex II)', 'eurocomply-nis2' ); ?></option>
					<option value="out_of_scope" <?php selected( (string) $s['entity_type'], 'out_of_scope' ); ?>><?php esc_html_e( 'Out of scope (self-assessment)', 'eurocomply-nis2' ); ?></option>
				</select></td></tr>

				<tr><th><label for="sector"><?php esc_html_e( 'Sector', 'eurocomply-nis2' ); ?></label></th>
				<td><input type="text" id="sector" name="eurocomply_nis2[sector]" value="<?php echo esc_attr( (string) $s['sector'] ); ?>" class="regular-text" /></td></tr>

				<tr><th><label for="csirt_country"><?php esc_html_e( 'Primary CSIRT country', 'eurocomply-nis2' ); ?></label></th>
				<td><input type="text" id="csirt_country" name="eurocomply_nis2[csirt_country]" value="<?php echo esc_attr( (string) $s['csirt_country'] ); ?>" maxlength="2" class="small-text" />
				<p class="description"><?php esc_html_e( 'ISO-3166-1 alpha-2, e.g. DE, FR, IT, SE. Use "EU" for ENISA only.', 'eurocomply-nis2' ); ?></p></td></tr>

				<tr><th><label for="csirt_email"><?php esc_html_e( 'CSIRT override email', 'eurocomply-nis2' ); ?></label></th>
				<td><input type="email" id="csirt_email" name="eurocomply_nis2[csirt_email]" value="<?php echo esc_attr( (string) $s['csirt_email'] ); ?>" class="regular-text" />
				<p class="description"><?php esc_html_e( 'If empty, the address from the CSIRT directory is used.', 'eurocomply-nis2' ); ?></p></td></tr>

				<tr><th><label for="security_contact"><?php esc_html_e( 'Security contact (for incident reports)', 'eurocomply-nis2' ); ?></label></th>
				<td><input type="email" id="security_contact" name="eurocomply_nis2[security_contact_email]" value="<?php echo esc_attr( (string) $s['security_contact_email'] ); ?>" class="regular-text" /></td></tr>

				<tr><th><label for="security_policy_url"><?php esc_html_e( 'security.txt / policy URL', 'eurocomply-nis2' ); ?></label></th>
				<td><input type="url" id="security_policy_url" name="eurocomply_nis2[security_policy_url]" value="<?php echo esc_attr( (string) $s['security_policy_url'] ); ?>" class="regular-text" /></td></tr>
			</table>

			<h2><?php esc_html_e( 'Event logging', 'eurocomply-nis2' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr><th><?php esc_html_e( 'Log events', 'eurocomply-nis2' ); ?></th>
				<td>
					<label><input type="checkbox" name="eurocomply_nis2[log_failed_logins]" value="1" <?php checked( ! empty( $s['log_failed_logins'] ) ); ?> /> <?php esc_html_e( 'Failed logins', 'eurocomply-nis2' ); ?></label><br />
					<label><input type="checkbox" name="eurocomply_nis2[log_successful_logins]" value="1" <?php checked( ! empty( $s['log_successful_logins'] ) ); ?> /> <?php esc_html_e( 'Successful logins', 'eurocomply-nis2' ); ?></label><br />
					<label><input type="checkbox" name="eurocomply_nis2[log_user_changes]" value="1" <?php checked( ! empty( $s['log_user_changes'] ) ); ?> /> <?php esc_html_e( 'User register / delete / role changes', 'eurocomply-nis2' ); ?></label><br />
					<label><input type="checkbox" name="eurocomply_nis2[log_plugin_changes]" value="1" <?php checked( ! empty( $s['log_plugin_changes'] ) ); ?> /> <?php esc_html_e( 'Plugin activate / deactivate / upgrade', 'eurocomply-nis2' ); ?></label><br />
					<label><input type="checkbox" name="eurocomply_nis2[log_theme_changes]" value="1" <?php checked( ! empty( $s['log_theme_changes'] ) ); ?> /> <?php esc_html_e( 'Theme switch', 'eurocomply-nis2' ); ?></label><br />
					<label><input type="checkbox" name="eurocomply_nis2[log_option_changes]" value="1" <?php checked( ! empty( $s['log_option_changes'] ) ); ?> /> <?php esc_html_e( 'Option updates (noisy)', 'eurocomply-nis2' ); ?></label><br />
					<label><input type="checkbox" name="eurocomply_nis2[log_file_changes]" value="1" <?php checked( ! empty( $s['log_file_changes'] ) ); ?> /> <?php esc_html_e( 'Core / plugin / theme upgrades', 'eurocomply-nis2' ); ?></label>
				</td></tr>
				<tr><th><label for="retain_events_days"><?php esc_html_e( 'Event retention (days)', 'eurocomply-nis2' ); ?></label></th>
				<td><input type="number" id="retain_events_days" name="eurocomply_nis2[retain_events_days]" value="<?php echo esc_attr( (string) $s['retain_events_days'] ); ?>" min="30" max="3650" /></td></tr>
				<tr><th><label for="retain_incidents_days"><?php esc_html_e( 'Incident retention (days)', 'eurocomply-nis2' ); ?></label></th>
				<td><input type="number" id="retain_incidents_days" name="eurocomply_nis2[retain_incidents_days]" value="<?php echo esc_attr( (string) $s['retain_incidents_days'] ); ?>" min="365" max="3650" /></td></tr>
			</table>

			<h2><?php esc_html_e( 'Notifications', 'eurocomply-nis2' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr><th><label for="notification_emails"><?php esc_html_e( 'Admin notification emails', 'eurocomply-nis2' ); ?></label></th>
				<td><textarea id="notification_emails" name="eurocomply_nis2[notification_emails]" rows="3" class="large-text" placeholder="sec@example.com, cio@example.com"><?php echo esc_textarea( implode( ', ', (array) $s['notification_emails'] ) ); ?></textarea></td></tr>
				<tr><th><label for="public_vuln_form_enabled"><?php esc_html_e( 'Public vulnerability-report shortcode', 'eurocomply-nis2' ); ?></label></th>
				<td><label><input type="checkbox" id="public_vuln_form_enabled" name="eurocomply_nis2[public_vuln_form_enabled]" value="1" <?php checked( ! empty( $s['public_vuln_form_enabled'] ) ); ?> /> <?php esc_html_e( 'Enable [eurocomply_nis2_vuln_report] shortcode (CRA-aligned).', 'eurocomply-nis2' ); ?></label></td></tr>
			</table>

			<?php submit_button(); ?>
		</form>
		<?php
	}

	private function render_pro() : void {
		$pro   = License::is_pro();
		$items = array(
			__( 'SIEM forwarding (Splunk / ELK / Datadog / Graylog) via webhooks and syslog', 'eurocomply-nis2' ),
			__( 'MISP / OpenCTI threat-intel feed ingestion', 'eurocomply-nis2' ),
			__( 'Signed PDF incident reports (DPA-ready)', 'eurocomply-nis2' ),
			__( 'REST API for SIEM / SOAR integration', 'eurocomply-nis2' ),
			__( 'Automatic CSIRT portal submission (BE CCB, NL NCSC, IT ACN, PL CERT.PL)', 'eurocomply-nis2' ),
			__( 'Multisite aggregator across a WordPress network', 'eurocomply-nis2' ),
			__( 'Correlation with WP Activity Log / Wordfence / iThemes', 'eurocomply-nis2' ),
			__( '5,000-row CSV export cap (vs 500 in free)', 'eurocomply-nis2' ),
			__( 'Scheduled event pruning respecting retention policy', 'eurocomply-nis2' ),
			__( 'Slack / Teams / PagerDuty webhook routing per severity', 'eurocomply-nis2' ),
		);
		echo '<p>' . esc_html__( 'Pro unlocks enterprise-grade NIS2 / CRA integrations:', 'eurocomply-nis2' ) . '</p>';
		echo '<ul class="eurocomply-nis2-pro-list">';
		foreach ( $items as $item ) {
			echo '<li>' . esc_html( $item ) . '</li>';
		}
		echo '</ul>';
		echo '<p>' . ( $pro ? esc_html__( 'Pro is active.', 'eurocomply-nis2' ) : esc_html__( 'Activate a license in the License tab to unlock Pro stubs.', 'eurocomply-nis2' ) ) . '</p>';
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
					<th><label for="license_key"><?php esc_html_e( 'License key', 'eurocomply-nis2' ); ?></label></th>
					<td><input type="text" id="license_key" name="license_key" value="<?php echo esc_attr( (string) ( $license['key'] ?? '' ) ); ?>" class="regular-text" placeholder="EC-XXXXXX" />
					<p class="description"><?php echo $is_pro ? esc_html__( 'Pro is active.', 'eurocomply-nis2' ) : esc_html__( 'Enter a license key in the form EC-XXXXXX to activate Pro stubs.', 'eurocomply-nis2' ); ?></p></td>
				</tr>
			</table>
			<p class="submit">
				<button type="submit" name="license_op" value="activate" class="button button-primary"><?php esc_html_e( 'Activate', 'eurocomply-nis2' ); ?></button>
				<button type="submit" name="license_op" value="deactivate" class="button"><?php esc_html_e( 'Deactivate', 'eurocomply-nis2' ); ?></button>
			</p>
		</form>
		<?php
	}

	public function handle_save() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-nis2' ), 403 );
		}
		check_admin_referer( self::NONCE_SAVE );

		$input = isset( $_POST['eurocomply_nis2'] ) && is_array( $_POST['eurocomply_nis2'] )
			? wp_unslash( (array) $_POST['eurocomply_nis2'] )
			: array();

		$sanitized = Settings::sanitize( $input );
		update_option( Settings::OPTION_KEY, $sanitized, false );

		add_settings_error( 'eurocomply_nis2', 'saved', __( 'Settings saved.', 'eurocomply-nis2' ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => self::MENU_SLUG, 'tab' => 'settings', 'settings-updated' => 'true' ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function handle_license() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-nis2' ), 403 );
		}
		check_admin_referer( self::NONCE_LIC );

		$op  = isset( $_POST['license_op'] ) ? sanitize_key( (string) $_POST['license_op'] ) : '';
		$key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['license_key'] ) ) : '';

		if ( 'deactivate' === $op ) {
			License::deactivate();
			add_settings_error( 'eurocomply_nis2', 'lic_off', __( 'License deactivated.', 'eurocomply-nis2' ), 'updated' );
		} else {
			$res = License::activate( $key );
			add_settings_error( 'eurocomply_nis2', 'lic', $res['message'], $res['ok'] ? 'updated' : 'error' );
		}

		set_transient( 'settings_errors', get_settings_errors(), 30 );

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => self::MENU_SLUG, 'tab' => 'license', 'settings-updated' => 'true' ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function handle_incident() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-nis2' ), 403 );
		}
		check_admin_referer( self::NONCE_INC );

		$op = isset( $_REQUEST['op'] ) ? sanitize_key( (string) $_REQUEST['op'] ) : '';
		$id = isset( $_REQUEST['request_id'] ) ? (int) $_REQUEST['request_id'] : 0;

		$extra_args = array();
		$redir_tab  = 'incidents';
		$message    = '';
		$type       = 'updated';

		switch ( $op ) {
			case 'save':
				$data = array(
					'title'                   => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['title'] ) ) : '',
					'category'                => isset( $_POST['category'] ) ? sanitize_key( (string) $_POST['category'] ) : 'other',
					'severity'                => isset( $_POST['severity'] ) ? sanitize_key( (string) $_POST['severity'] ) : 'medium',
					'status'                  => isset( $_POST['status'] ) ? sanitize_key( (string) $_POST['status'] ) : 'draft',
					'aware_at'                => isset( $_POST['aware_at'] ) ? self::date_or_null( (string) $_POST['aware_at'] ) : null,
					'resolved_at'             => isset( $_POST['resolved_at'] ) ? self::date_or_null( (string) $_POST['resolved_at'] ) : null,
					'impact_summary'          => isset( $_POST['impact_summary'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['impact_summary'] ) ) : '',
					'affected_systems'        => isset( $_POST['affected_systems'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['affected_systems'] ) ) : '',
					'affected_users_estimate' => isset( $_POST['affected_users_estimate'] ) ? (int) $_POST['affected_users_estimate'] : 0,
					'root_cause'              => isset( $_POST['root_cause'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['root_cause'] ) ) : '',
					'mitigation'              => isset( $_POST['mitigation'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['mitigation'] ) ) : '',
					'csirt_case_ref'          => isset( $_POST['csirt_case_ref'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['csirt_case_ref'] ) ) : '',
					'notes'                   => isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['notes'] ) ) : '',
				);
				if ( $id > 0 ) {
					IncidentStore::update( $id, $data );
					$message = __( 'Incident updated.', 'eurocomply-nis2' );
				} else {
					$id      = IncidentStore::create( $data );
					$message = sprintf( /* translators: %d: id */ __( 'Incident #%d created.', 'eurocomply-nis2' ), $id );
				}
				$extra_args['edit'] = $id;
				break;

			case 'build':
				$stage = isset( $_GET['stage'] ) ? sanitize_key( (string) $_GET['stage'] ) : '';
				$res   = IncidentReportBuilder::build( $id, $stage );
				if ( ! $res['ok'] ) {
					$type    = 'error';
					$message = $res['message'];
				} else {
					$message              = sprintf( /* translators: %s: stage label */ __( '%s report built — preview below.', 'eurocomply-nis2' ), IncidentReportBuilder::stage_label( $stage ) );
					$extra_args['edit']         = $id;
					$extra_args['report_stage'] = $stage;
					$extra_args['report_body']  = base64_encode( $res['body'] );
					$extra_args['report_json']  = base64_encode( $res['json'] );
				}
				break;

			case 'mark_sent':
				$stage = isset( $_GET['stage'] ) ? sanitize_key( (string) $_GET['stage'] ) : '';
				$col   = in_array( $stage, IncidentReportBuilder::STAGES, true ) ? $stage . '_sent_at' : '';
				if ( '' === $col ) {
					$type    = 'error';
					$message = __( 'Unknown stage.', 'eurocomply-nis2' );
				} else {
					IncidentStore::update( $id, array( $col => current_time( 'mysql' ) ) );
					$message            = sprintf( /* translators: %s: stage label */ __( '%s marked as sent.', 'eurocomply-nis2' ), IncidentReportBuilder::stage_label( $stage ) );
					$extra_args['edit'] = $id;
				}
				break;

			default:
				$type    = 'error';
				$message = __( 'Unknown operation.', 'eurocomply-nis2' );
		}

		add_settings_error( 'eurocomply_nis2', 'inc', $message, $type );
		set_transient( 'settings_errors', get_settings_errors(), 30 );

		$args = array_merge(
			array( 'page' => self::MENU_SLUG, 'tab' => $redir_tab, 'settings-updated' => 'true' ),
			$extra_args
		);
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	private static function date_or_null( string $raw ) : ?string {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return null;
		}
		$ts = strtotime( $raw );
		if ( false === $ts ) {
			return null;
		}
		return gmdate( 'Y-m-d H:i:s', $ts );
	}
}
