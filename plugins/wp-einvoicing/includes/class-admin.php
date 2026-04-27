<?php
/**
 * WordPress admin UI for EuroComply E-Invoicing.
 *
 * 4 tabs: Invoices · Settings · Pro Features · License.
 *
 * @package EuroComply\EInvoicing
 */

declare( strict_types = 1 );

namespace EuroComply\EInvoicing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin {

	public const MENU_SLUG     = 'eurocomply-einvoicing';
	public const NONCE_SAVE    = 'eurocomply_einv_save';
	public const NONCE_LICENSE = 'eurocomply_einv_license';
	public const NONCE_GENERATE = 'eurocomply_einv_generate';

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
		add_action( 'admin_post_eurocomply_einv_save', array( $this, 'handle_save' ) );
		add_action( 'admin_post_eurocomply_einv_license', array( $this, 'handle_license' ) );
		add_action( 'admin_post_eurocomply_einv_generate', array( $this, 'handle_generate' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		CsvExport::register();
	}

	public function register_menu() : void {
		add_menu_page(
			__( 'EuroComply E-Invoicing', 'eurocomply-einvoicing' ),
			__( 'E-Invoicing', 'eurocomply-einvoicing' ),
			'manage_woocommerce',
			self::MENU_SLUG,
			array( $this, 'render_page' ),
			'dashicons-media-spreadsheet',
			76
		);
	}

	public function enqueue( string $hook ) : void {
		if ( false === strpos( $hook, self::MENU_SLUG ) ) {
			return;
		}
		wp_enqueue_style(
			'eurocomply-einv-admin',
			EUROCOMPLY_EINV_URL . 'assets/css/admin.css',
			array(),
			EUROCOMPLY_EINV_VERSION
		);
	}

	public function render_page() : void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'eurocomply-einvoicing' ) );
		}

		$allowed_tabs = array( 'invoices', 'settings', 'pro', 'license' );
		$tab          = isset( $_GET['tab'] ) ? sanitize_key( (string) wp_unslash( $_GET['tab'] ) ) : 'invoices'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $tab, $allowed_tabs, true ) ) {
			$tab = 'invoices';
		}

		$is_pro = License::is_pro();

		echo '<div class="wrap eurocomply-einv-admin">';
		echo '<h1>' . esc_html__( 'EuroComply E-Invoicing', 'eurocomply-einvoicing' ) . ' <span class="eurocomply-einv-version">v' . esc_html( EUROCOMPLY_EINV_VERSION ) . '</span></h1>';

		$this->render_tabs( $tab, $is_pro );
		settings_errors( 'eurocomply_einv' );

		switch ( $tab ) {
			case 'settings':
				$this->render_settings_tab( Settings::get(), $is_pro );
				break;
			case 'pro':
				$this->render_pro_tab( $is_pro );
				break;
			case 'license':
				$this->render_license_tab( $is_pro );
				break;
			case 'invoices':
			default:
				$this->render_invoices_tab();
				break;
		}
		echo '</div>';
	}

	private function render_tabs( string $active, bool $is_pro ) : void {
		$tabs = array(
			'invoices' => __( 'Invoices', 'eurocomply-einvoicing' ),
			'settings' => __( 'Settings', 'eurocomply-einvoicing' ),
			'pro'      => __( 'Pro Features', 'eurocomply-einvoicing' ),
			'license'  => $is_pro ? __( 'License', 'eurocomply-einvoicing' ) : __( 'License (Pro)', 'eurocomply-einvoicing' ),
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

	private function render_invoices_tab() : void {
		$rows  = InvoiceStore::recent( 50 );
		$total = InvoiceStore::count();

		echo '<p>' . sprintf(
			/* translators: %d: total invoice count */
			esc_html__( '%d invoice(s) recorded.', 'eurocomply-einvoicing' ),
			(int) $total
		) . '</p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-bottom:1em;">';
		wp_nonce_field( self::NONCE_GENERATE );
		echo '<input type="hidden" name="action" value="eurocomply_einv_generate" />';
		echo '<label>' . esc_html__( 'Generate invoice for Order ID:', 'eurocomply-einvoicing' ) . ' ';
		echo '<input type="number" min="1" name="order_id" required class="small-text" />';
		echo '</label> ';
		submit_button( __( 'Generate', 'eurocomply-einvoicing' ), 'primary', 'submit', false );
		echo '</form>';

		echo '<p><a class="button" href="' . esc_url(
			wp_nonce_url(
				add_query_arg( array( 'action' => CsvExport::ACTION ), admin_url( 'admin-post.php' ) ),
				CsvExport::NONCE_ACTION
			)
		) . '">' . esc_html__( 'Download CSV of invoice log', 'eurocomply-einvoicing' ) . '</a></p>';

		echo '<table class="widefat striped"><thead><tr>';
		foreach ( array(
			__( 'ID', 'eurocomply-einvoicing' ),
			__( 'Order', 'eurocomply-einvoicing' ),
			__( 'Invoice #', 'eurocomply-einvoicing' ),
			__( 'Profile', 'eurocomply-einvoicing' ),
			__( 'Total', 'eurocomply-einvoicing' ),
			__( 'Status', 'eurocomply-einvoicing' ),
			__( 'Generated (UTC)', 'eurocomply-einvoicing' ),
			__( 'File', 'eurocomply-einvoicing' ),
		) as $h ) {
			echo '<th>' . esc_html( $h ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		if ( empty( $rows ) ) {
			echo '<tr><td colspan="8">' . esc_html__( 'No invoices generated yet.', 'eurocomply-einvoicing' ) . '</td></tr>';
		} else {
			foreach ( $rows as $row ) {
				echo '<tr>';
				echo '<td>' . esc_html( (string) $row['id'] ) . '</td>';
				echo '<td><a href="' . esc_url( admin_url( 'post.php?post=' . (int) $row['order_id'] . '&action=edit' ) ) . '">#' . esc_html( (string) $row['order_id'] ) . '</a></td>';
				echo '<td>' . esc_html( (string) $row['invoice_number'] ) . '</td>';
				echo '<td>' . esc_html( strtoupper( (string) $row['profile'] ) ) . '</td>';
				echo '<td>' . esc_html( (string) $row['total'] . ' ' . (string) $row['currency'] ) . '</td>';
				echo '<td>' . esc_html( (string) $row['status'] ) . '</td>';
				echo '<td>' . esc_html( (string) $row['generated_at'] ) . '</td>';
				echo '<td>';
				if ( ! empty( $row['file_url'] ) ) {
					echo '<a href="' . esc_url( (string) $row['file_url'] ) . '">' . esc_html__( 'Download', 'eurocomply-einvoicing' ) . '</a>';
				} else {
					echo '&mdash;';
				}
				echo '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';
	}

	private function render_settings_tab( array $settings, bool $is_pro ) : void {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( self::NONCE_SAVE );
		echo '<input type="hidden" name="action" value="eurocomply_einv_save" />';

		echo '<table class="form-table" role="presentation">';
		$this->text_row( 'seller_name', __( 'Seller name', 'eurocomply-einvoicing' ), (string) $settings['seller_name'] );
		$this->text_row( 'seller_vat_id', __( 'Seller VAT ID', 'eurocomply-einvoicing' ), (string) $settings['seller_vat_id'], 'e.g. DE123456789' );
		$this->text_row( 'seller_tax_id', __( 'Seller tax ID', 'eurocomply-einvoicing' ), (string) $settings['seller_tax_id'] );
		$this->text_row( 'seller_registration', __( 'Registration (HRB/RCS)', 'eurocomply-einvoicing' ), (string) $settings['seller_registration'] );
		$this->text_row( 'seller_address_line', __( 'Address line', 'eurocomply-einvoicing' ), (string) $settings['seller_address_line'] );
		$this->text_row( 'seller_postcode', __( 'Postcode', 'eurocomply-einvoicing' ), (string) $settings['seller_postcode'] );
		$this->text_row( 'seller_city', __( 'City', 'eurocomply-einvoicing' ), (string) $settings['seller_city'] );
		$this->text_row( 'seller_country', __( 'Country (ISO 3166-1 alpha-2)', 'eurocomply-einvoicing' ), (string) $settings['seller_country'] );
		$this->text_row( 'seller_email', __( 'Seller contact email', 'eurocomply-einvoicing' ), (string) $settings['seller_email'] );
		$this->text_row( 'currency', __( 'Default currency (ISO 4217)', 'eurocomply-einvoicing' ), (string) $settings['currency'] );
		$this->text_row( 'invoice_prefix', __( 'Invoice number prefix', 'eurocomply-einvoicing' ), (string) $settings['invoice_prefix'] );

		echo '<tr><th><label for="invoice_profile">' . esc_html__( 'Factur-X profile', 'eurocomply-einvoicing' ) . '</label></th><td>';
		echo '<select name="invoice_profile" id="invoice_profile">';
		$profiles = array(
			'minimum'  => __( 'MINIMUM (free)', 'eurocomply-einvoicing' ),
			'basic'    => __( 'BASIC (Pro)', 'eurocomply-einvoicing' ),
			'en16931'  => __( 'EN 16931 (Pro)', 'eurocomply-einvoicing' ),
			'extended' => __( 'EXTENDED (Pro)', 'eurocomply-einvoicing' ),
		);
		foreach ( $profiles as $val => $label ) {
			$disabled = ( ! $is_pro && 'minimum' !== $val ) ? ' disabled' : '';
			$selected = selected( (string) $settings['invoice_profile'], $val, false );
			echo '<option value="' . esc_attr( $val ) . '"' . esc_attr( $disabled ) . ' ' . $selected . '>' . esc_html( $label ) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</select>';
		if ( ! $is_pro ) {
			echo '<p class="description">' . esc_html__( 'Free tier ships MINIMUM profile only. Activate a Pro license to unlock BASIC, EN 16931, and EXTENDED profiles.', 'eurocomply-einvoicing' ) . '</p>';
		}
		echo '</td></tr>';

		echo '<tr><th>' . esc_html__( 'Auto-generate', 'eurocomply-einvoicing' ) . '</th><td><label>';
		echo '<input type="checkbox" name="auto_generate" value="1"' . checked( ! empty( $settings['auto_generate'] ), true, false ) . ' /> ';
		echo esc_html__( 'Automatically generate an invoice when an order reaches the trigger status.', 'eurocomply-einvoicing' );
		echo '</label></td></tr>';

		echo '<tr><th><label for="trigger_status">' . esc_html__( 'Trigger status', 'eurocomply-einvoicing' ) . '</label></th><td>';
		echo '<select name="trigger_status" id="trigger_status">';
		foreach ( array(
			'completed'  => __( 'Completed', 'eurocomply-einvoicing' ),
			'processing' => __( 'Processing', 'eurocomply-einvoicing' ),
		) as $val => $label ) {
			echo '<option value="' . esc_attr( $val ) . '" ' . selected( (string) $settings['trigger_status'], $val, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '</table>';

		submit_button( __( 'Save settings', 'eurocomply-einvoicing' ) );
		echo '</form>';
	}

	private function text_row( string $name, string $label, string $value, string $placeholder = '' ) : void {
		echo '<tr><th><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th><td>';
		echo '<input type="text" class="regular-text" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"';
		if ( '' !== $placeholder ) {
			echo ' placeholder="' . esc_attr( $placeholder ) . '"';
		}
		echo ' /></td></tr>';
	}

	private function render_pro_tab( bool $is_pro ) : void {
		echo '<h2>' . esc_html__( 'Pro features', 'eurocomply-einvoicing' ) . '</h2>';
		if ( $is_pro ) {
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Pro license active. All e-invoicing profiles and jurisdictions unlocked.', 'eurocomply-einvoicing' ) . '</p></div>';
		} else {
			echo '<div class="notice notice-info inline"><p>' . esc_html__( 'Upgrade to EuroComply Pro to unlock the features below.', 'eurocomply-einvoicing' ) . '</p></div>';
		}
		echo '<ul class="ul-disc">';
		foreach ( array(
			__( 'Factur-X BASIC / EN 16931 / EXTENDED profiles (full line-item detail)', 'eurocomply-einvoicing' ),
			__( 'PDF/A-3 archival conformance for the hybrid PDF container', 'eurocomply-einvoicing' ),
			__( 'Peppol BIS Billing 3.0 (UBL XML) export', 'eurocomply-einvoicing' ),
			__( 'Peppol Access Point sending with SMP lookup', 'eurocomply-einvoicing' ),
			__( 'Country-specific profiles: DE XRechnung, FR Chorus Pro, IT SDI / FatturaPA, PL KSeF', 'eurocomply-einvoicing' ),
			__( 'Bulk regenerate, resend, and 10-year GoBD-compliant archival', 'eurocomply-einvoicing' ),
			__( 'Digital signatures for FR Chorus Pro / IT SDI submission', 'eurocomply-einvoicing' ),
			__( 'Status webhooks and REST endpoints for ERP integration', 'eurocomply-einvoicing' ),
		) as $item ) {
			echo '<li>' . esc_html( $item ) . '</li>';
		}
		echo '</ul>';
	}

	private function render_license_tab( bool $is_pro ) : void {
		$data = License::get();

		echo '<p><strong>' . esc_html__( 'Status:', 'eurocomply-einvoicing' ) . '</strong> ';
		echo $is_pro
			? '<span style="color:#0a7d28;">' . esc_html__( 'Active (Pro)', 'eurocomply-einvoicing' ) . '</span>'
			: '<span>' . esc_html__( 'Free tier', 'eurocomply-einvoicing' ) . '</span>';
		echo '</p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( self::NONCE_LICENSE );
		echo '<input type="hidden" name="action" value="eurocomply_einv_license" />';

		if ( $is_pro ) {
			echo '<p>' . esc_html__( 'License key:', 'eurocomply-einvoicing' ) . ' <code>' . esc_html( (string) ( $data['key'] ?? '' ) ) . '</code></p>';
			echo '<input type="hidden" name="mode" value="deactivate" />';
			submit_button( __( 'Deactivate license', 'eurocomply-einvoicing' ), 'secondary' );
		} else {
			echo '<table class="form-table" role="presentation"><tr><th><label for="license_key">' . esc_html__( 'License key', 'eurocomply-einvoicing' ) . '</label></th><td>';
			echo '<input type="text" class="regular-text" id="license_key" name="license_key" value="" placeholder="EC-XXXXXX" />';
			echo '</td></tr></table>';
			submit_button( __( 'Activate license', 'eurocomply-einvoicing' ) );
		}
		echo '</form>';
	}

	public function handle_save() : void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-einvoicing' ) );
		}
		check_admin_referer( self::NONCE_SAVE );

		$clean = Settings::sanitize( (array) wp_unslash( $_POST ) );
		update_option( Settings::OPTION_KEY, $clean, false );

		add_settings_error( 'eurocomply_einv', 'saved', __( 'Settings saved.', 'eurocomply-einvoicing' ), 'updated' );
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
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-einvoicing' ) );
		}
		check_admin_referer( self::NONCE_LICENSE );

		$mode = isset( $_POST['mode'] ) ? sanitize_key( (string) wp_unslash( $_POST['mode'] ) ) : '';
		if ( 'deactivate' === $mode ) {
			License::deactivate();
			add_settings_error( 'eurocomply_einv', 'license_off', __( 'License deactivated.', 'eurocomply-einvoicing' ), 'updated' );
		} else {
			$key    = isset( $_POST['license_key'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['license_key'] ) ) : '';
			$result = License::activate( $key );
			if ( $result['ok'] ) {
				add_settings_error( 'eurocomply_einv', 'license_on', (string) $result['message'], 'updated' );
			} else {
				add_settings_error( 'eurocomply_einv', 'license_err', (string) $result['message'], 'error' );
			}
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

	public function handle_generate() : void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-einvoicing' ) );
		}
		check_admin_referer( self::NONCE_GENERATE );

		$order_id = isset( $_POST['order_id'] ) ? (int) $_POST['order_id'] : 0;
		if ( $order_id <= 0 ) {
			add_settings_error( 'eurocomply_einv', 'gen_bad', __( 'Invalid order ID.', 'eurocomply-einvoicing' ), 'error' );
		} else {
			$result = InvoiceGenerator::generate_for_order( $order_id );
			if ( $result['ok'] ) {
				add_settings_error( 'eurocomply_einv', 'gen_ok', (string) $result['message'], 'updated' );
			} else {
				add_settings_error( 'eurocomply_einv', 'gen_err', (string) $result['message'], 'error' );
			}
		}
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'             => self::MENU_SLUG,
					'tab'              => 'invoices',
					'settings-updated' => 'true',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
