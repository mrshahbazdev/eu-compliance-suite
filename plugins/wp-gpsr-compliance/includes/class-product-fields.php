<?php
/**
 * WooCommerce product "GPSR" metabox + save handling.
 *
 * Stores GPSR traceability fields (manufacturer / importer / EU Responsible Person /
 * warnings / batch) as post meta on `product` and `product_variation` posts.
 *
 * @package EuroComply\Gpsr
 */

declare( strict_types = 1 );

namespace EuroComply\Gpsr;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ProductFields {

	public const NONCE_ACTION = 'eurocomply_gpsr_save_product';
	public const NONCE_NAME   = 'eurocomply_gpsr_nonce';

	/**
	 * @var array<int,array{key:string,label:string,type:string,required:bool,help:string}>
	 */
	public const FIELDS = array(
		array(
			'key'      => '_gpsr_manufacturer_name',
			'label'    => 'Manufacturer name',
			'type'     => 'text',
			'required' => true,
			'help'     => 'Legal name of the manufacturer (Art. 9 GPSR).',
		),
		array(
			'key'      => '_gpsr_manufacturer_address',
			'label'    => 'Manufacturer postal address',
			'type'     => 'textarea',
			'required' => true,
			'help'     => 'Postal address + single point of contact (e-mail or URL).',
		),
		array(
			'key'      => '_gpsr_importer_name',
			'label'    => 'Importer name',
			'type'     => 'text',
			'required' => false,
			'help'     => 'Required if the manufacturer is outside the EU and you import.',
		),
		array(
			'key'      => '_gpsr_importer_address',
			'label'    => 'Importer postal address',
			'type'     => 'textarea',
			'required' => false,
			'help'     => '',
		),
		array(
			'key'      => '_gpsr_eu_rep_name',
			'label'    => 'EU Responsible Person',
			'type'     => 'text',
			'required' => false,
			'help'     => 'Required under Art. 4 Reg. (EU) 2019/1020 if neither manufacturer nor importer is established in the EU.',
		),
		array(
			'key'      => '_gpsr_eu_rep_address',
			'label'    => 'EU Responsible Person address',
			'type'     => 'textarea',
			'required' => false,
			'help'     => '',
		),
		array(
			'key'      => '_gpsr_warnings',
			'label'    => 'Warnings / safety information',
			'type'     => 'textarea',
			'required' => false,
			'help'     => 'Warnings, cautions, age limits, instructions for safe use. Rendered on the product page.',
		),
		array(
			'key'      => '_gpsr_batch',
			'label'    => 'Batch / lot / serial number',
			'type'     => 'text',
			'required' => false,
			'help'     => 'Traceability identifier for recall management.',
		),
	);

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
		add_action( 'save_post_product', array( $this, 'save_product' ), 10, 2 );
	}

	public function register_metabox() : void {
		add_meta_box(
			'eurocomply-gpsr',
			__( 'GPSR compliance (EU product safety)', 'eurocomply-gpsr' ),
			array( $this, 'render_metabox' ),
			'product',
			'normal',
			'default'
		);
	}

	/**
	 * @param \WP_Post $post Product post.
	 */
	public function render_metabox( \WP_Post $post ) : void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		echo '<table class="form-table" role="presentation"><tbody>';
		foreach ( self::FIELDS as $field ) {
			$value = (string) get_post_meta( $post->ID, $field['key'], true );
			$id    = 'eurocomply_gpsr_' . sanitize_key( $field['key'] );
			$name  = 'eurocomply_gpsr[' . $field['key'] . ']';
			$label = $field['label'] . ( $field['required'] ? ' *' : '' );

			echo '<tr>';
			echo '<th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th>';
			echo '<td>';
			if ( 'textarea' === $field['type'] ) {
				echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="3" class="large-text code">' . esc_textarea( $value ) . '</textarea>';
			} else {
				echo '<input type="text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="regular-text" />';
			}
			if ( ! empty( $field['help'] ) ) {
				echo '<p class="description">' . esc_html( $field['help'] ) . '</p>';
			}
			echo '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
		echo '<p class="description">' . esc_html__( 'Required fields marked with *. Leave importer / EU-Rep blank if your manufacturer is EU-established.', 'eurocomply-gpsr' ) . '</p>';
	}

	public function save_product( int $post_id, \WP_Post $post ) : void {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$raw = isset( $_POST['eurocomply_gpsr'] ) && is_array( $_POST['eurocomply_gpsr'] )
			? wp_unslash( $_POST['eurocomply_gpsr'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per-field below.
			: array();

		$allowed_keys = array_column( self::FIELDS, 'type', 'key' );

		foreach ( $allowed_keys as $key => $type ) {
			$value = isset( $raw[ $key ] ) ? (string) $raw[ $key ] : '';
			$value = 'textarea' === $type ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
			if ( '' === $value ) {
				delete_post_meta( $post_id, $key );
			} else {
				update_post_meta( $post_id, $key, $value );
			}
		}
	}

	/**
	 * Resolve the effective value for a field: product meta falls back to shop-wide defaults
	 * from Settings::get() if inherit_defaults is on.
	 */
	public static function resolve( int $product_id, string $meta_key ) : string {
		$value = (string) get_post_meta( $product_id, $meta_key, true );
		if ( '' !== $value ) {
			return $value;
		}
		$settings = Settings::get();
		if ( empty( $settings['inherit_defaults'] ) ) {
			return '';
		}
		$map = array(
			'_gpsr_manufacturer_name'    => 'default_manufacturer_name',
			'_gpsr_manufacturer_address' => 'default_manufacturer_address',
			'_gpsr_importer_name'        => 'default_importer_name',
			'_gpsr_importer_address'     => 'default_importer_address',
			'_gpsr_eu_rep_name'          => 'default_eu_rep_name',
			'_gpsr_eu_rep_address'       => 'default_eu_rep_address',
		);
		if ( ! isset( $map[ $meta_key ] ) ) {
			return '';
		}
		return isset( $settings[ $map[ $meta_key ] ] ) ? (string) $settings[ $map[ $meta_key ] ] : '';
	}
}
