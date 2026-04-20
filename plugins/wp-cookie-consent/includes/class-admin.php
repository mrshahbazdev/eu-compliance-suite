<?php
/**
 * WordPress admin UI for EuroComply Cookie Consent.
 *
 * @package EuroComply\CookieConsent
 */

declare( strict_types = 1 );

namespace EuroComply\CookieConsent;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the admin menu and renders the 5 settings tabs.
 */
final class Admin {

	public const MENU_SLUG  = 'eurocomply-cookie-consent';
	public const NONCE_SAVE = 'eurocomply_cc_save';

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
		add_action( 'admin_post_eurocomply_cc_save', array( $this, 'handle_save' ) );
		add_action( 'admin_post_eurocomply_cc_license', array( $this, 'handle_license' ) );
		add_action( 'admin_post_eurocomply_cc_purge_log', array( $this, 'handle_purge_log' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function register_menu() : void {
		add_menu_page(
			__( 'EuroComply Cookie Consent', 'eurocomply-cookie-consent' ),
			__( 'Cookie Consent', 'eurocomply-cookie-consent' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_page' ),
			'dashicons-privacy',
			71
		);
	}

	public function enqueue( string $hook ) : void {
		if ( false === strpos( $hook, self::MENU_SLUG ) ) {
			return;
		}
		wp_enqueue_style(
			'eurocomply-cc-admin',
			EUROCOMPLY_CC_URL . 'assets/css/admin.css',
			array(),
			EUROCOMPLY_CC_VERSION
		);
	}

	/**
	 * Render the tabbed settings page.
	 */
	public function render_page() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'eurocomply-cookie-consent' ) );
		}

