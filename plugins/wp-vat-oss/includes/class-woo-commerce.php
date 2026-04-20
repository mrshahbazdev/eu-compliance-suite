<?php
/**
 * WooCommerce checkout integration.
 *
 * Adds a VAT-number field, validates it against VIES, stores the decision on
 * the order, and (when enabled) zeroes the EU tax line for cross-border B2B
 * sales per the EU reverse-charge rules.
 *
 * This file is only loaded when WooCommerce is active (guarded in Plugin::boot).
 *
 * @package EuroComply\VatOss
 */

declare( strict_types = 1 );

namespace EuroComply\VatOss;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WooCommerce {

	private static ?WooCommerce $instance = null;

	public static function instance() : WooCommerce {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}
		return self::$instance;
	}

	private function boot() : void {
		add_filter( 'woocommerce_billing_fields', array( $this, 'add_vat_field' ) );
		add_action( 'woocommerce_checkout_update_order_review', array( $this, 'capture_vat_post' ) );
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_to_order_meta' ), 10, 1 );
		add_filter( 'woocommerce_matched_rates', array( $this, 'maybe_zero_rates' ), 10, 2 );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'render_admin_order_badge' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_checkout_assets' ) );
	}

	/**
	 * @param array<string,array<string,mixed>> $fields
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function add_vat_field( array $fields ) : array {
		$settings = Settings::get();
		$locale   = determine_locale();
		$label    = ( 0 === strpos( $locale, 'de' ) )
			? (string) ( $settings['checkout_label_de'] ?? 'USt-IdNr.' )
			: (string) ( $settings['checkout_label_en'] ?? 'VAT / Tax ID' );
		$desc     = ( 0 === strpos( $locale, 'de' ) )
			? (string) ( $settings['checkout_help_de'] ?? '' )
			: (string) ( $settings['checkout_help_en'] ?? '' );

		$fields['billing_eurocomply_vat'] = array(
			'label'       => $label,
			'description' => $desc,
			'required'    => false,
			'class'       => array( 'form-row-wide' ),
			'priority'    => 125,
			'clear'       => true,
		);

		return $fields;
	}

	/**
	 * Capture the typed VAT number into the session so we can use it during
	 * tax-rate calculation on the same page refresh.
	 *
	 * @param string $post_data url-encoded POST body from checkout AJAX.
	 */
	public function capture_vat_post( $post_data ) : void {
		if ( ! is_string( $post_data ) || '' === $post_data ) {
			return;
		}
		parse_str( $post_data, $parsed );
		if ( ! is_array( $parsed ) ) {
			return;
		}
		$vat = isset( $parsed['billing_eurocomply_vat'] ) ? Vies::normalise( (string) $parsed['billing_eurocomply_vat'] ) : '';
		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( 'eurocomply_vat_number', $vat );
		}
	}

	/**
	 * After order creation, persist the captured VAT number and run a VIES
	 * check so the merchant has an audit trail.
	 *
	 * @param int $order_id
	 */
	public function save_to_order_meta( int $order_id ) : void {
		$vat = '';
		if ( isset( $_POST['billing_eurocomply_vat'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$vat = Vies::normalise( sanitize_text_field( (string) wp_unslash( $_POST['billing_eurocomply_vat'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
		if ( '' === $vat ) {
			return;
		}

		$settings = Settings::get();
		$order    = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
		$country  = $order && method_exists( $order, 'get_billing_country' ) ? (string) $order->get_billing_country() : '';

		$result = Vies::validate( $vat, (int) ( $settings['vies_timeout'] ?? 8 ) );

		if ( $order ) {
			$order->update_meta_data( '_eurocomply_vat', $vat );
			$order->update_meta_data( '_eurocomply_vat_valid', $result['valid'] ? '1' : '0' );
			$order->update_meta_data( '_eurocomply_vat_name', $result['name'] );
			$order->update_meta_data( '_eurocomply_vat_source', $result['source'] );
			$order->save();
		}

		TaxLog::insert(
			array(
				'event'          => 'order_checkout',
				'order_id'       => $order_id,
				'buyer_country'  => $country,
				'shop_country'   => (string) ( $settings['shop_country'] ?? '' ),
				'vat_prefix'     => $result['prefix'],
				'vat_number'     => $result['number'],
				'vat_valid'      => $result['valid'] ? 1 : 0,
				'reverse_charge' => $this->should_reverse_charge( $country, $result['valid'] ) ? 1 : 0,
				'vies_source'    => $result['source'],
				'vies_name'      => substr( (string) $result['name'], 0, 250 ),
			)
		);
	}

	/**
	 * Zero the tax line when the buyer has a valid EU B2B VAT number and the
	 * order is a cross-border EU sale. Keeps existing rates untouched otherwise.
	 *
	 * @param array<int,array<string,mixed>> $rates
	 * @param mixed                          $item
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function maybe_zero_rates( $rates, $item ) : array {
		unset( $item );
		if ( ! is_array( $rates ) || empty( $rates ) ) {
			return is_array( $rates ) ? $rates : array();
		}

		$settings = Settings::get();
		if ( '1' !== (string) ( $settings['reverse_charge_b2b'] ?? '1' ) ) {
			return $rates;
		}

		$vat = $this->session_vat();
		if ( '' === $vat || ! Vies::local_format_ok( $vat ) ) {
			return $rates;
		}

		$country = $this->session_billing_country();
		$shop    = (string) ( $settings['shop_country'] ?? '' );
		if ( '' === $country || '' === $shop || $country === $shop ) {
			return $rates;
		}
		if ( ! Rates::is_eu_country( $country ) || ! Rates::is_eu_country( $shop ) ) {
			return $rates;
		}

		// Live VIES only fires once per checkout page-load thanks to the
		// transient cache inside Vies::validate().
		$result = Vies::validate( $vat, (int) ( $settings['vies_timeout'] ?? 8 ) );
		if ( ! $result['valid'] ) {
			return $rates;
		}

		foreach ( $rates as $rate_id => $rate ) {
			$rates[ $rate_id ]['rate']  = 0;
			$rates[ $rate_id ]['label'] = sprintf(
				/* translators: %s: 2-letter VAT prefix such as DE */
				__( 'Reverse charge (EU B2B %s)', 'eurocomply-vat-oss' ),
				$result['prefix']
			);
		}
		return $rates;
	}

	private function should_reverse_charge( string $buyer_country, bool $vat_valid ) : bool {
		if ( ! $vat_valid ) {
			return false;
		}
		$settings = Settings::get();
		$shop     = (string) ( $settings['shop_country'] ?? '' );
		return $shop !== '' && $buyer_country !== '' && $buyer_country !== $shop;
	}

	private function session_vat() : string {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return '';
		}
		$vat = WC()->session->get( 'eurocomply_vat_number', '' );
		return is_string( $vat ) ? $vat : '';
	}

	private function session_billing_country() : string {
		if ( ! function_exists( 'WC' ) || ! WC()->customer ) {
			return '';
		}
		return strtoupper( (string) WC()->customer->get_billing_country() );
	}

	public function render_admin_order_badge( $order ) : void {
		if ( ! is_object( $order ) || ! method_exists( $order, 'get_meta' ) ) {
			return;
		}
		$vat = (string) $order->get_meta( '_eurocomply_vat' );
		if ( '' === $vat ) {
			return;
		}
		$valid = '1' === (string) $order->get_meta( '_eurocomply_vat_valid' );
		$name  = (string) $order->get_meta( '_eurocomply_vat_name' );

		echo '<p><strong>' . esc_html__( 'EU VAT ID', 'eurocomply-vat-oss' ) . ':</strong> ';
		echo esc_html( $vat );
		echo $valid
			? ' <span style="color:#14532d;font-weight:600;">✓ ' . esc_html__( 'VIES-validated', 'eurocomply-vat-oss' ) . '</span>'
			: ' <span style="color:#991b1b;font-weight:600;">⚠ ' . esc_html__( 'Not validated', 'eurocomply-vat-oss' ) . '</span>';
		if ( '' !== $name ) {
			echo '<br><em>' . esc_html( $name ) . '</em>';
		}
		echo '</p>';
	}

	public function enqueue_checkout_assets() : void {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}
		wp_enqueue_style(
			'eurocomply-vat-checkout',
			EUROCOMPLY_VAT_URL . 'assets/css/checkout.css',
			array(),
			EUROCOMPLY_VAT_VERSION
		);
		wp_enqueue_script(
			'eurocomply-vat-checkout',
			EUROCOMPLY_VAT_URL . 'assets/js/checkout.js',
			array( 'jquery' ),
			EUROCOMPLY_VAT_VERSION,
			true
		);
		wp_localize_script(
			'eurocomply-vat-checkout',
			'EuroComplyVAT',
			array(
				'rest'  => esc_url_raw( rest_url( Rest::NAMESPACE . '/validate' ) ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
				'i18n'  => array(
					'checking' => __( 'Checking VAT number…', 'eurocomply-vat-oss' ),
					'valid'    => __( 'VAT number is valid.', 'eurocomply-vat-oss' ),
					'invalid'  => __( 'VAT number is not valid.', 'eurocomply-vat-oss' ),
					'error'    => __( 'Could not reach VIES — tax will apply as normal.', 'eurocomply-vat-oss' ),
				),
			)
		);
	}
}
