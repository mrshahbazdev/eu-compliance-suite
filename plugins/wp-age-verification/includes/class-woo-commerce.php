<?php
/**
 * WooCommerce integration: product-level gating, cart / checkout enforcement.
 *
 * @package EuroComply\AgeVerification
 */

declare( strict_types = 1 );

namespace EuroComply\AgeVerification;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WooCommerce {

	public const META_MIN_AGE = '_eurocomply_av_min_age';

	private static ?WooCommerce $instance = null;

	public static function instance() : WooCommerce {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register();
		}
		return self::$instance;
	}

	private function register() : void {
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'render_product_field' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_field' ) );
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_add_to_cart' ), 10, 3 );
		add_action( 'woocommerce_check_cart_items', array( $this, 'validate_cart' ) );
		add_action( 'woocommerce_review_order_before_submit', array( $this, 'render_checkout_block' ) );
	}

	public function render_product_field() : void {
		global $post;
		if ( ! $post ) {
			return;
		}
		woocommerce_wp_text_input(
			array(
				'id'                => self::META_MIN_AGE,
				'label'             => __( 'Minimum age to purchase', 'eurocomply-age-verification' ),
				'description'       => __( 'Override the category / default minimum age for this product. Leave empty to inherit. 0 disables gating for this product.', 'eurocomply-age-verification' ),
				'desc_tip'          => true,
				'type'              => 'number',
				'custom_attributes' => array( 'min' => 0, 'max' => 25, 'step' => 1 ),
				'value'             => (string) get_post_meta( $post->ID, self::META_MIN_AGE, true ),
			)
		);
	}

	public function save_product_field( int $post_id ) : void {
		if ( ! isset( $_POST[ self::META_MIN_AGE ] ) ) {
			return;
		}
		$raw = sanitize_text_field( wp_unslash( (string) $_POST[ self::META_MIN_AGE ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( '' === $raw ) {
			delete_post_meta( $post_id, self::META_MIN_AGE );
			return;
		}
		$age = max( 0, min( 25, (int) $raw ) );
		update_post_meta( $post_id, self::META_MIN_AGE, $age );
	}

	public static function required_age_for_product( int $product_id ) : int {
		if ( $product_id <= 0 ) {
			return 0;
		}
		$meta = get_post_meta( $product_id, self::META_MIN_AGE, true );
		if ( '' !== $meta && null !== $meta ) {
			return (int) $meta;
		}
		$s     = Settings::get();
		$terms = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
		$max   = 0;
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term_id ) {
				$tid = (int) $term_id;
				if ( ! in_array( $tid, (array) $s['restricted_categories'], true ) ) {
					continue;
				}
				$age = Settings::min_age_for_term( $tid );
				if ( $age > $max ) {
					$max = $age;
				}
			}
		}
		return $max;
	}

	public static function product_requires_gate( int $product_id ) : bool {
		return self::required_age_for_product( $product_id ) > 0;
	}

	/**
	 * Validate add-to-cart: if the product requires age gating and the session
	 * is not verified (or is under-age), block the operation with a notice.
	 */
	public function validate_add_to_cart( $passed, $product_id, $quantity ) {
		if ( ! $passed ) {
			return $passed;
		}
		$required = self::required_age_for_product( (int) $product_id );
		if ( $required <= 0 ) {
			return $passed;
		}
		$gate = AgeGate::instance();
		if ( $gate->has_valid_session() ) {
			return $passed;
		}
		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice(
				sprintf(
					/* translators: %d: required age */
					__( 'You must verify that you are at least %d years old before adding this product to your cart.', 'eurocomply-age-verification' ),
					$required
				),
				'error'
			);
		}
		return false;
	}

	public function validate_cart() : void {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}
		$gate = AgeGate::instance();
		if ( $gate->has_valid_session() ) {
			return;
		}
		foreach ( WC()->cart->get_cart() as $item ) {
			$product_id = isset( $item['product_id'] ) ? (int) $item['product_id'] : 0;
			$required   = self::required_age_for_product( $product_id );
			if ( $required > 0 ) {
				if ( function_exists( 'wc_add_notice' ) ) {
					wc_add_notice(
						sprintf(
							/* translators: %d: required age */
							__( 'Your cart contains age-restricted items. Please complete age verification (minimum %d) before checking out.', 'eurocomply-age-verification' ),
							$required
						),
						'error'
					);
				}
				break;
			}
		}
	}

	public function render_checkout_block() : void {
		$s = Settings::get();
		if ( empty( $s['show_checkout_block'] ) ) {
			return;
		}
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}
		$has_gated = false;
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( self::required_age_for_product( (int) ( $item['product_id'] ?? 0 ) ) > 0 ) {
				$has_gated = true;
				break;
			}
		}
		if ( ! $has_gated ) {
			return;
		}
		$gate = AgeGate::instance();
		if ( $gate->has_valid_session() ) {
			echo '<p class="eurocomply-av-checkout-verified">'
				. esc_html__( 'Age verification passed for this session.', 'eurocomply-age-verification' )
				. '</p>';
			return;
		}
		echo '<p class="eurocomply-av-checkout-required">'
			. esc_html__( 'This order contains age-restricted products. Complete age verification before placing the order.', 'eurocomply-age-verification' )
			. '</p>';
	}
}
