<?php
/**
 * WordPress admin UI for EuroComply EPR Multi-Country Reporting.
 *
 * 4 tabs: Reporting Dashboard · Settings · Pro Features · License.
 *
 * @package EuroComply\Epr
 */

declare( strict_types = 1 );

namespace EuroComply\Epr;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin {

	public const MENU_SLUG  = 'eurocomply-epr';
	public const NONCE_SAVE = 'eurocomply_epr_save';

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
		add_action( 'admin_post_eurocomply_epr_save', array( $this, 'handle_save' ) );
		add_action( 'admin_post_eurocomply_epr_license', array( $this, 'handle_license' ) );
		add_action( 'admin_post_eurocomply_epr_export', array( $this, 'handle_export' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function register_menu() : void {
		add_menu_page(
			__( 'EuroComply EPR', 'eurocomply-epr' ),
			__( 'EPR', 'eurocomply-epr' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_page' ),
			'dashicons-trash',
			74
		);
	}

	public function enqueue( string $hook ) : void {
		if ( false === strpos( $hook, self::MENU_SLUG ) ) {
			return;
		}
		wp_enqueue_style(
			'eurocomply-epr-admin',
			EUROCOMPLY_EPR_URL . 'assets/css/admin.css',
			array(),
			EUROCOMPLY_EPR_VERSION
		);
	}

	public function render_page() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'eurocomply-epr' ) );
		}

