<?php
/**
 * WordPress admin UI for EuroComply GPSR Compliance Manager.
 *
 * 4 tabs: Compliance Dashboard · Settings · Pro Features · License.
 *
 * @package EuroComply\Gpsr
 */

declare( strict_types = 1 );

namespace EuroComply\Gpsr;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin {

	public const MENU_SLUG  = 'eurocomply-gpsr';
	public const NONCE_SAVE = 'eurocomply_gpsr_save';

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
		add_action( 'admin_post_eurocomply_gpsr_save', array( $this, 'handle_save' ) );
		add_action( 'admin_post_eurocomply_gpsr_license', array( $this, 'handle_license' ) );
		add_action( 'admin_post_eurocomply_gpsr_export', array( $this, 'handle_export' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function register_menu() : void {
		add_menu_page(
			__( 'EuroComply GPSR', 'eurocomply-gpsr' ),
			__( 'GPSR', 'eurocomply-gpsr' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_page' ),
			'dashicons-shield',
			73
		);
	}

	public function enqueue( string $hook ) : void {
		if ( false === strpos( $hook, self::MENU_SLUG ) ) {
			return;
		}
		wp_enqueue_style(
			'eurocomply-gpsr-admin',
			EUROCOMPLY_GPSR_URL . 'assets/css/admin.css',
			array(),
			EUROCOMPLY_GPSR_VERSION
		);
	}

	public function render_page() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'eurocomply-gpsr' ) );
		}

