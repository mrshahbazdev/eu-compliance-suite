<?php
/**
 * WooCommerce product metabox for EPR data: per-country registration number +
 * packaging material weights (grams).
 *
 * @package EuroComply\Epr
 */

declare( strict_types = 1 );

namespace EuroComply\Epr;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ProductFields {

	public const META_REGISTRATION_PREFIX = '_epr_reg_';   // _epr_reg_DE etc.
	public const META_WEIGHT_PREFIX       = '_epr_wt_';    // _epr_wt_plastic etc. (grams, float)
	public const META_COUNTRY_COVERAGE    = '_epr_countries'; // CSV list of country codes this product declares.

	private static ?ProductFields $instance = null;

	public static function instance() : ProductFields {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}
		return self::$instance;
	}

	private function boot() : void {
		add_action( 'add_meta_boxes', array( $this, 'register_metabox' ) );
		add_action( 'save_post_product', array( $this, 'save_metabox' ), 10, 2 );
	}

	public function register_metabox() : void {
		add_meta_box(
			'eurocomply-epr-metabox',
			__( 'EPR Reporting (EU packaging)', 'eurocomply-epr' ),
			array( $this, 'render_metabox' ),
			'product',
			'normal',
			'default'
		);
	}

	public function render_metabox( \WP_Post $post ) : void {
		wp_nonce_field( 'eurocomply_epr_metabox', 'eurocomply_epr_nonce' );

		$settings  = Settings::get();
		$enabled   = (array) $settings['enabled_countries'];
		$countries = Countries::all();
		$materials = Countries::materials();

		echo '<p>' . esc_html__( 'Declare per-country EPR registration numbers and packaging weights (in grams). Only enabled countries are shown.', 'eurocomply-epr' ) . '</p>';

		echo '<h4>' . esc_html__( 'Registration numbers', 'eurocomply-epr' ) . '</h4>';
		echo '<table class="form-table"><tbody>';
		foreach ( $enabled as $code ) {
			$country = Countries::get( $code );
			if ( null === $country ) {
				continue;
			}
			$value = (string) get_post_meta( $post->ID, self::META_REGISTRATION_PREFIX . $code, true );
			echo '<tr>';
			echo '<th scope="row"><label for="epr_reg_' . esc_attr( $code ) . '">' . esc_html( $country['name'] . ' — ' . $country['reg_label'] ) . '</label></th>';
			echo '<td>';
			echo '<input type="text" class="regular-text" id="epr_reg_' . esc_attr( $code ) . '" name="epr_reg[' . esc_attr( $code ) . ']" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $country['reg_example'] ) . '" />';
			echo '<p class="description">' . esc_html( $country['regulator'] ) . '</p>';
			echo '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';

		echo '<h4>' . esc_html__( 'Packaging weights (grams)', 'eurocomply-epr' ) . '</h4>';
		echo '<table class="form-table"><tbody>';
		foreach ( $materials as $key => $label ) {
			$value = (string) get_post_meta( $post->ID, self::META_WEIGHT_PREFIX . $key, true );
			echo '<tr>';
			echo '<th scope="row"><label for="epr_wt_' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th>';
			echo '<td><input type="number" min="0" step="0.01" class="small-text" id="epr_wt_' . esc_attr( $key ) . '" name="epr_wt[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '" /> g</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	public function save_metabox( int $post_id, \WP_Post $post ) : void {
		if ( ! isset( $_POST['eurocomply_epr_nonce'] ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( (string) $_POST['eurocomply_epr_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'eurocomply_epr_metabox' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_product', $post_id ) ) {
			return;
		}

		$countries_touched = array();

		if ( isset( $_POST['epr_reg'] ) && is_array( $_POST['epr_reg'] ) ) {
			foreach ( (array) $_POST['epr_reg'] as $code => $raw ) {
				$code = strtoupper( sanitize_text_field( (string) wp_unslash( (string) $code ) ) );
				if ( ! Countries::is_supported( $code ) ) {
					continue;
				}
				$value = sanitize_text_field( (string) wp_unslash( (string) $raw ) );
				if ( '' === $value ) {
					delete_post_meta( $post_id, self::META_REGISTRATION_PREFIX . $code );
				} else {
					update_post_meta( $post_id, self::META_REGISTRATION_PREFIX . $code, $value );
					$countries_touched[ $code ] = true;
				}
			}
		}

		if ( isset( $_POST['epr_wt'] ) && is_array( $_POST['epr_wt'] ) ) {
			foreach ( Countries::materials() as $key => $_label ) {
				$raw   = isset( $_POST['epr_wt'][ $key ] ) ? (string) wp_unslash( (string) $_POST['epr_wt'][ $key ] ) : '';
				$value = '' === $raw ? 0.0 : (float) $raw;
				if ( $value <= 0 ) {
					delete_post_meta( $post_id, self::META_WEIGHT_PREFIX . $key );
				} else {
					update_post_meta( $post_id, self::META_WEIGHT_PREFIX . $key, (string) $value );
				}
			}
		}

		if ( ! empty( $countries_touched ) ) {
			update_post_meta( $post_id, self::META_COUNTRY_COVERAGE, implode( ',', array_keys( $countries_touched ) ) );
		} else {
			delete_post_meta( $post_id, self::META_COUNTRY_COVERAGE );
		}
	}

	/**
	 * Total declared packaging weight (grams) across all materials.
	 */
	public static function total_weight( int $product_id ) : float {
		$total = 0.0;
		foreach ( array_keys( Countries::materials() ) as $mat ) {
			$total += (float) get_post_meta( $product_id, self::META_WEIGHT_PREFIX . $mat, true );
		}
		return $total;
	}

	/**
	 * Per-material breakdown.
	 *
	 * @return array<string,float>
	 */
	public static function weight_breakdown( int $product_id ) : array {
		$out = array();
		foreach ( array_keys( Countries::materials() ) as $mat ) {
			$w = (float) get_post_meta( $product_id, self::META_WEIGHT_PREFIX . $mat, true );
			if ( $w > 0 ) {
				$out[ $mat ] = $w;
			}
		}
		return $out;
	}

	public static function registration_for( int $product_id, string $country_code ) : string {
		$code = strtoupper( $country_code );
		$own  = (string) get_post_meta( $product_id, self::META_REGISTRATION_PREFIX . $code, true );
		if ( '' !== $own ) {
			return $own;
		}
		$settings = Settings::get();
		if ( ! empty( $settings['inherit_defaults'] ) ) {
			$defaults = (array) $settings['default_registrations'];
			return (string) ( $defaults[ $code ] ?? '' );
		}
		return '';
	}
}
