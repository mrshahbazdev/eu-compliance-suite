<?php
/**
 * WordPress admin UI for EuroComply EU VAT & OSS.
 *
 * 5 tabs: Settings · VAT Rates · VIES Test · Transactions · License.
 *
 * @package EuroComply\VatOss
 */

declare( strict_types = 1 );

namespace EuroComply\VatOss;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin {

	public const MENU_SLUG  = 'eurocomply-vat-oss';
	public const NONCE_SAVE = 'eurocomply_vat_save';

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
		add_action( 'admin_post_eurocomply_vat_save', array( $this, 'handle_save' ) );
		add_action( 'admin_post_eurocomply_vat_license', array( $this, 'handle_license' ) );
		add_action( 'admin_post_eurocomply_vat_vies_test', array( $this, 'handle_vies_test' ) );
		add_action( 'admin_post_eurocomply_vat_purge_log', array( $this, 'handle_purge_log' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function register_menu() : void {
		add_menu_page(
			__( 'EuroComply VAT & OSS', 'eurocomply-vat-oss' ),
			__( 'VAT & OSS', 'eurocomply-vat-oss' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_page' ),
			'dashicons-money-alt',
			72
		);
	}

	public function enqueue( string $hook ) : void {
		if ( false === strpos( $hook, self::MENU_SLUG ) ) {
			return;
		}
		wp_enqueue_style(
			'eurocomply-vat-admin',
			EUROCOMPLY_VAT_URL . 'assets/css/admin.css',
			array(),
			EUROCOMPLY_VAT_VERSION
		);
	}

	public function render_page() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'eurocomply-vat-oss' ) );
		}