		$allowed_tabs = array( 'banner', 'categories', 'integrations', 'log', 'license' );
		$tab          = isset( $_GET['tab'] ) ? sanitize_key( (string) wp_unslash( $_GET['tab'] ) ) : 'banner'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $tab, $allowed_tabs, true ) ) {
			$tab = 'banner';
		}

		$settings = Settings::get();
		$is_pro   = License::is_pro();

		echo '<div class="wrap eurocomply-cc-admin">';
		echo '<h1>' . esc_html__( 'EuroComply Cookie Consent', 'eurocomply-cookie-consent' ) . ' <span class="eurocomply-cc-version">v' . esc_html( EUROCOMPLY_CC_VERSION ) . '</span></h1>';

		$this->render_tabs( $tab, $is_pro );

		settings_errors( 'eurocomply_cc' );

		switch ( $tab ) {
			case 'categories':
				$this->render_categories_tab( $settings );
				break;
			case 'integrations':
				$this->render_integrations_tab( $settings );
				break;
			case 'log':
				$this->render_log_tab( $is_pro );
				break;
			case 'license':
				$this->render_license_tab( $is_pro );
				break;
			case 'banner':
			default:
				$this->render_banner_tab( $settings );
				break;
		}
		echo '</div>';
	}

	private function render_tabs( string $active, bool $is_pro ) : void {
		$tabs = array(
			'banner'       => __( 'Banner', 'eurocomply-cookie-consent' ),
			'categories'   => __( 'Categories', 'eurocomply-cookie-consent' ),
			'integrations' => __( 'Integrations', 'eurocomply-cookie-consent' ),
			'log'          => __( 'Consent Log', 'eurocomply-cookie-consent' ),
			'license'      => $is_pro ? __( 'License', 'eurocomply-cookie-consent' ) : __( 'License (Pro)', 'eurocomply-cookie-consent' ),
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

	private function render_banner_tab( array $settings ) : void {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( self::NONCE_SAVE ); ?>
			<input type="hidden" name="action" value="eurocomply_cc_save" />
			<input type="hidden" name="tab" value="banner" />
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><label for="banner_position"><?php esc_html_e( 'Banner position', 'eurocomply-cookie-consent' ); ?></label></th>
						<td>
							<select id="banner_position" name="banner_position">
								<?php foreach ( array( 'bottom' => __( 'Bottom', 'eurocomply-cookie-consent' ), 'top' => __( 'Top', 'eurocomply-cookie-consent' ), 'modal' => __( 'Centered modal', 'eurocomply-cookie-consent' ) ) as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['banner_position'], $value ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="banner_layout"><?php esc_html_e( 'Layout', 'eurocomply-cookie-consent' ); ?></label></th>
						<td>
							<select id="banner_layout" name="banner_layout">
								<option value="box" <?php selected( $settings['banner_layout'], 'box' ); ?>><?php esc_html_e( 'Box', 'eurocomply-cookie-consent' ); ?></option>
								<option value="bar" <?php selected( $settings['banner_layout'], 'bar' ); ?>><?php esc_html_e( 'Bar', 'eurocomply-cookie-consent' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Colours', 'eurocomply-cookie-consent' ); ?></th>
						<td>
							<p>
								<label><?php esc_html_e( 'Background', 'eurocomply-cookie-consent' ); ?>
									<input type="text" name="banner_color_bg" value="<?php echo esc_attr( $settings['banner_color_bg'] ); ?>" placeholder="#111827" />
								</label>
							</p>
							<p>
								<label><?php esc_html_e( 'Text', 'eurocomply-cookie-consent' ); ?>
									<input type="text" name="banner_color_text" value="<?php echo esc_attr( $settings['banner_color_text'] ); ?>" placeholder="#f9fafb" />
								</label>
							</p>
							<p>
								<label><?php esc_html_e( 'Accent / button', 'eurocomply-cookie-consent' ); ?>
									<input type="text" name="banner_color_accent" value="<?php echo esc_attr( $settings['banner_color_accent'] ); ?>" placeholder="#2563eb" />
								</label>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Buttons', 'eurocomply-cookie-consent' ); ?></th>
						<td>
							<label><input type="checkbox" name="show_reject_button" value="1" <?php checked( ! empty( $settings['show_reject_button'] ) ); ?> /> <?php esc_html_e( 'Show a Reject button (recommended for GDPR)', 'eurocomply-cookie-consent' ); ?></label><br />
							<label><input type="checkbox" name="show_preferences_link" value="1" <?php checked( ! empty( $settings['show_preferences_link'] ) ); ?> /> <?php esc_html_e( 'Show a persistent “Cookie settings” link in the footer', 'eurocomply-cookie-consent' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Language', 'eurocomply-cookie-consent' ); ?></th>
						<td>
							<select name="primary_language">
								<option value="en" <?php selected( $settings['primary_language'], 'en' ); ?>>English</option>
								<option value="de" <?php selected( $settings['primary_language'], 'de' ); ?>>Deutsch</option>
							</select>
							<p><label><input type="checkbox" name="auto_language" value="1" <?php checked( ! empty( $settings['auto_language'] ) ); ?> /> <?php esc_html_e( 'Auto-detect from site locale (DE for de_DE / de_AT / de_CH, else English)', 'eurocomply-cookie-consent' ); ?></label></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="consent_days"><?php esc_html_e( 'Remember consent for', 'eurocomply-cookie-consent' ); ?></label></th>
						<td>
							<input id="consent_days" type="number" min="1" max="365" name="consent_days" value="<?php echo esc_attr( (string) $settings['consent_days'] ); ?>" />
							<?php esc_html_e( 'days', 'eurocomply-cookie-consent' ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Linked pages', 'eurocomply-cookie-consent' ); ?></th>
						<td>
							<p>
								<label><?php esc_html_e( 'Privacy policy page', 'eurocomply-cookie-consent' ); ?>
									<?php
									wp_dropdown_pages(
										array(
											'name'             => 'privacy_policy_page',
											'show_option_none' => __( '— None —', 'eurocomply-cookie-consent' ),
											'option_none_value' => '0',
											'selected'         => (int) $settings['privacy_policy_page'],
										)
									);
									?>
								</label>
							</p>
							<p>
								<label><?php esc_html_e( 'Imprint page', 'eurocomply-cookie-consent' ); ?>
									<?php
									wp_dropdown_pages(
										array(
											'name'             => 'imprint_page',
											'show_option_none' => __( '— None —', 'eurocomply-cookie-consent' ),
											'option_none_value' => '0',
											'selected'         => (int) $settings['imprint_page'],
										)
									);
									?>
								</label>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Re-prompt visitors', 'eurocomply-cookie-consent' ); ?></th>
						<td>
							<label><input type="checkbox" name="bump_consent_version" value="1" /> <?php esc_html_e( 'Increment the consent version on save (re-asks every visitor)', 'eurocomply-cookie-consent' ); ?></label>
							<p class="description"><?php printf( esc_html__( 'Current version: %s', 'eurocomply-cookie-consent' ), esc_html( (string) $settings['consent_version'] ) ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>
			<h2><?php esc_html_e( 'Banner text — English', 'eurocomply-cookie-consent' ); ?></h2>
			<?php $this->render_text_block( 'text_en', $settings['text_en'] ); ?>
			<h2><?php esc_html_e( 'Banner text — Deutsch', 'eurocomply-cookie-consent' ); ?></h2>
			<?php $this->render_text_block( 'text_de', $settings['text_de'] ); ?>
			<?php submit_button( __( 'Save banner settings', 'eurocomply-cookie-consent' ) ); ?>
		</form>
		<?php
	}

	private function render_text_block( string $group, array $values ) : void {
		$fields = array(
			'title'       => __( 'Headline', 'eurocomply-cookie-consent' ),
			'body'        => __( 'Body text', 'eurocomply-cookie-consent' ),
			'accept_all'  => __( 'Accept button label', 'eurocomply-cookie-consent' ),
			'reject_all'  => __( 'Reject button label', 'eurocomply-cookie-consent' ),
			'customize'   => __( 'Customise button label', 'eurocomply-cookie-consent' ),
			'save'        => __( 'Save button label', 'eurocomply-cookie-consent' ),
			'policy_link' => __( 'Policy link text', 'eurocomply-cookie-consent' ),
		);
		echo '<table class="form-table" role="presentation"><tbody>';
		foreach ( $fields as $key => $label ) {
			$value = isset( $values[ $key ] ) ? (string) $values[ $key ] : '';
			echo '<tr>';
			echo '<th scope="row"><label for="' . esc_attr( $group . '_' . $key ) . '">' . esc_html( $label ) . '</label></th>';
			echo '<td><input id="' . esc_attr( $group . '_' . $key ) . '" type="text" class="regular-text" name="' . esc_attr( $group . '[' . $key . ']' ) . '" value="' . esc_attr( $value ) . '" /></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private function render_categories_tab( array $settings ) : void {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( self::NONCE_SAVE ); ?>
			<input type="hidden" name="action" value="eurocomply_cc_save" />
			<input type="hidden" name="tab" value="categories" />
			<p><?php esc_html_e( 'Categories map visitor choices to Google Consent Mode v2 signals. “Necessary” is always on.', 'eurocomply-cookie-consent' ); ?></p>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Enabled', 'eurocomply-cookie-consent' ); ?></th>
						<th><?php esc_html_e( 'Category', 'eurocomply-cookie-consent' ); ?></th>
						<th><?php esc_html_e( 'Consent Mode v2 signals', 'eurocomply-cookie-consent' ); ?></th>
						<th><?php esc_html_e( 'Description', 'eurocomply-cookie-consent' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $settings['categories'] as $slug => $row ) : ?>
						<tr>
							<td>
								<?php if ( ! empty( $row['locked'] ) ) : ?>
									<input type="checkbox" disabled checked />
								<?php else : ?>
									<input type="checkbox" name="categories[<?php echo esc_attr( $slug ); ?>][enabled]" value="1" <?php checked( ! empty( $row['enabled'] ) ); ?> />
								<?php endif; ?>
							</td>
							<td><strong><?php echo esc_html( (string) $row['label'] ); ?></strong><br /><code><?php echo esc_html( $slug ); ?></code></td>
							<td><code><?php echo esc_html( implode( ', ', (array) ( $row['gcm'] ?? array() ) ) ); ?></code></td>
							<td><?php echo esc_html( (string) ( $row['description'] ?? '' ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php submit_button( __( 'Save category settings', 'eurocomply-cookie-consent' ) ); ?>
		</form>
		<?php
	}

	private function render_integrations_tab( array $settings ) : void {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( self::NONCE_SAVE ); ?>
			<input type="hidden" name="action" value="eurocomply_cc_save" />
			<input type="hidden" name="tab" value="integrations" />
			<h2><?php esc_html_e( 'Google Consent Mode v2', 'eurocomply-cookie-consent' ); ?></h2>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable', 'eurocomply-cookie-consent' ); ?></th>
						<td><label><input type="checkbox" name="gcm_enabled" value="1" <?php checked( ! empty( $settings['gcm_enabled'] ) ); ?> /> <?php esc_html_e( 'Emit gtag(\'consent\', \'default\', …) in the page head', 'eurocomply-cookie-consent' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Region scope', 'eurocomply-cookie-consent' ); ?></th>
						<td>
							<select name="regions">
								<option value="eea" <?php selected( $settings['regions'], 'eea' ); ?>><?php esc_html_e( 'EEA + UK + CH (recommended)', 'eurocomply-cookie-consent' ); ?></option>
								<option value="world" <?php selected( $settings['regions'], 'world' ); ?>><?php esc_html_e( 'Worldwide', 'eurocomply-cookie-consent' ); ?></option>
								<option value="off" <?php selected( $settings['regions'], 'off' ); ?>><?php esc_html_e( 'Do not set a region (Pro: geo-IP)', 'eurocomply-cookie-consent' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Privacy flags', 'eurocomply-cookie-consent' ); ?></th>
						<td>
							<label><input type="checkbox" name="gcm_ads_data_redaction" value="1" <?php checked( ! empty( $settings['gcm_ads_data_redaction'] ) ); ?> /> <code>ads_data_redaction=true</code></label><br />
							<label><input type="checkbox" name="gcm_url_passthrough" value="1" <?php checked( ! empty( $settings['gcm_url_passthrough'] ) ); ?> /> <code>url_passthrough=true</code></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="gcm_wait_for_update"><?php esc_html_e( 'wait_for_update (ms)', 'eurocomply-cookie-consent' ); ?></label></th>
						<td><input id="gcm_wait_for_update" type="number" min="0" max="5000" name="gcm_wait_for_update" value="<?php echo esc_attr( (string) $settings['gcm_wait_for_update'] ); ?>" /></td>
					</tr>
				</tbody>
			</table>
			<h2><?php esc_html_e( 'Optional tag IDs', 'eurocomply-cookie-consent' ); ?></h2>
			<p class="description"><?php esc_html_e( 'These IDs are stored for future auto-loader features. Currently the plugin only emits the Consent Mode defaults — add your own gtag / Meta / Ads snippets inside categories you block.', 'eurocomply-cookie-consent' ); ?></p>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><label for="ga4_id">GA4 Measurement ID</label></th>
						<td><input id="ga4_id" type="text" class="regular-text" name="ga4_id" value="<?php echo esc_attr( (string) $settings['ga4_id'] ); ?>" placeholder="G-XXXXXXX" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="meta_pixel_id">Meta Pixel ID</label></th>
						<td><input id="meta_pixel_id" type="text" class="regular-text" name="meta_pixel_id" value="<?php echo esc_attr( (string) $settings['meta_pixel_id'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="google_ads_id">Google Ads ID</label></th>
						<td><input id="google_ads_id" type="text" class="regular-text" name="google_ads_id" value="<?php echo esc_attr( (string) $settings['google_ads_id'] ); ?>" placeholder="AW-XXXXXXX" /></td>
					</tr>
				</tbody>
			</table>
			<?php submit_button( __( 'Save integrations', 'eurocomply-cookie-consent' ) ); ?>
		</form>
		<?php
	}

	private function render_log_tab( bool $is_pro ) : void {
		$total = ConsentLog::count();
		$rows  = ConsentLog::recent( 50 );
		?>
		<p><?php printf( esc_html__( 'Stored consent rows: %d (pseudonymised — IP + user agent are salted SHA-256 hashes).', 'eurocomply-cookie-consent' ), (int) $total ); ?></p>
		<table class="widefat striped">
			<thead>
				<tr>
					<th>#</th>
					<th><?php esc_html_e( 'Timestamp (UTC)', 'eurocomply-cookie-consent' ); ?></th>
					<th><?php esc_html_e( 'Version', 'eurocomply-cookie-consent' ); ?></th>
					<th><?php esc_html_e( 'Language', 'eurocomply-cookie-consent' ); ?></th>
					<th><?php esc_html_e( 'Granted categories', 'eurocomply-cookie-consent' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="5"><em><?php esc_html_e( 'No consent events yet.', 'eurocomply-cookie-consent' ); ?></em></td></tr>
				<?php endif; ?>
				<?php foreach ( $rows as $row ) : ?>
					<?php
					$state   = json_decode( (string) $row['state'], true );
					$granted = array();
					if ( is_array( $state ) ) {
						foreach ( $state as $cat => $ok ) {
							if ( $ok ) {
								$granted[] = $cat;
							}
						}
					}
					?>
					<tr>
						<td><?php echo (int) $row['id']; ?></td>
						<td><?php echo esc_html( (string) $row['created_at'] ); ?></td>
						<td><?php echo esc_html( (string) $row['consent_version'] ); ?></td>
						<td><?php echo esc_html( (string) $row['language'] ); ?></td>
						<td><?php echo empty( $granted ) ? '—' : esc_html( implode( ', ', $granted ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<h2><?php esc_html_e( 'Maintenance', 'eurocomply-cookie-consent' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'This permanently deletes the consent log. Continue?', 'eurocomply-cookie-consent' ) ); ?>');">
			<?php wp_nonce_field( 'eurocomply_cc_purge_log' ); ?>
			<input type="hidden" name="action" value="eurocomply_cc_purge_log" />
			<?php submit_button( __( 'Purge consent log', 'eurocomply-cookie-consent' ), 'delete', 'submit', false ); ?>
		</form>
		<h2><?php esc_html_e( 'CSV export (Pro)', 'eurocomply-cookie-consent' ); ?></h2>
		<?php if ( $is_pro ) : ?>
			<p><em><?php esc_html_e( 'CSV export endpoint will be wired up once the Pro licensing service is live. Licence detected — you are eligible.', 'eurocomply-cookie-consent' ); ?></em></p>
		<?php else : ?>
			<p><em><?php esc_html_e( 'Add a Pro licence under the License tab to unlock automated CSV export.', 'eurocomply-cookie-consent' ); ?></em></p>
		<?php endif; ?>
		<?php
	}

	private function render_license_tab( bool $is_pro ) : void {
		$key = License::get_key();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'eurocomply_cc_license' ); ?>
			<input type="hidden" name="action" value="eurocomply_cc_license" />
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><label for="eurocomply_cc_license_key"><?php esc_html_e( 'Licence key', 'eurocomply-cookie-consent' ); ?></label></th>
						<td>
							<input id="eurocomply_cc_license_key" type="text" class="regular-text" name="license_key" value="<?php echo esc_attr( $key ); ?>" placeholder="EC-XXXXXX" />
							<p class="description"><?php esc_html_e( 'Format: EC- followed by at least 6 upper-case alphanumerics. Online validation will be added in a follow-up release.', 'eurocomply-cookie-consent' ); ?></p>
							<?php if ( $is_pro ) : ?>
								<p><strong style="color:#167c3c"><?php esc_html_e( 'Pro features unlocked.', 'eurocomply-cookie-consent' ); ?></strong></p>
							<?php endif; ?>
						</td>
					</tr>
				</tbody>
			</table>
			<?php submit_button( __( 'Save licence', 'eurocomply-cookie-consent' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Handle a settings-tab POST.
	 */
	public function handle_save() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'eurocomply-cookie-consent' ) );
		}
		check_admin_referer( self::NONCE_SAVE );

		// wp_unslash() because WP adds magic slashes to $_POST regardless of the PHP config.
		$raw = isset( $_POST ) && is_array( $_POST ) ? wp_unslash( $_POST ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		Settings::save( is_array( $raw ) ? $raw : array() );

		$tab = isset( $_POST['tab'] ) ? sanitize_key( (string) $_POST['tab'] ) : 'banner';
		add_settings_error( 'eurocomply_cc', 'saved', __( 'Settings saved.', 'eurocomply-cookie-consent' ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'             => self::MENU_SLUG,
					'tab'              => $tab,
					'settings-updated' => 'true',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle the licence POST.
	 */
	public function handle_license() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'eurocomply-cookie-consent' ) );
		}
		check_admin_referer( 'eurocomply_cc_license' );
		$key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['license_key'] ) ) : '';
		License::set_key( $key );
		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => self::MENU_SLUG,
					'tab'  => 'license',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle the "purge log" POST.
	 */
	public function handle_purge_log() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'eurocomply-cookie-consent' ) );
		}
		check_admin_referer( 'eurocomply_cc_purge_log' );
		ConsentLog::truncate();
		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => self::MENU_SLUG,
					'tab'  => 'log',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