		$allowed_tabs = array( 'dashboard', 'settings', 'pro', 'license' );
		$tab          = isset( $_GET['tab'] ) ? sanitize_key( (string) wp_unslash( $_GET['tab'] ) ) : 'dashboard'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $tab, $allowed_tabs, true ) ) {
			$tab = 'dashboard';
		}

		$is_pro = License::is_pro();

		echo '<div class="wrap eurocomply-epr-admin">';
		echo '<h1>' . esc_html__( 'EuroComply EPR Multi-Country Reporting', 'eurocomply-epr' ) . ' <span class="eurocomply-epr-version">v' . esc_html( EUROCOMPLY_EPR_VERSION ) . '</span></h1>';

		$this->render_tabs( $tab, $is_pro );
		settings_errors( 'eurocomply_epr' );

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
			'dashboard' => __( 'Reporting Dashboard', 'eurocomply-epr' ),
			'settings'  => __( 'Settings', 'eurocomply-epr' ),
			'pro'       => __( 'Pro Features', 'eurocomply-epr' ),
			'license'   => $is_pro ? __( 'License', 'eurocomply-epr' ) : __( 'License (Pro)', 'eurocomply-epr' ),
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
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'WooCommerce is not active. The Reporting Dashboard needs WooCommerce to scan products.', 'eurocomply-epr' ) . '</p></div>';
			return;
		}

		$settings = Settings::get();
		$enabled  = (array) $settings['enabled_countries'];

		if ( empty( $enabled ) ) {
			echo '<div class="notice notice-info inline"><p>' . esc_html__( 'No countries enabled yet. Go to the Settings tab to enable one or more EPR countries.', 'eurocomply-epr' ) . '</p></div>';
			return;
		}

		$rows    = Reporting::scan();
		$summary = Reporting::summary( $rows );

		echo '<p class="eurocomply-epr-summary">';
		printf(
			/* translators: 1: compliant count, 2: warning count, 3: error count */
			esc_html__( '%1$d products compliant · %2$d with warnings · %3$d missing required fields.', 'eurocomply-epr' ),
			(int) $summary['compliant'],
			(int) $summary['warning'],
			(int) $summary['error']
		);
		echo '</p>';

		$base_export = add_query_arg(
			array( 'action' => 'eurocomply_epr_export' ),
			admin_url( 'admin-post.php' )
		);
		$all_url = wp_nonce_url( $base_export, 'eurocomply_epr_csv', '_wpnonce' );

		echo '<p>';
		echo '<a class="button button-primary" href="' . esc_url( $all_url ) . '">' . esc_html__( 'Download CSV (all countries)', 'eurocomply-epr' ) . '</a> ';
		foreach ( $enabled as $code ) {
			$url = wp_nonce_url( add_query_arg( 'country', $code, $base_export ), 'eurocomply_epr_csv', '_wpnonce' );
			echo '<a class="button" href="' . esc_url( $url ) . '" style="margin-left:4px;">' . esc_html__( 'CSV:', 'eurocomply-epr' ) . ' ' . esc_html( $code ) . '</a>';
		}
		echo '</p>';

		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No products found.', 'eurocomply-epr' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'ID', 'eurocomply-epr' ) . '</th>';
		echo '<th>' . esc_html__( 'Product', 'eurocomply-epr' ) . '</th>';
		echo '<th>' . esc_html__( 'Packaging (g)', 'eurocomply-epr' ) . '</th>';
		foreach ( $enabled as $code ) {
			echo '<th>' . esc_html( $code ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			echo '<tr>';
			echo '<td>' . (int) $row['id'] . '</td>';
			$edit = get_edit_post_link( (int) $row['id'] );
			echo '<td>';
			if ( $edit ) {
				echo '<a href="' . esc_url( $edit ) . '">' . esc_html( (string) $row['title'] ) . '</a>';
			} else {
				echo esc_html( (string) $row['title'] );
			}
			echo '</td>';
			echo '<td>' . esc_html( (string) round( (float) $row['total_weight_g'], 2 ) ) . '</td>';
			foreach ( $enabled as $code ) {
				$c = $row['countries'][ $code ] ?? array( 'status' => Reporting::STATUS_ERROR, 'missing' => array( 'unknown' ) );
				$label = $this->status_label( (string) $c['status'] );
				echo '<td title="' . esc_attr( (string) ( $c['note'] ?? '' ) ) . '">' . esc_html( $label );
				if ( ! empty( $c['missing'] ) ) {
					echo '<br><small>' . esc_html( implode( ', ', (array) $c['missing'] ) ) . '</small>';
				}
				echo '</td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private function status_label( string $status ) : string {
		switch ( $status ) {
			case Reporting::STATUS_OK:
				return __( 'Compliant', 'eurocomply-epr' );
			case Reporting::STATUS_WARNING:
				return __( 'Warning', 'eurocomply-epr' );
			default:
				return __( 'Missing required', 'eurocomply-epr' );
		}
	}

	private function render_settings_tab( array $settings ) : void {
		$action_url = admin_url( 'admin-post.php' );
		$countries  = Countries::all();
		?>
		<form method="post" action="<?php echo esc_url( $action_url ); ?>">
			<input type="hidden" name="action" value="eurocomply_epr_save" />
			<?php wp_nonce_field( self::NONCE_SAVE ); ?>

			<h3><?php esc_html_e( 'Countries', 'eurocomply-epr' ); ?></h3>
			<table class="form-table"><tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Enabled countries', 'eurocomply-epr' ); ?></th>
					<td>
						<?php foreach ( $countries as $code => $meta ) :
							$checked = in_array( $code, (array) $settings['enabled_countries'], true );
							?>
							<label style="display:inline-block;min-width:180px;margin-bottom:4px;">
								<input type="checkbox" name="enabled_countries[]" value="<?php echo esc_attr( $code ); ?>" <?php checked( $checked ); ?> />
								<?php echo esc_html( $meta['name'] . ' (' . $code . ')' ); ?>
							</label>
						<?php endforeach; ?>
						<p class="description"><?php esc_html_e( 'Only enabled countries appear on the product metabox and dashboard.', 'eurocomply-epr' ); ?></p>
					</td>
				</tr>
			</tbody></table>

			<h3><?php esc_html_e( 'Shop-wide registration defaults', 'eurocomply-epr' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Used as fall-back when a product does not set its own per-country registration number.', 'eurocomply-epr' ); ?></p>
			<table class="form-table"><tbody>
				<tr>
					<th scope="row"><label for="inherit_defaults"><?php esc_html_e( 'Inherit defaults', 'eurocomply-epr' ); ?></label></th>
					<td>
						<label><input type="checkbox" id="inherit_defaults" name="inherit_defaults" value="1" <?php checked( ! empty( $settings['inherit_defaults'] ) ); ?> />
						<?php esc_html_e( 'Fall back to the defaults below when a product registration number is empty.', 'eurocomply-epr' ); ?></label>
					</td>
				</tr>
				<?php foreach ( $countries as $code => $meta ) : ?>
					<tr>
						<th scope="row"><label for="epr_def_<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $meta['name'] . ' — ' . $meta['reg_label'] ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="epr_def_<?php echo esc_attr( $code ); ?>" name="default_registrations[<?php echo esc_attr( $code ); ?>]" value="<?php echo esc_attr( (string) ( $settings['default_registrations'][ $code ] ?? '' ) ); ?>" placeholder="<?php echo esc_attr( $meta['reg_example'] ); ?>" />
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody></table>

			<h3><?php esc_html_e( 'Report period', 'eurocomply-epr' ); ?></h3>
			<table class="form-table"><tbody>
				<tr>
					<th scope="row"><label for="report_period"><?php esc_html_e( 'Reporting cadence', 'eurocomply-epr' ); ?></label></th>
					<td>
						<select id="report_period" name="report_period">
							<?php foreach ( array( 'monthly' => __( 'Monthly', 'eurocomply-epr' ), 'quarterly' => __( 'Quarterly', 'eurocomply-epr' ), 'yearly' => __( 'Yearly', 'eurocomply-epr' ) ) as $k => $l ) : ?>
								<option value="<?php echo esc_attr( $k ); ?>" <?php selected( (string) $settings['report_period'], $k ); ?>><?php echo esc_html( $l ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Pro tier: automated report packaging on this cadence.', 'eurocomply-epr' ); ?></p>
					</td>
				</tr>
			</tbody></table>

			<?php submit_button( __( 'Save Changes', 'eurocomply-epr' ) ); ?>
		</form>
		<?php
	}

	private function render_pro_tab( bool $is_pro ) : void {
		if ( $is_pro ) {
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Pro license active. Advanced EPR workflows will ship in the next release.', 'eurocomply-epr' ) . '</p></div>';
		} else {
			echo '<div class="notice notice-info inline"><p>' . esc_html__( 'Unlock these features with a Pro license.', 'eurocomply-epr' ) . '</p></div>';
		}

		$features = array(
			array( 'title' => __( 'Registry auto-submission', 'eurocomply-epr' ), 'desc' => __( 'Push quarterly reports to LUCID, CITEO and other registry APIs without manual uploads.', 'eurocomply-epr' ) ),
			array( 'title' => __( 'Per-registry code mapping', 'eurocomply-epr' ), 'desc' => __( 'Map your packaging materials to each registry\'s exact code set (ZSVR, CITEO, CONAI, Afvalfonds).', 'eurocomply-epr' ) ),
			array( 'title' => __( 'Batch recall workflow', 'eurocomply-epr' ), 'desc' => __( 'Track batch/lot numbers per order, trigger recall notifications to affected customers.', 'eurocomply-epr' ) ),
			array( 'title' => __( 'Multi-producer mode', 'eurocomply-epr' ), 'desc' => __( 'Separate EPR accounts for different brands or legal entities on the same WooCommerce store.', 'eurocomply-epr' ) ),
			array( 'title' => __( 'Audit-ready export archive', 'eurocomply-epr' ), 'desc' => __( 'GoBD-style 10-year immutable archive of every submitted report.', 'eurocomply-epr' ) ),
		);
		echo '<div class="eurocomply-epr-features">';
		foreach ( $features as $f ) {
			echo '<div class="eurocomply-epr-feature-card">';
			echo '<h3>' . esc_html( $f['title'] ) . '</h3>';
			echo '<p>' . esc_html( $f['desc'] ) . '</p>';
			echo '</div>';
		}
		echo '</div>';
	}

	private function render_license_tab( bool $is_pro ) : void {
		$action_url = admin_url( 'admin-post.php' );
		$data       = License::get();
		$status     = $is_pro ? __( 'Active (Pro)', 'eurocomply-epr' ) : __( 'Free tier', 'eurocomply-epr' );
		?>
		<p><strong><?php esc_html_e( 'Status:', 'eurocomply-epr' ); ?></strong> <?php echo esc_html( $status ); ?></p>
		<?php if ( $is_pro ) : ?>
			<form method="post" action="<?php echo esc_url( $action_url ); ?>">
				<input type="hidden" name="action" value="eurocomply_epr_license" />
				<input type="hidden" name="mode" value="deactivate" />
				<?php wp_nonce_field( 'eurocomply_epr_license' ); ?>
				<?php submit_button( __( 'Deactivate', 'eurocomply-epr' ), 'secondary' ); ?>
			</form>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( $action_url ); ?>">
				<input type="hidden" name="action" value="eurocomply_epr_license" />
				<input type="hidden" name="mode" value="activate" />
				<?php wp_nonce_field( 'eurocomply_epr_license' ); ?>
				<p>
					<label for="eurocomply_epr_license_key"><?php esc_html_e( 'License key', 'eurocomply-epr' ); ?></label><br />
					<input type="text" id="eurocomply_epr_license_key" name="license_key" class="regular-text" placeholder="EC-XXXXXX" />
				</p>
				<?php submit_button( __( 'Activate license', 'eurocomply-epr' ) ); ?>
			</form>
		<?php endif; ?>
		<?php
	}

	public function handle_save() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-epr' ) );
		}
		check_admin_referer( self::NONCE_SAVE );
		Settings::save( wp_unslash( $_POST ) );
		add_settings_error( 'eurocomply_epr', 'saved', __( 'Settings saved.', 'eurocomply-epr' ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'settings', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_license() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-epr' ) );
		}
		check_admin_referer( 'eurocomply_epr_license' );

		$mode = isset( $_POST['mode'] ) ? sanitize_key( (string) wp_unslash( $_POST['mode'] ) ) : '';
		if ( 'deactivate' === $mode ) {
			License::deactivate();
			add_settings_error( 'eurocomply_epr', 'deactivated', __( 'License deactivated.', 'eurocomply-epr' ), 'updated' );
		} else {
			$key    = isset( $_POST['license_key'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['license_key'] ) ) : '';
			$result = License::activate( $key );
			add_settings_error(
				'eurocomply_epr',
				$result['ok'] ? 'activated' : 'invalid',
				(string) $result['message'],
				$result['ok'] ? 'updated' : 'error'
			);
		}
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'license', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_export() : void {
		CsvExport::handle();
	}
}