		$allowed_tabs = array( 'settings', 'rates', 'vies', 'log', 'license' );
		$tab          = isset( $_GET['tab'] ) ? sanitize_key( (string) wp_unslash( $_GET['tab'] ) ) : 'settings'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $tab, $allowed_tabs, true ) ) {
			$tab = 'settings';
		}

		$settings = Settings::get();
		$is_pro   = License::is_pro();

		echo '<div class="wrap eurocomply-vat-admin">';
		echo '<h1>' . esc_html__( 'EuroComply VAT & OSS', 'eurocomply-vat-oss' ) . ' <span class="eurocomply-vat-version">v' . esc_html( EUROCOMPLY_VAT_VERSION ) . '</span></h1>';

		$this->render_tabs( $tab, $is_pro );

		settings_errors( 'eurocomply_vat' );

		switch ( $tab ) {
			case 'rates':
				$this->render_rates_tab();
				break;
			case 'vies':
				$this->render_vies_tab();
				break;
			case 'log':
				$this->render_log_tab( $is_pro );
				break;
			case 'license':
				$this->render_license_tab( $is_pro );
				break;
			case 'settings':
			default:
				$this->render_settings_tab( $settings );
				break;
		}
		echo '</div>';
	}

	private function render_tabs( string $active, bool $is_pro ) : void {
		$tabs = array(
			'settings' => __( 'Settings', 'eurocomply-vat-oss' ),
			'rates'    => __( 'VAT Rates', 'eurocomply-vat-oss' ),
			'vies'     => __( 'VIES Test', 'eurocomply-vat-oss' ),
			'log'      => __( 'Transactions', 'eurocomply-vat-oss' ),
			'license'  => $is_pro ? __( 'License', 'eurocomply-vat-oss' ) : __( 'License (Pro)', 'eurocomply-vat-oss' ),
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

	private function render_settings_tab( array $settings ) : void {
		$rates = Rates::all();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( self::NONCE_SAVE ); ?>
			<input type="hidden" name="action" value="eurocomply_vat_save" />
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><label for="shop_country"><?php esc_html_e( 'Shop country', 'eurocomply-vat-oss' ); ?></label></th>
						<td>
							<select id="shop_country" name="shop_country">
								<?php foreach ( Rates::EU27 as $iso ) : ?>
									<option value="<?php echo esc_attr( $iso ); ?>" <?php selected( $settings['shop_country'], $iso ); ?>>
										<?php echo esc_html( $iso . ' — ' . ( $rates[ $iso ]['name'] ?? $iso ) ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Country where your business is registered for VAT.', 'eurocomply-vat-oss' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'B2B reverse charge', 'eurocomply-vat-oss' ); ?></th>
						<td>
							<label><input type="checkbox" name="reverse_charge_b2b" value="1" <?php checked( $settings['reverse_charge_b2b'], '1' ); ?>> <?php esc_html_e( 'Zero VAT for cross-border B2B sales with a valid EU VAT number.', 'eurocomply-vat-oss' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Validate via VIES', 'eurocomply-vat-oss' ); ?></th>
						<td>
							<label><input type="checkbox" name="validate_via_vies" value="1" <?php checked( $settings['validate_via_vies'], '1' ); ?>> <?php esc_html_e( 'Hit the European Commission VIES endpoint during checkout.', 'eurocomply-vat-oss' ); ?></label>
							<p class="description"><?php esc_html_e( 'Disable only if VIES is unreachable from your server. Local format checks always run.', 'eurocomply-vat-oss' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="vies_timeout"><?php esc_html_e( 'VIES timeout (seconds)', 'eurocomply-vat-oss' ); ?></label></th>
						<td>
							<input type="number" id="vies_timeout" name="vies_timeout" min="2" max="30" value="<?php echo esc_attr( (string) $settings['vies_timeout'] ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'OSS destination tax', 'eurocomply-vat-oss' ); ?></th>
						<td>
							<label><input type="checkbox" name="oss_enabled" value="1" <?php checked( $settings['oss_enabled'], '1' ); ?>> <?php esc_html_e( 'Use the buyer-country VAT rate for B2C cross-border EU sales (One-Stop Shop).', 'eurocomply-vat-oss' ); ?></label>
							<p class="description"><?php esc_html_e( 'Required by merchants exceeding €10,000 EU cross-border B2C turnover per year.', 'eurocomply-vat-oss' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Require VAT for B2B', 'eurocomply-vat-oss' ); ?></th>
						<td>
							<label><input type="checkbox" name="require_vat_for_b2b" value="1" <?php checked( $settings['require_vat_for_b2b'], '1' ); ?>> <?php esc_html_e( 'Block checkout for customers who entered a company name but no VAT number.', 'eurocomply-vat-oss' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Show rates in cart', 'eurocomply-vat-oss' ); ?></th>
						<td>
							<label><input type="checkbox" name="show_rates_in_cart" value="1" <?php checked( $settings['show_rates_in_cart'], '1' ); ?>> <?php esc_html_e( 'Hint applied VAT rate + country in the cart totals block.', 'eurocomply-vat-oss' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="checkout_label_en"><?php esc_html_e( 'Checkout label (EN)', 'eurocomply-vat-oss' ); ?></label></th>
						<td><input type="text" id="checkout_label_en" name="checkout_label_en" class="regular-text" value="<?php echo esc_attr( (string) $settings['checkout_label_en'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="checkout_label_de"><?php esc_html_e( 'Checkout label (DE)', 'eurocomply-vat-oss' ); ?></label></th>
						<td><input type="text" id="checkout_label_de" name="checkout_label_de" class="regular-text" value="<?php echo esc_attr( (string) $settings['checkout_label_de'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="checkout_help_en"><?php esc_html_e( 'Helper text (EN)', 'eurocomply-vat-oss' ); ?></label></th>
						<td><textarea id="checkout_help_en" name="checkout_help_en" rows="2" class="large-text"><?php echo esc_textarea( (string) $settings['checkout_help_en'] ); ?></textarea></td>
					</tr>
					<tr>
						<th scope="row"><label for="checkout_help_de"><?php esc_html_e( 'Helper text (DE)', 'eurocomply-vat-oss' ); ?></label></th>
						<td><textarea id="checkout_help_de" name="checkout_help_de" rows="2" class="large-text"><?php echo esc_textarea( (string) $settings['checkout_help_de'] ); ?></textarea></td>
					</tr>
				</tbody>
			</table>
			<?php submit_button( __( 'Save settings', 'eurocomply-vat-oss' ) ); ?>
		</form>
		<?php
	}

	private function render_rates_tab() : void {
		$rates = Rates::all();
		?>
		<h2><?php esc_html_e( 'EU-27 standard VAT rates', 'eurocomply-vat-oss' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Data source: European Commission · Taxes in Europe Database v3 (TEDB). Reduced, super-reduced and parking rates ship in the Pro tier.', 'eurocomply-vat-oss' ); ?>
		</p>
		<table class="wp-list-table widefat fixed striped eurocomply-vat-rates">
			<thead>
				<tr>
					<th style="width:80px;"><?php esc_html_e( 'ISO', 'eurocomply-vat-oss' ); ?></th>
					<th><?php esc_html_e( 'Country', 'eurocomply-vat-oss' ); ?></th>
					<th style="width:120px;"><?php esc_html_e( 'Standard', 'eurocomply-vat-oss' ); ?></th>
					<th><?php esc_html_e( 'Reduced rates', 'eurocomply-vat-oss' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( Rates::EU27 as $iso ) :
					$row      = $rates[ $iso ] ?? array();
					$standard = isset( $row['standard'] ) ? number_format_i18n( (float) $row['standard'], 1 ) : '—';
					$reduced  = isset( $row['reduced'] ) && is_array( $row['reduced'] ) ? $row['reduced'] : array();
					?>
					<tr>
						<td><code><?php echo esc_html( $iso ); ?></code></td>
						<td><?php echo esc_html( (string) ( $row['name'] ?? $iso ) ); ?></td>
						<td><strong><?php echo esc_html( $standard ); ?>%</strong></td>
						<td>
							<?php
							if ( empty( $reduced ) ) {
								echo '<em>' . esc_html__( 'none', 'eurocomply-vat-oss' ) . '</em>';
							} else {
								$parts = array();
								foreach ( $reduced as $r ) {
									$parts[] = number_format_i18n( (float) $r, 1 ) . '%';
								}
								echo esc_html( implode( ', ', $parts ) );
							}
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private function render_vies_tab() : void {
		$last = get_transient( 'eurocomply_vat_last_check' );
		?>
		<h2><?php esc_html_e( 'Test a VAT number against VIES', 'eurocomply-vat-oss' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Useful to confirm VIES reachability from this server and to sanity-check customer VAT numbers.', 'eurocomply-vat-oss' ); ?>
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( self::NONCE_SAVE ); ?>
			<input type="hidden" name="action" value="eurocomply_vat_vies_test" />
			<p>
				<input type="text" name="vat" placeholder="DE123456789" class="regular-text" value="<?php echo esc_attr( (string) ( $last['input'] ?? '' ) ); ?>">
				<?php submit_button( __( 'Check', 'eurocomply-vat-oss' ), 'secondary', 'submit', false ); ?>
			</p>
		</form>
		<?php if ( is_array( $last ) && ! empty( $last['input'] ) ) : ?>
			<h3><?php esc_html_e( 'Last check', 'eurocomply-vat-oss' ); ?></h3>
			<table class="widefat striped" style="max-width:560px;">
				<tbody>
					<tr><th><?php esc_html_e( 'Input', 'eurocomply-vat-oss' ); ?></th><td><code><?php echo esc_html( (string) $last['input'] ); ?></code></td></tr>
					<tr><th><?php esc_html_e( 'Valid', 'eurocomply-vat-oss' ); ?></th><td><?php echo $last['valid'] ? '<strong style="color:#14532d;">yes</strong>' : '<strong style="color:#991b1b;">no</strong>'; ?></td></tr>
					<tr><th><?php esc_html_e( 'Source', 'eurocomply-vat-oss' ); ?></th><td><code><?php echo esc_html( (string) ( $last['source'] ?? '' ) ); ?></code></td></tr>
					<tr><th><?php esc_html_e( 'Name', 'eurocomply-vat-oss' ); ?></th><td><?php echo esc_html( (string) ( $last['name'] ?? '' ) ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Address', 'eurocomply-vat-oss' ); ?></th><td><?php echo esc_html( (string) ( $last['address'] ?? '' ) ); ?></td></tr>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
	}

	private function render_log_tab( bool $is_pro ) : void {
		$rows  = TaxLog::recent( 50 );
		$total = TaxLog::count();
		?>
		<h2><?php esc_html_e( 'Transaction log', 'eurocomply-vat-oss' ); ?></h2>
		<p class="description">
			<?php
			printf(
				/* translators: %d: number of rows */
				esc_html__( 'Stored events: %d (showing up to 50 most recent).', 'eurocomply-vat-oss' ),
				(int) $total
			);
			?>
		</p>
		<?php if ( empty( $rows ) ) : ?>
			<p><em><?php esc_html_e( 'No events yet.', 'eurocomply-vat-oss' ); ?></em></p>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'When (UTC)', 'eurocomply-vat-oss' ); ?></th>
						<th><?php esc_html_e( 'Event', 'eurocomply-vat-oss' ); ?></th>
						<th><?php esc_html_e( 'Order', 'eurocomply-vat-oss' ); ?></th>
						<th><?php esc_html_e( 'Buyer → Shop', 'eurocomply-vat-oss' ); ?></th>
						<th><?php esc_html_e( 'VAT', 'eurocomply-vat-oss' ); ?></th>
						<th><?php esc_html_e( 'Valid', 'eurocomply-vat-oss' ); ?></th>
						<th><?php esc_html_e( 'Reverse charge', 'eurocomply-vat-oss' ); ?></th>
						<th><?php esc_html_e( 'Source', 'eurocomply-vat-oss' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td><?php echo esc_html( (string) ( $row['created_at'] ?? '' ) ); ?></td>
							<td><code><?php echo esc_html( (string) ( $row['event'] ?? '' ) ); ?></code></td>
							<td><?php echo $row['order_id'] ? esc_html( (string) $row['order_id'] ) : '—'; ?></td>
							<td><?php echo esc_html( (string) ( $row['buyer_country'] ?? '—' ) . ' → ' . (string) ( $row['shop_country'] ?? '—' ) ); ?></td>
							<td><?php echo $row['vat_prefix'] ? '<code>' . esc_html( (string) $row['vat_prefix'] . (string) $row['vat_number'] ) . '</code>' : '—'; ?></td>
							<td><?php echo (int) $row['vat_valid'] ? '✓' : '—'; ?></td>
							<td><?php echo (int) $row['reverse_charge'] ? '✓' : '—'; ?></td>
							<td><code><?php echo esc_html( (string) ( $row['vies_source'] ?? '' ) ); ?></code></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Permanently delete all log rows?', 'eurocomply-vat-oss' ) ); ?>');">
				<?php wp_nonce_field( self::NONCE_SAVE ); ?>
				<input type="hidden" name="action" value="eurocomply_vat_purge_log" />
				<?php submit_button( __( 'Purge log', 'eurocomply-vat-oss' ), 'delete', 'submit', false ); ?>
			</form>
		<?php endif; ?>
		<?php if ( ! $is_pro ) : ?>
			<p class="eurocomply-vat-pro-hint">
				<?php esc_html_e( 'Pro tier adds CSV export, per-country filters and an automated MOSS/OSS quarterly report.', 'eurocomply-vat-oss' ); ?>
			</p>
		<?php endif; ?>
		<?php
	}

	private function render_license_tab( bool $is_pro ) : void {
		$current = License::get_key();
		?>
		<h2><?php esc_html_e( 'License', 'eurocomply-vat-oss' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Enter an EC-XXXXXX Pro key to unlock bulk VIES, CSV export, per-customer overrides and the OSS report builder.', 'eurocomply-vat-oss' ); ?>
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( self::NONCE_SAVE ); ?>
			<input type="hidden" name="action" value="eurocomply_vat_license" />
			<p>
				<input type="text" name="license_key" class="regular-text" placeholder="EC-XXXXXX" value="<?php echo esc_attr( $current ); ?>">
				<?php submit_button( __( 'Save key', 'eurocomply-vat-oss' ), 'primary', 'submit', false ); ?>
			</p>
			<p>
				<?php
				if ( $is_pro ) {
					echo '<strong style="color:#14532d;">' . esc_html__( 'Pro active.', 'eurocomply-vat-oss' ) . '</strong>';
				} else {
					echo '<em>' . esc_html__( 'Free tier.', 'eurocomply-vat-oss' ) . '</em>';
				}
				?>
			</p>
		</form>
		<?php
	}

	public function handle_save() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Nope.', 'eurocomply-vat-oss' ) );
		}
		check_admin_referer( self::NONCE_SAVE );

		Settings::save( is_array( $_POST ) ? wp_unslash( $_POST ) : array() ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		add_settings_error( 'eurocomply_vat', 'saved', __( 'Settings saved.', 'eurocomply-vat-oss' ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );

		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'settings', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_license() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Nope.', 'eurocomply-vat-oss' ) );
		}
		check_admin_referer( self::NONCE_SAVE );

		$raw     = isset( $_POST['license_key'] ) ? (string) wp_unslash( $_POST['license_key'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$saved   = License::set_key( $raw );
		$message = '' !== $saved
			? __( 'License key saved.', 'eurocomply-vat-oss' )
			: __( 'That does not look like a valid EC- key.', 'eurocomply-vat-oss' );
		$type    = '' !== $saved ? 'updated' : 'error';
		add_settings_error( 'eurocomply_vat', 'license', $message, $type );

		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'license' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_vies_test() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Nope.', 'eurocomply-vat-oss' ) );
		}
		check_admin_referer( self::NONCE_SAVE );

		$vat      = isset( $_POST['vat'] ) ? Vies::normalise( sanitize_text_field( (string) wp_unslash( $_POST['vat'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$settings = Settings::get();
		$result   = '' !== $vat ? Vies::validate( $vat, (int) ( $settings['vies_timeout'] ?? 8 ) ) : array(
			'input'   => '',
			'valid'   => false,
			'name'    => '',
			'address' => '',
			'source'  => '',
		);
		$result['input'] = $vat;

		set_transient( 'eurocomply_vat_last_check', $result, 5 * MINUTE_IN_SECONDS );

		if ( '' !== $vat ) {
			TaxLog::insert(
				array(
					'event'         => 'admin_vies_test',
					'vat_prefix'    => (string) ( $result['prefix'] ?? '' ),
					'vat_number'    => (string) ( $result['number'] ?? '' ),
					'vat_valid'     => ! empty( $result['valid'] ) ? 1 : 0,
					'vies_source'   => (string) ( $result['source'] ?? '' ),
					'vies_name'     => substr( (string) ( $result['name'] ?? '' ), 0, 250 ),
					'shop_country'  => (string) ( $settings['shop_country'] ?? '' ),
				)
			);
		}

		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'vies' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_purge_log() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Nope.', 'eurocomply-vat-oss' ) );
		}
		check_admin_referer( self::NONCE_SAVE );

		$deleted = TaxLog::purge();
		add_settings_error(
			'eurocomply_vat',
			'purged',
			sprintf(
				/* translators: %d: number of rows deleted */
				__( 'Purged %d log rows.', 'eurocomply-vat-oss' ),
				$deleted
			),
			'updated'
		);

		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'log' ), admin_url( 'admin.php' ) ) );
		exit;
	}
}