		$allowed_tabs = array( 'dashboard', 'settings', 'pro', 'license' );
		$tab          = isset( $_GET['tab'] ) ? sanitize_key( (string) wp_unslash( $_GET['tab'] ) ) : 'dashboard'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $tab, $allowed_tabs, true ) ) {
			$tab = 'dashboard';
		}

		$is_pro = License::is_pro();

		echo '<div class="wrap eurocomply-gpsr-admin">';
		echo '<h1>' . esc_html__( 'EuroComply GPSR Compliance Manager', 'eurocomply-gpsr' ) . ' <span class="eurocomply-gpsr-version">v' . esc_html( EUROCOMPLY_GPSR_VERSION ) . '</span></h1>';

		$this->render_tabs( $tab, $is_pro );
		settings_errors( 'eurocomply_gpsr' );

		switch ( $tab ) {
			case 'settings':
				$this->render_settings_tab( Settings::get() );
				break;
			case 'pro':
				$this->render_pro_tab( $is_pro );
				break;
			case 'license':
				$this->render_license_tab( $is_pro );
				break;
			case 'dashboard':
			default:
				$this->render_dashboard_tab();
				break;
		}
		echo '</div>';
	}

	private function render_tabs( string $active, bool $is_pro ) : void {
		$tabs = array(
			'dashboard' => __( 'Compliance Dashboard', 'eurocomply-gpsr' ),
			'settings'  => __( 'Settings', 'eurocomply-gpsr' ),
			'pro'       => __( 'Pro Features', 'eurocomply-gpsr' ),
			'license'   => $is_pro ? __( 'License', 'eurocomply-gpsr' ) : __( 'License (Pro)', 'eurocomply-gpsr' ),
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

	private function render_dashboard_tab() : void {
		if ( ! class_exists( '\\WooCommerce' ) && ! function_exists( 'WC' ) ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'WooCommerce is not active. The Compliance Dashboard needs WooCommerce to scan products.', 'eurocomply-gpsr' ) . '</p></div>';
			return;
		}

		$scan   = Compliance::scan( 500 );
		$counts = $scan['counts'];
		$rows   = $scan['rows'];

		$export_url = wp_nonce_url(
			add_query_arg(
				array( 'action' => 'eurocomply_gpsr_export' ),
				admin_url( 'admin-post.php' )
			),
			'eurocomply_gpsr_export'
		);

		echo '<p class="eurocomply-gpsr-summary">';
		printf(
			/* translators: 1: OK count, 2: warning count, 3: error count */
			esc_html__( '%1$d products compliant · %2$d with warnings · %3$d missing required fields.', 'eurocomply-gpsr' ),
			(int) $counts[ Compliance::STATUS_OK ],
			(int) $counts[ Compliance::STATUS_WARNING ],
			(int) $counts[ Compliance::STATUS_ERROR ]
		);
		echo ' <a class="button" href="' . esc_url( $export_url ) . '">' . esc_html__( 'Download CSV', 'eurocomply-gpsr' ) . '</a>';
		echo '</p>';

		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No products found.', 'eurocomply-gpsr' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'ID', 'eurocomply-gpsr' ) . '</th>';
		echo '<th>' . esc_html__( 'Product', 'eurocomply-gpsr' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'eurocomply-gpsr' ) . '</th>';
		echo '<th>' . esc_html__( 'Missing required', 'eurocomply-gpsr' ) . '</th>';
		echo '<th>' . esc_html__( 'Missing recommended', 'eurocomply-gpsr' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$edit_url       = (string) get_edit_post_link( (int) $row['id'] );
			$status_label   = $this->status_label( (string) $row['status'] );
			$missing_req    = array_map( array( Compliance::class, 'label_for' ), (array) $row['missing_required'] );
			$missing_recom  = array_map( array( Compliance::class, 'label_for' ), (array) $row['missing_recommended'] );

			echo '<tr>';
			echo '<td>' . (int) $row['id'] . '</td>';
			echo '<td><a href="' . esc_url( $edit_url ) . '">' . esc_html( (string) $row['title'] ) . '</a></td>';
			echo '<td><span class="eurocomply-gpsr-status eurocomply-gpsr-status--' . esc_attr( (string) $row['status'] ) . '">' . esc_html( $status_label ) . '</span></td>';
			echo '<td>' . esc_html( implode( ', ', $missing_req ) ) . '</td>';
			echo '<td>' . esc_html( implode( ', ', $missing_recom ) ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private function status_label( string $status ) : string {
		switch ( $status ) {
			case Compliance::STATUS_OK:
				return __( 'OK', 'eurocomply-gpsr' );
			case Compliance::STATUS_WARNING:
				return __( 'Warning', 'eurocomply-gpsr' );
			case Compliance::STATUS_ERROR:
				return __( 'Missing required', 'eurocomply-gpsr' );
		}
		return $status;
	}

	private function render_settings_tab( array $settings ) : void {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( self::NONCE_SAVE ); ?>
			<input type="hidden" name="action" value="eurocomply_gpsr_save" />
			<h2><?php esc_html_e( 'Frontend display', 'eurocomply-gpsr' ); ?></h2>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Render on product pages', 'eurocomply-gpsr' ); ?></th>
						<td>
							<label><input type="checkbox" name="render_frontend" value="1" <?php checked( ! empty( $settings['render_frontend'] ) ); ?> />
								<?php esc_html_e( 'Automatically display the GPSR safety block on single-product pages.', 'eurocomply-gpsr' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'You can also use the [eurocomply_gpsr] shortcode for custom placement.', 'eurocomply-gpsr' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="frontend_heading"><?php esc_html_e( 'Block heading', 'eurocomply-gpsr' ); ?></label></th>
						<td>
							<input type="text" id="frontend_heading" name="frontend_heading" class="regular-text" value="<?php echo esc_attr( (string) $settings['frontend_heading'] ); ?>" />
						</td>
					</tr>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Shop-wide defaults', 'eurocomply-gpsr' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Used as fall-back when a product does not have its own value. Useful when you are the manufacturer for every product.', 'eurocomply-gpsr' ); ?></p>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Inherit defaults', 'eurocomply-gpsr' ); ?></th>
						<td>
							<label><input type="checkbox" name="inherit_defaults" value="1" <?php checked( ! empty( $settings['inherit_defaults'] ) ); ?> />
								<?php esc_html_e( 'Fall back to the defaults below when a product field is empty.', 'eurocomply-gpsr' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="default_manufacturer_name"><?php esc_html_e( 'Default manufacturer name', 'eurocomply-gpsr' ); ?></label></th>
						<td><input type="text" id="default_manufacturer_name" name="default_manufacturer_name" class="regular-text" value="<?php echo esc_attr( (string) $settings['default_manufacturer_name'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="default_manufacturer_address"><?php esc_html_e( 'Default manufacturer address', 'eurocomply-gpsr' ); ?></label></th>
						<td><textarea id="default_manufacturer_address" name="default_manufacturer_address" rows="3" class="large-text"><?php echo esc_textarea( (string) $settings['default_manufacturer_address'] ); ?></textarea></td>
					</tr>
					<tr>
						<th scope="row"><label for="default_importer_name"><?php esc_html_e( 'Default importer name', 'eurocomply-gpsr' ); ?></label></th>
						<td><input type="text" id="default_importer_name" name="default_importer_name" class="regular-text" value="<?php echo esc_attr( (string) $settings['default_importer_name'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="default_importer_address"><?php esc_html_e( 'Default importer address', 'eurocomply-gpsr' ); ?></label></th>
						<td><textarea id="default_importer_address" name="default_importer_address" rows="3" class="large-text"><?php echo esc_textarea( (string) $settings['default_importer_address'] ); ?></textarea></td>
					</tr>
					<tr>
						<th scope="row"><label for="default_eu_rep_name"><?php esc_html_e( 'Default EU Responsible Person', 'eurocomply-gpsr' ); ?></label></th>
						<td><input type="text" id="default_eu_rep_name" name="default_eu_rep_name" class="regular-text" value="<?php echo esc_attr( (string) $settings['default_eu_rep_name'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="default_eu_rep_address"><?php esc_html_e( 'Default EU-Rep address', 'eurocomply-gpsr' ); ?></label></th>
						<td><textarea id="default_eu_rep_address" name="default_eu_rep_address" rows="3" class="large-text"><?php echo esc_textarea( (string) $settings['default_eu_rep_address'] ); ?></textarea></td>
					</tr>
				</tbody>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	private function render_pro_tab( bool $is_pro ) : void {
		$features = array(
			array(
				'title' => __( 'AI-fill from product description', 'eurocomply-gpsr' ),
				'desc'  => __( 'Auto-extract warnings, age limits and safety instructions from your existing product copy.', 'eurocomply-gpsr' ),
			),
			array(
				'title' => __( 'EU Responsible Person marketplace', 'eurocomply-gpsr' ),
				'desc'  => __( 'One-click lookup of authorised EU representatives (Amazon EU-Rep, local auth-rep services).', 'eurocomply-gpsr' ),
			),
			array(
				'title' => __( 'Auto geo-block non-compliant products', 'eurocomply-gpsr' ),
				'desc'  => __( 'Automatically hide non-compliant products from EU visitors until fields are filled.', 'eurocomply-gpsr' ),
			),
			array(
				'title' => __( 'Bulk CSV import with validation', 'eurocomply-gpsr' ),
				'desc'  => __( 'Upload manufacturer / importer / EU-Rep data for thousands of SKUs with schema validation.', 'eurocomply-gpsr' ),
			),
			array(
				'title' => __( 'Incident & recall workflow', 'eurocomply-gpsr' ),
				'desc'  => __( 'Log safety incidents, notify customers by batch/lot, and produce recall evidence for authorities.', 'eurocomply-gpsr' ),
			),
		);

		echo '<div class="eurocomply-gpsr-pro">';
		if ( ! $is_pro ) {
			echo '<div class="notice notice-info inline"><p>' . esc_html__( 'Unlock these features with a Pro license. Enter your key on the License tab.', 'eurocomply-gpsr' ) . '</p></div>';
		} else {
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Pro license active. Advanced workflows will ship in the next release.', 'eurocomply-gpsr' ) . '</p></div>';
		}
		echo '<ul class="eurocomply-gpsr-pro__list">';
		foreach ( $features as $feature ) {
			echo '<li><strong>' . esc_html( $feature['title'] ) . '</strong><br /><span>' . esc_html( $feature['desc'] ) . '</span></li>';
		}
		echo '</ul>';
		echo '</div>';
	}

	private function render_license_tab( bool $is_pro ) : void {
		$data = License::get();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'eurocomply_gpsr_license' ); ?>
			<input type="hidden" name="action" value="eurocomply_gpsr_license" />
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><label for="eurocomply_gpsr_license_key"><?php esc_html_e( 'License key', 'eurocomply-gpsr' ); ?></label></th>
						<td>
							<input type="text" id="eurocomply_gpsr_license_key" name="license_key" class="regular-text" value="<?php echo esc_attr( (string) ( $data['key'] ?? '' ) ); ?>" placeholder="EC-XXXXXX" />
							<p class="description"><?php esc_html_e( 'Enter your EuroComply Pro key. Format: EC- followed by 6+ characters.', 'eurocomply-gpsr' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Status', 'eurocomply-gpsr' ); ?></th>
						<td><strong><?php echo $is_pro ? esc_html__( 'Active (Pro)', 'eurocomply-gpsr' ) : esc_html__( 'Free tier', 'eurocomply-gpsr' ); ?></strong></td>
					</tr>
				</tbody>
			</table>
			<?php submit_button( $is_pro ? __( 'Update license', 'eurocomply-gpsr' ) : __( 'Activate license', 'eurocomply-gpsr' ), 'primary', 'activate' ); ?>
			<?php if ( $is_pro ) : ?>
				<?php submit_button( __( 'Deactivate', 'eurocomply-gpsr' ), 'delete', 'deactivate', false ); ?>
			<?php endif; ?>
		</form>
		<?php
	}

	public function handle_save() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-gpsr' ) );
		}
		check_admin_referer( self::NONCE_SAVE );

		$input = is_array( $_POST ) ? wp_unslash( $_POST ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in Settings::save.
		Settings::save( $input );

		add_settings_error( 'eurocomply_gpsr', 'saved', __( 'Settings saved.', 'eurocomply-gpsr' ), 'updated' );
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
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-gpsr' ) );
		}
		check_admin_referer( 'eurocomply_gpsr_license' );

		if ( isset( $_POST['deactivate'] ) ) {
			License::deactivate();
			add_settings_error( 'eurocomply_gpsr', 'deactivated', __( 'License deactivated.', 'eurocomply-gpsr' ), 'updated' );
		} else {
			$key    = isset( $_POST['license_key'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['license_key'] ) ) : '';
			$result = License::activate( $key );
			add_settings_error( 'eurocomply_gpsr', 'license', $result['message'], $result['ok'] ? 'updated' : 'error' );
		}

		set_transient( 'settings_errors', get_settings_errors(), 30 );
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

	public function handle_export() : void {
		check_admin_referer( 'eurocomply_gpsr_export' );
		CsvExport::stream();
	}
}
