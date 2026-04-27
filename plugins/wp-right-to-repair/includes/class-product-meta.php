<?php
/**
 * WooCommerce product meta for right-to-repair.
 *
 * Stored as post meta on the WC product post type:
 *   _eurocomply_r2r_category           enum (see Settings::product_categories)
 *   _eurocomply_r2r_energy_class       enum A..G | NA
 *   _eurocomply_r2r_energy_kwh         int (kWh/year)
 *   _eurocomply_r2r_repair_index       float 0..10 (FR Indice de réparabilité)
 *   _eurocomply_r2r_spare_parts_years  int 0..15
 *   _eurocomply_r2r_spare_parts_url    url (supplier / parts catalogue)
 *   _eurocomply_r2r_repair_manual_url  url
 *   _eurocomply_r2r_eprel_id           string (EPREL product registration id)
 *   _eurocomply_r2r_warranty_years     int 0..10
 *   _eurocomply_r2r_disassembly_score  float 0..10 (disassembly ease)
 *
 * @package EuroComply\R2R
 */

declare( strict_types = 1 );

namespace EuroComply\R2R;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ProductMeta {

	public const NONCE = 'eurocomply_r2r_product_meta';

	private static ?ProductMeta $instance = null;

	public static function instance() : ProductMeta {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register();
		}
		return self::$instance;
	}

	private function register() : void {
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'render_panel' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save' ), 10, 1 );
	}

	public function render_panel() : void {
		global $post;
		if ( ! $post instanceof \WP_Post ) {
			return;
		}
		$categories = Settings::product_categories();
		$cat        = (string) get_post_meta( $post->ID, '_eurocomply_r2r_category', true );
		$class      = (string) get_post_meta( $post->ID, '_eurocomply_r2r_energy_class', true );
		$kwh        = (int) get_post_meta( $post->ID, '_eurocomply_r2r_energy_kwh', true );
		$index      = (string) get_post_meta( $post->ID, '_eurocomply_r2r_repair_index', true );
		$years      = (string) get_post_meta( $post->ID, '_eurocomply_r2r_spare_parts_years', true );
		$spare_url  = (string) get_post_meta( $post->ID, '_eurocomply_r2r_spare_parts_url', true );
		$manual_url = (string) get_post_meta( $post->ID, '_eurocomply_r2r_repair_manual_url', true );
		$eprel      = (string) get_post_meta( $post->ID, '_eurocomply_r2r_eprel_id', true );
		$warranty   = (string) get_post_meta( $post->ID, '_eurocomply_r2r_warranty_years', true );
		$disassy    = (string) get_post_meta( $post->ID, '_eurocomply_r2r_disassembly_score', true );

		echo '<div class="options_group eurocomply-r2r-panel">';
		echo '<h4 style="margin:10px 12px 4px;">' . esc_html__( 'EuroComply — Right-to-Repair & Energy', 'eurocomply-r2r' ) . '</h4>';

		wp_nonce_field( self::NONCE, '_eurocomply_r2r_nonce' );

		echo '<p class="form-field"><label for="eurocomply_r2r_category">' . esc_html__( 'ESPR product category', 'eurocomply-r2r' ) . '</label>';
		echo '<select id="eurocomply_r2r_category" name="eurocomply_r2r_category">';
		foreach ( $categories as $slug => $info ) {
			printf(
				'<option value="%1$s" %3$s>%2$s (%4$dy spare parts)</option>',
				esc_attr( $slug ),
				esc_html( (string) $info['label'] ),
				selected( $cat, $slug, false ),
				(int) $info['spare_parts_years']
			);
		}
		echo '</select></p>';

		echo '<p class="form-field"><label for="eurocomply_r2r_energy_class">' . esc_html__( 'Energy class', 'eurocomply-r2r' ) . '</label>';
		echo '<select id="eurocomply_r2r_energy_class" name="eurocomply_r2r_energy_class">';
		echo '<option value="">' . esc_html__( '— none —', 'eurocomply-r2r' ) . '</option>';
		foreach ( Settings::energy_classes() as $slug => $label ) {
			printf( '<option value="%1$s" %3$s>%2$s</option>', esc_attr( $slug ), esc_html( (string) $label ), selected( $class, $slug, false ) );
		}
		echo '</select></p>';

		woocommerce_wp_text_input( array(
			'id'                => 'eurocomply_r2r_energy_kwh',
			'label'             => __( 'Energy consumption (kWh / year)', 'eurocomply-r2r' ),
			'type'              => 'number',
			'value'             => $kwh ? (string) $kwh : '',
			'custom_attributes' => array( 'min' => '0', 'max' => '100000', 'step' => '1' ),
		) );

		woocommerce_wp_text_input( array(
			'id'                => 'eurocomply_r2r_repair_index',
			'label'             => __( 'Reparability score (0–10)', 'eurocomply-r2r' ),
			'description'       => __( 'FR Indice de réparabilité (2021) or equivalent operator-declared score.', 'eurocomply-r2r' ),
			'desc_tip'          => true,
			'type'              => 'number',
			'value'             => $index,
			'custom_attributes' => array( 'min' => '0', 'max' => '10', 'step' => '0.1' ),
		) );

		woocommerce_wp_text_input( array(
			'id'                => 'eurocomply_r2r_disassembly_score',
			'label'             => __( 'Disassembly ease score (0–10)', 'eurocomply-r2r' ),
			'type'              => 'number',
			'value'             => $disassy,
			'custom_attributes' => array( 'min' => '0', 'max' => '10', 'step' => '0.1' ),
		) );

		woocommerce_wp_text_input( array(
			'id'                => 'eurocomply_r2r_spare_parts_years',
			'label'             => __( 'Spare parts guaranteed (years)', 'eurocomply-r2r' ),
			'type'              => 'number',
			'value'             => $years,
			'custom_attributes' => array( 'min' => '0', 'max' => '15', 'step' => '1' ),
		) );

		woocommerce_wp_text_input( array(
			'id'    => 'eurocomply_r2r_spare_parts_url',
			'label' => __( 'Spare parts catalogue URL', 'eurocomply-r2r' ),
			'type'  => 'url',
			'value' => $spare_url,
		) );

		woocommerce_wp_text_input( array(
			'id'    => 'eurocomply_r2r_repair_manual_url',
			'label' => __( 'Repair manual URL', 'eurocomply-r2r' ),
			'type'  => 'url',
			'value' => $manual_url,
		) );

		woocommerce_wp_text_input( array(
			'id'          => 'eurocomply_r2r_eprel_id',
			'label'       => __( 'EPREL registration ID', 'eurocomply-r2r' ),
			'type'        => 'text',
			'value'       => $eprel,
			'description' => __( 'Product ID from the European Product Registry for Energy Labelling (eprel.ec.europa.eu). Pro: automatic sync.', 'eurocomply-r2r' ),
			'desc_tip'    => true,
		) );

		woocommerce_wp_text_input( array(
			'id'                => 'eurocomply_r2r_warranty_years',
			'label'             => __( 'Warranty (years)', 'eurocomply-r2r' ),
			'description'       => __( 'EU statutory minimum is 2 years. Add extended commercial warranty if any.', 'eurocomply-r2r' ),
			'desc_tip'          => true,
			'type'              => 'number',
			'value'             => $warranty,
			'custom_attributes' => array( 'min' => '0', 'max' => '10', 'step' => '1' ),
		) );

		echo '</div>';
	}

	public function save( int $post_id ) : void {
		if ( ! isset( $_POST['_eurocomply_r2r_nonce'] ) || ! wp_verify_nonce( (string) $_POST['_eurocomply_r2r_nonce'], self::NONCE ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_product', $post_id ) ) {
			return;
		}

		$map = array(
			'eurocomply_r2r_category'           => array( 'key' => '_eurocomply_r2r_category',           'type' => 'category' ),
			'eurocomply_r2r_energy_class'       => array( 'key' => '_eurocomply_r2r_energy_class',       'type' => 'energy_class' ),
			'eurocomply_r2r_energy_kwh'         => array( 'key' => '_eurocomply_r2r_energy_kwh',         'type' => 'int_nonneg' ),
			'eurocomply_r2r_repair_index'       => array( 'key' => '_eurocomply_r2r_repair_index',       'type' => 'score_10' ),
			'eurocomply_r2r_disassembly_score'  => array( 'key' => '_eurocomply_r2r_disassembly_score',  'type' => 'score_10' ),
			'eurocomply_r2r_spare_parts_years'  => array( 'key' => '_eurocomply_r2r_spare_parts_years',  'type' => 'int_15' ),
			'eurocomply_r2r_spare_parts_url'    => array( 'key' => '_eurocomply_r2r_spare_parts_url',    'type' => 'url' ),
			'eurocomply_r2r_repair_manual_url'  => array( 'key' => '_eurocomply_r2r_repair_manual_url',  'type' => 'url' ),
			'eurocomply_r2r_eprel_id'           => array( 'key' => '_eurocomply_r2r_eprel_id',           'type' => 'short_text' ),
			'eurocomply_r2r_warranty_years'     => array( 'key' => '_eurocomply_r2r_warranty_years',     'type' => 'int_10' ),
		);

		foreach ( $map as $field => $conf ) {
			$raw = isset( $_POST[ $field ] ) ? wp_unslash( (string) $_POST[ $field ] ) : '';
			switch ( $conf['type'] ) {
				case 'category':
					$val = sanitize_key( $raw );
					if ( ! isset( Settings::product_categories()[ $val ] ) ) {
						$val = '';
					}
					break;
				case 'energy_class':
					$val = strtoupper( $raw );
					if ( ! isset( Settings::energy_classes()[ $val ] ) ) {
						$val = '';
					}
					break;
				case 'int_nonneg':
					$val = '' === $raw ? '' : (string) max( 0, (int) $raw );
					break;
				case 'int_10':
					$val = '' === $raw ? '' : (string) max( 0, min( 10, (int) $raw ) );
					break;
				case 'int_15':
					$val = '' === $raw ? '' : (string) max( 0, min( 15, (int) $raw ) );
					break;
				case 'score_10':
					if ( '' === $raw ) {
						$val = '';
					} else {
						$num = (float) str_replace( ',', '.', $raw );
						$num = max( 0.0, min( 10.0, $num ) );
						$val = number_format( $num, 1, '.', '' );
					}
					break;
				case 'url':
					$val = esc_url_raw( $raw );
					break;
				case 'short_text':
					$val = substr( sanitize_text_field( $raw ), 0, 64 );
					break;
				default:
					$val = sanitize_text_field( $raw );
			}

			if ( '' === $val ) {
				delete_post_meta( $post_id, $conf['key'] );
			} else {
				update_post_meta( $post_id, $conf['key'], $val );
			}
		}
	}

	/**
	 * Pull all R2R meta for a product in a single call.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_for_product( int $product_id ) : array {
		$s          = Settings::get();
		$cat        = (string) get_post_meta( $product_id, '_eurocomply_r2r_category', true );
		$categories = Settings::product_categories();
		if ( '' === $cat ) {
			$cat = (string) $s['default_product_category'];
		}
		$category_info = $categories[ $cat ] ?? $categories['not_applicable'];

		$years_raw = get_post_meta( $product_id, '_eurocomply_r2r_spare_parts_years', true );
		$years     = '' === $years_raw ? (int) $category_info['spare_parts_years'] : (int) $years_raw;

		$warranty_raw = get_post_meta( $product_id, '_eurocomply_r2r_warranty_years', true );
		$warranty     = '' === $warranty_raw ? (int) $s['default_warranty_years'] : (int) $warranty_raw;

		return array(
			'category'          => $cat,
			'category_label'    => (string) $category_info['label'],
			'energy_class'      => (string) get_post_meta( $product_id, '_eurocomply_r2r_energy_class', true ),
			'energy_kwh'        => (int) get_post_meta( $product_id, '_eurocomply_r2r_energy_kwh', true ),
			'repair_index'      => (string) get_post_meta( $product_id, '_eurocomply_r2r_repair_index', true ),
			'disassembly_score' => (string) get_post_meta( $product_id, '_eurocomply_r2r_disassembly_score', true ),
			'spare_parts_years' => $years,
			'spare_parts_url'   => (string) get_post_meta( $product_id, '_eurocomply_r2r_spare_parts_url', true ),
			'repair_manual_url' => (string) get_post_meta( $product_id, '_eurocomply_r2r_repair_manual_url', true ),
			'eprel_id'          => (string) get_post_meta( $product_id, '_eurocomply_r2r_eprel_id', true ),
			'warranty_years'    => $warranty,
		);
	}
}
