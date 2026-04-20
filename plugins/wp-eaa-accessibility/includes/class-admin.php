<?php
/**
 * WordPress admin UI for EuroComply EAA Accessibility.
 *
 * 4 tabs: Scanner · Statement · Pro Features · License.
 *
 * @package EuroComply\Eaa
 */

declare( strict_types = 1 );

namespace EuroComply\Eaa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin {

	public const MENU_SLUG  = 'eurocomply-eaa';
	public const NONCE_SAVE = 'eurocomply_eaa_save';
	public const NONCE_SCAN = 'eurocomply_eaa_scan';

	private static ?Admin $instance = null;

	public static function instance() : Admin {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}
		return self::$instance;
	}

	private function boot() : void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_eurocomply_eaa_save', array( $this, 'handle_save' ) );
		add_action( 'admin_post_eurocomply_eaa_license', array( $this, 'handle_license' ) );
		add_action( 'admin_post_eurocomply_eaa_scan', array( $this, 'handle_scan' ) );
		add_action( 'admin_post_eurocomply_eaa_export', array( $this, 'handle_export' ) );
		add_action( 'admin_post_eurocomply_eaa_clear', array( $this, 'handle_clear' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function register_menu() : void {
		add_menu_page(
			__( 'EuroComply EAA', 'eurocomply-eaa' ),
			__( 'Accessibility', 'eurocomply-eaa' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_page' ),
			'dashicons-universal-access-alt',
			75
		);
	}

	public function enqueue( string $hook ) : void {
		if ( false === strpos( $hook, self::MENU_SLUG ) ) {
			return;
		}
		wp_enqueue_style(
			'eurocomply-eaa-admin',
			EUROCOMPLY_EAA_URL . 'assets/css/admin.css',
			array(),
			EUROCOMPLY_EAA_VERSION
		);
	}

	public function render_page() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'eurocomply-eaa' ) );
		}

		$allowed_tabs = array( 'scanner', 'statement', 'pro', 'license' );
		$tab          = isset( $_GET['tab'] ) ? sanitize_key( (string) wp_unslash( $_GET['tab'] ) ) : 'scanner'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $tab, $allowed_tabs, true ) ) {
			$tab = 'scanner';
		}

		$is_pro = License::is_pro();

		echo '<div class="wrap eurocomply-eaa-admin">';
		echo '<h1>' . esc_html__( 'EuroComply EAA Accessibility', 'eurocomply-eaa' ) . ' <span class="eurocomply-eaa-version">v' . esc_html( EUROCOMPLY_EAA_VERSION ) . '</span></h1>';

		$this->render_tabs( $tab, $is_pro );
		settings_errors( 'eurocomply_eaa' );

		switch ( $tab ) {
			case 'statement':
				$this->render_statement_tab( Settings::get() );
				break;
			case 'pro':
				$this->render_pro_tab( $is_pro );
				break;
			case 'license':
				$this->render_license_tab( $is_pro );
				break;
			case 'scanner':
			default:
				$this->render_scanner_tab( Settings::get() );
				break;
		}
		echo '</div>';
	}

	private function render_tabs( string $active, bool $is_pro ) : void {
		$tabs = array(
			'scanner'   => __( 'Scanner', 'eurocomply-eaa' ),
			'statement' => __( 'Statement', 'eurocomply-eaa' ),
			'pro'       => __( 'Pro Features', 'eurocomply-eaa' ),
			'license'   => $is_pro ? __( 'License', 'eurocomply-eaa' ) : __( 'License (Pro)', 'eurocomply-eaa' ),
		);
		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $tabs as $slug => $label ) {
			$url   = add_query_arg(
				array(
					'page' => self::MENU_SLUG,
					'tab'  => $slug,
				),
				admin_url( 'admin.php' )
			);
			$class = 'nav-tab' . ( $slug === $active ? ' nav-tab-active' : '' );
			echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</h2>';
	}

	private function render_scanner_tab( array $settings ) : void {
		$action_url = admin_url( 'admin-post.php' );
		$sev        = IssueStore::counts_by_severity();
		$total      = IssueStore::total();

		$scan_url = wp_nonce_url(
			add_query_arg( array( 'action' => 'eurocomply_eaa_scan' ), $action_url ),
			self::NONCE_SCAN
		);
		$export_url = wp_nonce_url(
			add_query_arg( array( 'action' => 'eurocomply_eaa_export' ), $action_url ),
			'eurocomply_eaa_csv'
		);
		$clear_url = wp_nonce_url(
			add_query_arg( array( 'action' => 'eurocomply_eaa_clear' ), $action_url ),
			'eurocomply_eaa_clear'
		);

		echo '<p class="eurocomply-eaa-summary">';
		printf(
			/* translators: 1: total, 2: serious, 3: moderate, 4: minor */
			esc_html__( '%1$d issues recorded · %2$d serious · %3$d moderate · %4$d minor.', 'eurocomply-eaa' ),
			(int) $total,
			(int) $sev['serious'],
			(int) $sev['moderate'],
			(int) $sev['minor']
		);
		echo '</p>';

		echo '<p>';
		echo '<a class="button button-primary" href="' . esc_url( $scan_url ) . '">' . esc_html__( 'Scan published posts & pages', 'eurocomply-eaa' ) . '</a> ';
		echo '<a class="button" href="' . esc_url( $export_url ) . '">' . esc_html__( 'Download CSV', 'eurocomply-eaa' ) . '</a> ';
		echo '<a class="button button-link-delete" href="' . esc_url( $clear_url ) . '">' . esc_html__( 'Clear all issues', 'eurocomply-eaa' ) . '</a>';
		echo '</p>';

		// Enabled-rules form.
		$catalog = Rules::all();
		$enabled = (array) $settings['enabled_rules'];
		echo '<h3>' . esc_html__( 'Enabled rules', 'eurocomply-eaa' ) . '</h3>';
		echo '<form method="post" action="' . esc_url( $action_url ) . '">';
		echo '<input type="hidden" name="action" value="eurocomply_eaa_save" />';
		echo '<input type="hidden" name="redirect_tab" value="scanner" />';
		wp_nonce_field( self::NONCE_SAVE );
		// Preserve statement + toggles fields so they are not wiped by a partial save.
		foreach ( array( 'inject_skip_link', 'focus_outline_polyfill', 'scan_on_save' ) as $flag ) {
			echo '<input type="hidden" name="' . esc_attr( $flag ) . '" value="' . esc_attr( (string) (int) $settings[ $flag ] ) . '" />';
		}
		foreach ( array( 'statement_entity_name', 'statement_contact_email', 'statement_conformance', 'statement_last_review' ) as $flag ) {
			echo '<input type="hidden" name="' . esc_attr( $flag ) . '" value="' . esc_attr( (string) $settings[ $flag ] ) . '" />';
		}
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Enabled', 'eurocomply-eaa' ) . '</th>';
		echo '<th>' . esc_html__( 'Rule', 'eurocomply-eaa' ) . '</th>';
		echo '<th>' . esc_html__( 'WCAG', 'eurocomply-eaa' ) . '</th>';
		echo '<th>' . esc_html__( 'Severity', 'eurocomply-eaa' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( $catalog as $key => $meta ) {
			$checked = in_array( $key, $enabled, true );
			echo '<tr>';
			echo '<td><input type="checkbox" name="enabled_rules[]" value="' . esc_attr( $key ) . '" ' . checked( $checked, true, false ) . ' /></td>';
			echo '<td>' . esc_html( (string) $meta['label'] ) . '</td>';
			echo '<td>' . esc_html( (string) $meta['wcag'] ) . '</td>';
			echo '<td>' . esc_html( (string) $meta['severity'] ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
		submit_button( __( 'Save enabled rules', 'eurocomply-eaa' ) );
		echo '</form>';

		// Recent issues table.
		$rows = IssueStore::recent_issues( 200 );
		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No issues recorded yet. Save a published post or click "Scan published posts & pages" to populate this list.', 'eurocomply-eaa' ) . '</p>';
			return;
		}

		echo '<h3>' . esc_html__( 'Recent issues (latest 200)', 'eurocomply-eaa' ) . '</h3>';
		echo '<table class="widefat striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'When', 'eurocomply-eaa' ) . '</th>';
		echo '<th>' . esc_html__( 'URL', 'eurocomply-eaa' ) . '</th>';
		echo '<th>' . esc_html__( 'Rule', 'eurocomply-eaa' ) . '</th>';
		echo '<th>' . esc_html__( 'WCAG', 'eurocomply-eaa' ) . '</th>';
		echo '<th>' . esc_html__( 'Severity', 'eurocomply-eaa' ) . '</th>';
		echo '<th>' . esc_html__( 'Snippet', 'eurocomply-eaa' ) . '</th>';
		echo '</tr></thead><tbody>';
		$catalog = Rules::all();
		foreach ( $rows as $r ) {
			$rule_key = (string) $r['rule'];
			$label    = isset( $catalog[ $rule_key ]['label'] ) ? (string) $catalog[ $rule_key ]['label'] : $rule_key;
			echo '<tr>';
			echo '<td>' . esc_html( (string) $r['scanned_at'] ) . '</td>';
			echo '<td><code>' . esc_html( (string) $r['url'] ) . '</code></td>';
			echo '<td>' . esc_html( $label ) . '</td>';
			echo '<td>' . esc_html( (string) $r['wcag'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['severity'] ) . '</td>';
			echo '<td><code>' . esc_html( (string) $r['snippet'] ) . '</code></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private function render_statement_tab( array $settings ) : void {
		$action_url = admin_url( 'admin-post.php' );
		$page_id    = StatementPage::get_page_id();
		?>
		<p class="description">
			<?php esc_html_e( 'The European Accessibility Act (Art. 7) requires a public accessibility statement. The plugin auto-creates a page with the [eurocomply_eaa_statement] shortcode on activation; this tab controls what the shortcode renders.', 'eurocomply-eaa' ); ?>
		</p>
		<?php if ( $page_id ) : ?>
			<p>
				<?php
				$view_url = (string) get_permalink( $page_id );
				$edit_url = (string) get_edit_post_link( $page_id );
				printf(
					/* translators: 1: view URL, 2: edit URL */
					wp_kses_post( __( 'Statement page: <a href="%1$s">View</a> · <a href="%2$s">Edit</a>.', 'eurocomply-eaa' ) ),
					esc_url( $view_url ),
					esc_url( $edit_url )
				);
				?>
			</p>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( $action_url ); ?>">
			<input type="hidden" name="action" value="eurocomply_eaa_save" />
			<input type="hidden" name="redirect_tab" value="statement" />
			<?php wp_nonce_field( self::NONCE_SAVE ); ?>

			<?php // Preserve scanner-tab settings. ?>
			<?php foreach ( (array) ( $settings['enabled_rules'] ?? array() ) as $rule ) : ?>
				<input type="hidden" name="enabled_rules[]" value="<?php echo esc_attr( (string) $rule ); ?>" />
			<?php endforeach; ?>

			<h3><?php esc_html_e( 'Frontend injection', 'eurocomply-eaa' ); ?></h3>
			<table class="form-table"><tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Skip-to-content link', 'eurocomply-eaa' ); ?></th>
					<td>
						<label><input type="checkbox" name="inject_skip_link" value="1" <?php checked( ! empty( $settings['inject_skip_link'] ) ); ?> />
						<?php esc_html_e( 'Inject a visible-on-focus "Skip to content" link at the top of every page (requires theme support for wp_body_open).', 'eurocomply-eaa' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Focus outline polyfill', 'eurocomply-eaa' ); ?></th>
					<td>
						<label><input type="checkbox" name="focus_outline_polyfill" value="1" <?php checked( ! empty( $settings['focus_outline_polyfill'] ) ); ?> />
						<?php esc_html_e( 'Force a visible 2px outline on :focus-visible for themes that strip it.', 'eurocomply-eaa' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Scan on post save', 'eurocomply-eaa' ); ?></th>
					<td>
						<label><input type="checkbox" name="scan_on_save" value="1" <?php checked( ! empty( $settings['scan_on_save'] ) ); ?> />
						<?php esc_html_e( 'Re-scan a post automatically when it is published / updated.', 'eurocomply-eaa' ); ?></label>
					</td>
				</tr>
			</tbody></table>

			<h3><?php esc_html_e( 'Accessibility statement', 'eurocomply-eaa' ); ?></h3>
			<table class="form-table"><tbody>
				<tr>
					<th scope="row"><label for="statement_entity_name"><?php esc_html_e( 'Legal entity / site name', 'eurocomply-eaa' ); ?></label></th>
					<td><input type="text" class="regular-text" id="statement_entity_name" name="statement_entity_name" value="<?php echo esc_attr( (string) $settings['statement_entity_name'] ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="statement_contact_email"><?php esc_html_e( 'Accessibility contact email', 'eurocomply-eaa' ); ?></label></th>
					<td><input type="email" class="regular-text" id="statement_contact_email" name="statement_contact_email" value="<?php echo esc_attr( (string) $settings['statement_contact_email'] ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="statement_conformance"><?php esc_html_e( 'Conformance status', 'eurocomply-eaa' ); ?></label></th>
					<td>
						<select id="statement_conformance" name="statement_conformance">
							<?php foreach ( array(
								'full'    => __( 'Fully conformant (WCAG 2.1 AA)', 'eurocomply-eaa' ),
								'partial' => __( 'Partially conformant (WCAG 2.1 AA)', 'eurocomply-eaa' ),
								'non'     => __( 'Non-conformant', 'eurocomply-eaa' ),
							) as $k => $l ) : ?>
								<option value="<?php echo esc_attr( $k ); ?>" <?php selected( (string) $settings['statement_conformance'], $k ); ?>><?php echo esc_html( $l ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="statement_last_review"><?php esc_html_e( 'Last reviewed on', 'eurocomply-eaa' ); ?></label></th>
					<td><input type="date" id="statement_last_review" name="statement_last_review" value="<?php echo esc_attr( (string) $settings['statement_last_review'] ); ?>" /></td>
				</tr>
			</tbody></table>

			<?php submit_button( __( 'Save Changes', 'eurocomply-eaa' ) ); ?>
		</form>
		<?php
	}

	private function render_pro_tab( bool $is_pro ) : void {
		if ( $is_pro ) {
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Pro license active. Advanced accessibility workflows will ship in the next release.', 'eurocomply-eaa' ) . '</p></div>';
		} else {
			echo '<div class="notice notice-info inline"><p>' . esc_html__( 'Unlock these features with a Pro license.', 'eurocomply-eaa' ) . '</p></div>';
		}

		$features = array(
			array( 'title' => __( 'AI alt-text auto-fill', 'eurocomply-eaa' ), 'desc' => __( 'Generate descriptive alt text for media-library images and WooCommerce product galleries.', 'eurocomply-eaa' ) ),
			array( 'title' => __( 'Scheduled scans + email alerts', 'eurocomply-eaa' ), 'desc' => __( 'Weekly site-wide crawl with diff reports emailed to compliance owners.', 'eurocomply-eaa' ) ),
			array( 'title' => __( 'VPAT / EN 301 549 export', 'eurocomply-eaa' ), 'desc' => __( 'Generate a VPAT 2.5 + EN 301 549 conformance report for procurement buyers.', 'eurocomply-eaa' ) ),
			array( 'title' => __( 'ARIA remediation editor', 'eurocomply-eaa' ), 'desc' => __( 'In-page overlay that lets editors fix roles, labels and names without touching code.', 'eurocomply-eaa' ) ),
			array( 'title' => __( 'Computed contrast + focus order', 'eurocomply-eaa' ), 'desc' => __( 'Headless-Chromium checks for computed colour-contrast, reflow and keyboard focus order.', 'eurocomply-eaa' ) ),
		);
		echo '<div class="eurocomply-eaa-features">';
		foreach ( $features as $f ) {
			echo '<div class="eurocomply-eaa-feature-card">';
			echo '<h3>' . esc_html( $f['title'] ) . '</h3>';
			echo '<p>' . esc_html( $f['desc'] ) . '</p>';
			echo '</div>';
		}
		echo '</div>';
	}

	private function render_license_tab( bool $is_pro ) : void {
		$action_url = admin_url( 'admin-post.php' );
		$status     = $is_pro ? __( 'Active (Pro)', 'eurocomply-eaa' ) : __( 'Free tier', 'eurocomply-eaa' );
		?>
		<p><strong><?php esc_html_e( 'Status:', 'eurocomply-eaa' ); ?></strong> <?php echo esc_html( $status ); ?></p>
		<?php if ( $is_pro ) : ?>
			<form method="post" action="<?php echo esc_url( $action_url ); ?>">
				<input type="hidden" name="action" value="eurocomply_eaa_license" />
				<input type="hidden" name="mode" value="deactivate" />
				<?php wp_nonce_field( 'eurocomply_eaa_license' ); ?>
				<?php submit_button( __( 'Deactivate', 'eurocomply-eaa' ), 'secondary' ); ?>
			</form>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( $action_url ); ?>">
				<input type="hidden" name="action" value="eurocomply_eaa_license" />
				<input type="hidden" name="mode" value="activate" />
				<?php wp_nonce_field( 'eurocomply_eaa_license' ); ?>
				<p>
					<label for="eurocomply_eaa_license_key"><?php esc_html_e( 'License key', 'eurocomply-eaa' ); ?></label><br />
					<input type="text" id="eurocomply_eaa_license_key" name="license_key" class="regular-text" placeholder="EC-XXXXXX" />
				</p>
				<?php submit_button( __( 'Activate license', 'eurocomply-eaa' ) ); ?>
			</form>
		<?php endif; ?>
		<?php
	}

	public function handle_save() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-eaa' ) );
		}
		check_admin_referer( self::NONCE_SAVE );
		Settings::save( wp_unslash( $_POST ) );
		add_settings_error( 'eurocomply_eaa', 'saved', __( 'Settings saved.', 'eurocomply-eaa' ), 'updated' );

		$redirect_tab = isset( $_POST['redirect_tab'] ) ? sanitize_key( (string) wp_unslash( $_POST['redirect_tab'] ) ) : 'statement';
		if ( ! in_array( $redirect_tab, array( 'scanner', 'statement' ), true ) ) {
			$redirect_tab = 'statement';
		}

		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => $redirect_tab, 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_license() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-eaa' ) );
		}
		check_admin_referer( 'eurocomply_eaa_license' );

		$mode = isset( $_POST['mode'] ) ? sanitize_key( (string) wp_unslash( $_POST['mode'] ) ) : '';
		if ( 'deactivate' === $mode ) {
			License::deactivate();
			add_settings_error( 'eurocomply_eaa', 'license_off', __( 'License deactivated.', 'eurocomply-eaa' ), 'updated' );
		} else {
			$key    = isset( $_POST['license_key'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['license_key'] ) ) : '';
			$result = License::activate( $key );
			if ( $result['ok'] ) {
				add_settings_error( 'eurocomply_eaa', 'license_on', (string) $result['message'], 'updated' );
			} else {
				add_settings_error( 'eurocomply_eaa', 'license_err', (string) $result['message'], 'error' );
			}
		}
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'license', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_scan() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-eaa' ) );
		}
		check_admin_referer( self::NONCE_SCAN );

		$ids = get_posts(
			array(
				'post_type'      => array( 'post', 'page' ),
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'fields'         => 'ids',
			)
		);
		$scanner = Scanner::instance();
		$fetched = 0;
		$total   = 0;
		foreach ( $ids as $id ) {
			$url = (string) get_permalink( (int) $id );
			if ( '' === $url ) {
				continue;
			}
			$res = $scanner->scan_url_into_store( $url, 'post', (int) $id );
			if ( ! empty( $res['fetched'] ) ) {
				$fetched++;
				$total += count( $res['issues'] );
			}
		}

		add_settings_error(
			'eurocomply_eaa',
			'scan_done',
			sprintf(
				/* translators: 1: URLs scanned, 2: issues recorded */
				__( 'Scan complete: %1$d URLs scanned, %2$d issues recorded.', 'eurocomply-eaa' ),
				$fetched,
				$total
			),
			'updated'
		);
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'scanner', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_clear() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-eaa' ) );
		}
		check_admin_referer( 'eurocomply_eaa_clear' );
		IssueStore::clear_all();
		add_settings_error( 'eurocomply_eaa', 'cleared', __( 'All recorded issues cleared.', 'eurocomply-eaa' ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'scanner', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_export() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-eaa' ) );
		}
		check_admin_referer( 'eurocomply_eaa_csv' );
		CsvExport::stream();
		exit;
	}
}
