<?php
/**
 * WordPress admin UI for EuroComply Omnibus.
 *
 * 5 tabs: Dashboard · Settings · Price History · Pro Features · License.
 *
 * @package EuroComply\Omnibus
 */

declare( strict_types = 1 );

namespace EuroComply\Omnibus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin {

	public const MENU_SLUG      = 'eurocomply-omnibus';
	public const NONCE_SAVE     = 'eurocomply_omnibus_save';
	public const NONCE_LICENSE  = 'eurocomply_omnibus_license';
	public const NONCE_BACKFILL = 'eurocomply_omnibus_backfill';

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
		add_action( 'admin_post_eurocomply_omnibus_save', array( $this, 'handle_save' ) );
		add_action( 'admin_post_eurocomply_omnibus_license', array( $this, 'handle_license' ) );
		add_action( 'admin_post_eurocomply_omnibus_backfill', array( $this, 'handle_backfill' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		CsvExport::register();
	}

	public function register_menu() : void {
		add_menu_page(
			__( 'EuroComply Omnibus', 'eurocomply-omnibus' ),
			__( 'Omnibus', 'eurocomply-omnibus' ),
			'manage_woocommerce',
			self::MENU_SLUG,
			array( $this, 'render_page' ),
			'dashicons-chart-line',
			77
		);
	}

	public function enqueue( string $hook ) : void {
		if ( false === strpos( $hook, self::MENU_SLUG ) ) {
			return;
		}
		wp_enqueue_style(
			'eurocomply-omnibus-admin',
			EUROCOMPLY_OMNIBUS_URL . 'assets/css/admin.css',
			array(),
			EUROCOMPLY_OMNIBUS_VERSION
		);
	}

	public function render_page() : void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'eurocomply-omnibus' ) );
		}

		$allowed_tabs = array( 'dashboard', 'settings', 'history', 'pro', 'license' );
		$tab          = isset( $_GET['tab'] ) ? sanitize_key( (string) wp_unslash( $_GET['tab'] ) ) : 'dashboard'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $tab, $allowed_tabs, true ) ) {
			$tab = 'dashboard';
		}

		$is_pro = License::is_pro();

		echo '<div class="wrap eurocomply-omnibus-admin">';
		echo '<h1>' . esc_html__( 'EuroComply Omnibus', 'eurocomply-omnibus' ) . ' <span class="eurocomply-omnibus-version">v' . esc_html( EUROCOMPLY_OMNIBUS_VERSION ) . '</span></h1>';

		$this->render_tabs( $tab, $is_pro );
		settings_errors( 'eurocomply_omnibus' );

		switch ( $tab ) {
			case 'settings':
				$this->render_settings_tab( Settings::get(), $is_pro );
				break;
			case 'history':
				$this->render_history_tab();
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
			'dashboard' => __( 'Dashboard', 'eurocomply-omnibus' ),
			'settings'  => __( 'Settings', 'eurocomply-omnibus' ),
			'history'   => __( 'Price History', 'eurocomply-omnibus' ),
			'pro'       => __( 'Pro Features', 'eurocomply-omnibus' ),
			'license'   => $is_pro ? __( 'License', 'eurocomply-omnibus' ) : __( 'License (Pro)', 'eurocomply-omnibus' ),
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
			$class = 'nav-tab' . ( $active === $slug ? ' nav-tab-active' : '' );
			printf(
				'<a href="%1$s" class="%2$s">%3$s</a>',
				esc_url( $url ),
				esc_attr( $class ),
				esc_html( $label )
			);
		}
		echo '</h2>';
	}

	private function render_dashboard_tab() : void {
		$total    = PriceStore::count();
		$distinct = PriceStore::distinct_product_count();

		echo '<p>' . sprintf(
			/* translators: 1: row count, 2: product count */
			esc_html__( '%1$d price history rows recorded across %2$d products / variations.', 'eurocomply-omnibus' ),
			(int) $total,
			(int) $distinct
		) . '</p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-bottom:1em;">';
		wp_nonce_field( self::NONCE_BACKFILL );
		echo '<input type="hidden" name="action" value="eurocomply_omnibus_backfill" />';
		echo '<p>' . esc_html__( 'Run a one-off sweep across all published products and variations. Each one gets an initial history row with its current regular / sale price so the 30-day reference can start accumulating.', 'eurocomply-omnibus' ) . '</p>';
		submit_button( __( 'Backfill history from current prices', 'eurocomply-omnibus' ), 'primary', 'submit', false );
		echo '</form>';

		echo '<p><a class="button" href="' . esc_url(
			wp_nonce_url(
				add_query_arg( array( 'action' => CsvExport::ACTION ), admin_url( 'admin-post.php' ) ),
				CsvExport::NONCE_ACTION
			)
		) . '">' . esc_html__( 'Download CSV of recent price history', 'eurocomply-omnibus' ) . '</a></p>';

		$rows = PriceStore::recent( 25 );
		$this->render_history_table( $rows, false );
	}

	private function render_history_tab() : void {
		$product_id = isset( $_GET['product_id'] ) ? (int) $_GET['product_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		echo '<form method="get" style="margin-bottom:1em;">';
		echo '<input type="hidden" name="page" value="' . esc_attr( self::MENU_SLUG ) . '" />';
		echo '<input type="hidden" name="tab" value="history" />';
		echo '<label>' . esc_html__( 'Product ID:', 'eurocomply-omnibus' ) . ' ';
		echo '<input type="number" min="0" name="product_id" class="small-text" value="' . esc_attr( (string) $product_id ) . '" />';
		echo '</label> ';
		submit_button( __( 'Show history', 'eurocomply-omnibus' ), 'secondary', 'submit', false );
		echo '</form>';

		if ( $product_id > 0 ) {
			$rows  = PriceStore::history_for_product( $product_id, 500 );
			$count = count( $rows );
			echo '<p>' . sprintf(
				/* translators: 1: row count, 2: product ID */
				esc_html__( '%1$d row(s) for product / variation #%2$d.', 'eurocomply-omnibus' ),
				(int) $count,
				(int) $product_id
			) . '</p>';
			$this->render_history_table( $rows, true );
			return;
		}

		echo '<p>' . esc_html__( 'Enter a product or variation ID above to inspect its full price history.', 'eurocomply-omnibus' ) . '</p>';
		echo '<h2>' . esc_html__( 'Recent price changes (all products)', 'eurocomply-omnibus' ) . '</h2>';
		$rows = PriceStore::recent( 100 );
		$this->render_history_table( $rows, true );
	}

	/**
	 * @param array<int,array<string,mixed>> $rows
	 */
	private function render_history_table( array $rows, bool $include_source ) : void {
		echo '<table class="widefat striped"><thead><tr>';
		$headers = array(
			__( 'ID', 'eurocomply-omnibus' ),
			__( 'Product', 'eurocomply-omnibus' ),
			__( 'Regular', 'eurocomply-omnibus' ),
			__( 'Sale', 'eurocomply-omnibus' ),
			__( 'Effective', 'eurocomply-omnibus' ),
			__( 'Currency', 'eurocomply-omnibus' ),
			__( 'Recorded (UTC)', 'eurocomply-omnibus' ),
		);
		if ( $include_source ) {
			$headers[] = __( 'Source', 'eurocomply-omnibus' );
		}
		foreach ( $headers as $h ) {
			echo '<th>' . esc_html( $h ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		if ( empty( $rows ) ) {
			echo '<tr><td colspan="' . ( $include_source ? 8 : 7 ) . '">' . esc_html__( 'No price history recorded yet.', 'eurocomply-omnibus' ) . '</td></tr>';
		} else {
			foreach ( $rows as $row ) {
				$product_id = (int) $row['product_id'];
				echo '<tr>';
				echo '<td>' . esc_html( (string) $row['id'] ) . '</td>';
				echo '<td><a href="' . esc_url( admin_url( 'post.php?post=' . $product_id . '&action=edit' ) ) . '">#' . esc_html( (string) $product_id ) . '</a></td>';
				echo '<td>' . esc_html( number_format( (float) $row['regular_price'], 2, '.', '' ) ) . '</td>';
				echo '<td>' . esc_html( null === $row['sale_price'] ? '—' : number_format( (float) $row['sale_price'], 2, '.', '' ) ) . '</td>';
				echo '<td>' . esc_html( number_format( (float) $row['effective_price'], 2, '.', '' ) ) . '</td>';
				echo '<td>' . esc_html( (string) $row['currency'] ) . '</td>';
				echo '<td>' . esc_html( (string) $row['recorded_at'] ) . '</td>';
				if ( $include_source ) {
					echo '<td>' . esc_html( (string) $row['trigger_source'] ) . '</td>';
				}
				echo '</tr>';
			}
		}
		echo '</tbody></table>';
	}

	/**
	 * @param array<string,mixed> $settings
	 */
	private function render_settings_tab( array $settings, bool $is_pro ) : void {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( self::NONCE_SAVE );
		echo '<input type="hidden" name="action" value="eurocomply_omnibus_save" />';

		echo '<table class="form-table" role="presentation">';

		echo '<tr><th><label for="reference_days">' . esc_html__( 'Reference window', 'eurocomply-omnibus' ) . '</label></th><td>';
		echo '<select name="reference_days" id="reference_days">';
		$windows = array(
			30  => __( '30 days (EU default — Art. 6a PID)', 'eurocomply-omnibus' ),
			60  => __( '60 days (Pro)', 'eurocomply-omnibus' ),
			90  => __( '90 days (Pro)', 'eurocomply-omnibus' ),
			180 => __( '180 days (Pro)', 'eurocomply-omnibus' ),
		);
		foreach ( $windows as $val => $label ) {
			$disabled = ( ! $is_pro && 30 !== $val ) ? ' disabled' : '';
			$selected = selected( (int) $settings['reference_days'], $val, false );
			echo '<option value="' . esc_attr( (string) $val ) . '"' . esc_attr( $disabled ) . ' ' . $selected . '>' . esc_html( $label ) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</select>';
		if ( ! $is_pro ) {
			echo '<p class="description">' . esc_html__( 'Free tier ships the EU-mandated 30-day window. Pro unlocks 60 / 90 / 180 days for national regulators that require longer windows.', 'eurocomply-omnibus' ) . '</p>';
		}
		echo '</td></tr>';

		echo '<tr><th><label for="display_position">' . esc_html__( 'Display position', 'eurocomply-omnibus' ) . '</label></th><td>';
		echo '<select name="display_position" id="display_position">';
		$positions = array(
			'below_price'     => __( 'Below the price (default)', 'eurocomply-omnibus' ),
			'above_price'     => __( 'Above the price', 'eurocomply-omnibus' ),
			'after_addtocart' => __( 'After add-to-cart button', 'eurocomply-omnibus' ),
		);
		foreach ( $positions as $val => $label ) {
			echo '<option value="' . esc_attr( $val ) . '" ' . selected( (string) $settings['display_position'], $val, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr>';

		echo '<tr><th>' . esc_html__( 'Show on shop / archive pages', 'eurocomply-omnibus' ) . '</th><td><label>';
		echo '<input type="checkbox" name="display_on_loop" value="1"' . checked( ! empty( $settings['display_on_loop'] ), true, false ) . ' /> ';
		echo esc_html__( 'Render the disclosure on shop, category, and tag archives (in addition to the single product page).', 'eurocomply-omnibus' );
		echo '</label></td></tr>';

		echo '<tr><th>' . esc_html__( 'Show on cart / checkout', 'eurocomply-omnibus' ) . '</th><td><label>';
		echo '<input type="checkbox" name="display_on_cart" value="1"' . checked( ! empty( $settings['display_on_cart'] ), true, false ) . ' /> ';
		echo esc_html__( 'Render the disclosure inside the cart and checkout line-item price blocks.', 'eurocomply-omnibus' );
		echo '</label></td></tr>';

		echo '<tr><th>' . esc_html__( 'Hide when no history', 'eurocomply-omnibus' ) . '</th><td><label>';
		echo '<input type="checkbox" name="hide_when_no_history" value="1"' . checked( ! empty( $settings['hide_when_no_history'] ), true, false ) . ' /> ';
		echo esc_html__( 'Suppress the disclosure if no reference price exists yet — recommended during the first 30 days after install.', 'eurocomply-omnibus' );
		echo '</label></td></tr>';

		echo '<tr><th>' . esc_html__( 'Exclude introductory period', 'eurocomply-omnibus' ) . '</th><td><label>';
		echo '<input type="checkbox" name="exclude_introductory" value="1"' . checked( ! empty( $settings['exclude_introductory'] ), true, false ) . ' /> ';
		echo esc_html__( 'Skip the disclosure for products that have been on sale since launch (no pre-sale reference price). The PID permits this exemption.', 'eurocomply-omnibus' );
		echo '</label></td></tr>';

		echo '<tr><th><label for="introductory_days">' . esc_html__( 'Introductory period (days)', 'eurocomply-omnibus' ) . '</label></th><td>';
		echo '<input type="number" min="1" max="365" id="introductory_days" name="introductory_days" class="small-text" value="' . esc_attr( (string) (int) $settings['introductory_days'] ) . '" />';
		echo ' <span class="description">' . esc_html__( 'Products younger than this (first tracked row) are treated as introductory.', 'eurocomply-omnibus' ) . '</span>';
		echo '</td></tr>';

		echo '<tr><th>' . esc_html__( 'Auto-track on product save', 'eurocomply-omnibus' ) . '</th><td><label>';
		echo '<input type="checkbox" name="auto_track_on_save" value="1"' . checked( ! empty( $settings['auto_track_on_save'] ), true, false ) . ' /> ';
		echo esc_html__( 'Write a price history row whenever a product or variation is saved in the admin.', 'eurocomply-omnibus' );
		echo '</label></td></tr>';

		echo '<tr><th><label for="label_template">' . esc_html__( 'Label template', 'eurocomply-omnibus' ) . '</label></th><td>';
		echo '<input type="text" class="regular-text" id="label_template" name="label_template" value="' . esc_attr( (string) $settings['label_template'] ) . '" />';
		echo '<p class="description">' . esc_html__( 'Use %1$d for the window size and %2$s for the formatted reference price.', 'eurocomply-omnibus' ) . '</p>';
		echo '</td></tr>';

		echo '</table>';

		submit_button( __( 'Save settings', 'eurocomply-omnibus' ) );
		echo '</form>';
	}

	private function render_pro_tab( bool $is_pro ) : void {
		echo '<h2>' . esc_html__( 'Pro features', 'eurocomply-omnibus' ) . '</h2>';
		if ( $is_pro ) {
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Pro license active. All Omnibus features unlocked.', 'eurocomply-omnibus' ) . '</p></div>';
		} else {
			echo '<div class="notice notice-info inline"><p>' . esc_html__( 'Upgrade to EuroComply Pro to unlock the features below.', 'eurocomply-omnibus' ) . '</p></div>';
		}
		echo '<ul class="ul-disc">';
		foreach ( array(
			__( 'Extended reference windows: 60 / 90 / 180 days for national regulators that go beyond the EU baseline', 'eurocomply-omnibus' ),
			__( 'Per-country reference prices (WPML / Polylang) so each language store computes its own lowest price', 'eurocomply-omnibus' ),
			__( 'Multi-currency reference pricing (WooCommerce Multi-Currency, CURCY, Aelia) — tracks each currency separately', 'eurocomply-omnibus' ),
			__( 'Daily snapshot cron (WP-Cron + Action Scheduler) — picks up price changes made via import, REST, or CLI that bypass the save hook', 'eurocomply-omnibus' ),
			__( 'Auditor-ready PDF reports: per-product 30-day lowest, sale timeline, introductory-period flag, signed timestamps', 'eurocomply-omnibus' ),
			__( 'Block-editor disclosure block — drop the Omnibus notice anywhere in a product description', 'eurocomply-omnibus' ),
			__( 'Bulk CSV import of historical prices from ERP / accounting exports', 'eurocomply-omnibus' ),
			__( 'Email digest of significant price drops (configurable threshold) for the compliance officer', 'eurocomply-omnibus' ),
		) as $item ) {
			echo '<li>' . esc_html( $item ) . '</li>';
		}
		echo '</ul>';
	}

	private function render_license_tab( bool $is_pro ) : void {
		$data = License::get();

		echo '<p><strong>' . esc_html__( 'Status:', 'eurocomply-omnibus' ) . '</strong> ';
		echo $is_pro
			? '<span style="color:#0a7d28;">' . esc_html__( 'Active (Pro)', 'eurocomply-omnibus' ) . '</span>'
			: '<span>' . esc_html__( 'Free tier', 'eurocomply-omnibus' ) . '</span>';
		echo '</p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( self::NONCE_LICENSE );
		echo '<input type="hidden" name="action" value="eurocomply_omnibus_license" />';

		if ( $is_pro ) {
			echo '<p>' . esc_html__( 'License key:', 'eurocomply-omnibus' ) . ' <code>' . esc_html( (string) ( $data['key'] ?? '' ) ) . '</code></p>';
			echo '<input type="hidden" name="mode" value="deactivate" />';
			submit_button( __( 'Deactivate license', 'eurocomply-omnibus' ), 'secondary' );
		} else {
			echo '<table class="form-table" role="presentation"><tr><th><label for="license_key">' . esc_html__( 'License key', 'eurocomply-omnibus' ) . '</label></th><td>';
			echo '<input type="text" class="regular-text" id="license_key" name="license_key" value="" placeholder="EC-XXXXXX" />';
			echo '</td></tr></table>';
			submit_button( __( 'Activate license', 'eurocomply-omnibus' ) );
		}
		echo '</form>';
	}

	public function handle_save() : void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-omnibus' ) );
		}
		check_admin_referer( self::NONCE_SAVE );

		$clean = Settings::sanitize( (array) wp_unslash( $_POST ) );
		update_option( Settings::OPTION_KEY, $clean, false );

		add_settings_error( 'eurocomply_omnibus', 'saved', __( 'Settings saved.', 'eurocomply-omnibus' ), 'updated' );
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
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-omnibus' ) );
		}
		check_admin_referer( self::NONCE_LICENSE );

		$mode = isset( $_POST['mode'] ) ? sanitize_key( (string) wp_unslash( $_POST['mode'] ) ) : '';
		if ( 'deactivate' === $mode ) {
			License::deactivate();
			add_settings_error( 'eurocomply_omnibus', 'license-deactivated', __( 'License deactivated.', 'eurocomply-omnibus' ), 'updated' );
		} else {
			$key    = isset( $_POST['license_key'] ) ? (string) wp_unslash( $_POST['license_key'] ) : '';
			$result = License::activate( $key );
			add_settings_error(
				'eurocomply_omnibus',
				$result['ok'] ? 'license-activated' : 'license-invalid',
				$result['message'],
				$result['ok'] ? 'updated' : 'error'
			);
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

	public function handle_backfill() : void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-omnibus' ) );
		}
		check_admin_referer( self::NONCE_BACKFILL );

		$result = PriceTracker::instance()->backfill();

		add_settings_error(
			'eurocomply_omnibus',
			'backfill-complete',
			sprintf(
				/* translators: 1: scanned count, 2: recorded count, 3: skipped count */
				__( 'Backfill complete: %1$d scanned, %2$d recorded, %3$d skipped.', 'eurocomply-omnibus' ),
				(int) $result['scanned'],
				(int) $result['recorded'],
				(int) $result['skipped']
			),
			'updated'
		);
		set_transient( 'settings_errors', get_settings_errors(), 30 );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'             => self::MENU_SLUG,
					'tab'              => 'dashboard',
					'settings-updated' => 'true',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
