<?php
/**
 * Renders the Omnibus "previous lowest price in the last N days" disclosure
 * next to the sale price on shop / single / cart / checkout pages.
 *
 * @package EuroComply\Omnibus
 */

declare( strict_types = 1 );

namespace EuroComply\Omnibus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PriceDisplay {

	private static ?PriceDisplay $instance = null;

	public static function instance() : PriceDisplay {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register();
		}
		return self::$instance;
	}

	private function register() : void {
		add_filter( 'woocommerce_get_price_html', array( $this, 'filter_price_html' ), 20, 2 );
	}

	/**
	 * @param string $html
	 * @param mixed  $product WC_Product
	 */
	public function filter_price_html( string $html, $product ) : string {
		if ( ! is_object( $product ) || ! method_exists( $product, 'is_on_sale' ) || ! $product->is_on_sale() ) {
			return $html;
		}

		$settings = Settings::get();

		if ( ! self::in_allowed_context( $settings ) ) {
			return $html;
		}

		$product_id = (int) $product->get_id();
		$reference  = self::reference_price( $product_id, $settings );

		if ( null === $reference ) {
			if ( ! empty( $settings['hide_when_no_history'] ) ) {
				return $html;
			}
			$notice = self::markup( '—', '', $settings, true );
			return self::append( $html, $notice, (string) $settings['display_position'] );
		}

		$currency_code = (string) $reference['currency'];
		$formatted     = self::format_price( $reference['price'], $currency_code );
		$notice        = self::markup( $formatted, $currency_code, $settings, false );

		return self::append( $html, $notice, (string) $settings['display_position'] );
	}

	/**
	 * @param array<string,mixed> $settings
	 *
	 * @return array{price:float,currency:string,recorded_at:string}|null
	 */
	public static function reference_price( int $product_id, array $settings ) : ?array {
		$days = (int) ( $settings['reference_days'] ?? 30 );

		$before_ts = null;
		if ( ! empty( $settings['exclude_introductory'] ) ) {
			$first = PriceStore::first_recorded_at( $product_id );
			if ( null !== $first ) {
				$age_days = (int) ceil( ( time() - $first ) / DAY_IN_SECONDS );
				if ( $age_days < (int) $settings['introductory_days'] ) {
					return null;
				}
			}
			$sale_start = PriceStore::sale_started_at( $product_id );
			if ( null !== $sale_start ) {
				$before_ts = $sale_start;
			}
		}

		return PriceStore::lowest_in_window( $product_id, $days, $before_ts );
	}

	/**
	 * @param array<string,mixed> $settings
	 */
	private static function in_allowed_context( array $settings ) : bool {
		if ( function_exists( 'is_product' ) && is_product() ) {
			return true;
		}
		if ( ! empty( $settings['display_on_loop'] ) && function_exists( 'is_shop' ) && ( is_shop() || is_product_category() || is_product_tag() ) ) {
			return true;
		}
		if ( ! empty( $settings['display_on_cart'] ) && function_exists( 'is_cart' ) && ( is_cart() || is_checkout() ) ) {
			return true;
		}
		// Admin / REST / other contexts: keep the disclosure on so it shows in
		// blocks that render outside the main archive conditionals.
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return true;
		}
		return false;
	}

	private static function format_price( float $amount, string $currency ) : string {
		if ( function_exists( 'wc_price' ) ) {
			return (string) wc_price( $amount, array( 'currency' => $currency ) );
		}
		return number_format( $amount, 2, '.', ',' ) . ' ' . $currency;
	}

	/**
	 * @param array<string,mixed> $settings
	 */
	private static function markup( string $formatted_price, string $currency, array $settings, bool $is_placeholder ) : string {
		$days     = (int) ( $settings['reference_days'] ?? 30 );
		$template = (string) ( $settings['label_template'] ?? '' );
		if ( '' === $template ) {
			$template = __( 'Previous lowest price (last %1$d days): %2$s', 'eurocomply-omnibus' );
		}
		$label = sprintf(
			/* translators: %1$d: number of days, %2$s: formatted price. */
			$template,
			$days,
			$formatted_price
		);
		$classes = 'eurocomply-omnibus-reference';
		if ( $is_placeholder ) {
			$classes .= ' eurocomply-omnibus-reference--empty';
		}
		return '<span class="' . esc_attr( $classes ) . '" data-reference-days="' . esc_attr( (string) $days ) . '" data-currency="' . esc_attr( $currency ) . '">' . wp_kses_post( $label ) . '</span>';
	}

	private static function append( string $html, string $notice, string $position ) : string {
		switch ( $position ) {
			case 'above_price':
				return '<span class="eurocomply-omnibus-above">' . $notice . '</span>' . $html;
			case 'after_addtocart':
				// Render inline for now; the "after add-to-cart" variant is a Pro feature
				// that hooks woocommerce_single_product_summary directly.
				return $html . ' <small>' . $notice . '</small>';
			case 'below_price':
			default:
				return $html . '<br /><small>' . $notice . '</small>';
		}
	}
}
