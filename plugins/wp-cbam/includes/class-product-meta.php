<?php
/**
 * Per-product CBAM metabox (CN-8 + emissions data).
 *
 * @package EuroComply\CBAM
 */

declare( strict_types = 1 );

namespace EuroComply\CBAM;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ProductMeta {

	private const META_CN          = '_eurocomply_cbam_cn8';
	private const META_COUNTRY     = '_eurocomply_cbam_origin_country';
	private const META_DIRECT      = '_eurocomply_cbam_direct_tco2e';
	private const META_INDIRECT    = '_eurocomply_cbam_indirect_tco2e';
	private const META_PRODUCTION  = '_eurocomply_cbam_production_route';
	private const META_VERIFIED    = '_eurocomply_cbam_verified';
	private const META_SUPPLIER    = '_eurocomply_cbam_supplier';
	private const NONCE            = 'eurocomply_cbam_product';

	public const META_KEYS = array(
		self::META_CN,
		self::META_COUNTRY,
		self::META_DIRECT,
		self::META_INDIRECT,
		self::META_PRODUCTION,
		self::META_VERIFIED,
		self::META_SUPPLIER,
	);

	private static ?ProductMeta $instance = null;

	public static function instance() : ProductMeta {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register();
		}
		return self::$instance;
	}

	private function register() : void {
		if ( ! Settings::get()['enable_product_meta'] ) {
			return;
		}
		add_action( 'add_meta_boxes',      array( $this, 'add_metabox' ) );
		add_action( 'save_post_product',   array( $this, 'save_meta' ), 10, 2 );
		add_filter( 'the_content',         array( $this, 'append_emissions' ), 9 );
	}

	public function add_metabox() : void {
		$types = array( 'product' );
		foreach ( $types as $type ) {
			add_meta_box(
				'eurocomply-cbam-product',
				__( 'CBAM (embedded emissions)', 'eurocomply-cbam' ),
				array( $this, 'render_metabox' ),
				$type,
				'side',
				'default'
			);
		}
	}

	public function render_metabox( \WP_Post $post ) : void {
		wp_nonce_field( self::NONCE, 'eurocomply_cbam_product_nonce' );
		$cn         = (string) get_post_meta( $post->ID, self::META_CN,         true );
		$country    = (string) get_post_meta( $post->ID, self::META_COUNTRY,    true );
		$direct     = (string) get_post_meta( $post->ID, self::META_DIRECT,     true );
		$indirect   = (string) get_post_meta( $post->ID, self::META_INDIRECT,   true );
		$route      = (string) get_post_meta( $post->ID, self::META_PRODUCTION, true );
		$verified   = (int) get_post_meta( $post->ID, self::META_VERIFIED,    true );
		$supplier   = (string) get_post_meta( $post->ID, self::META_SUPPLIER,   true );

		$cat = '' !== $cn ? CbamRegistry::category_for_cn( $cn ) : '';

		echo '<p><label>' . esc_html__( 'CN-8 code', 'eurocomply-cbam' ) . '<br />';
		echo '<input type="text" name="eurocomply_cbam[cn]" value="' . esc_attr( $cn ) . '" maxlength="8" pattern="[0-9]{8}" class="widefat" /></label></p>';

		if ( '' !== $cat ) {
			echo '<p><span class="eurocomply-cbam-pill ok">' . esc_html__( 'Category', 'eurocomply-cbam' ) . ': ' . esc_html( $cat ) . '</span></p>';
		} elseif ( '' !== $cn ) {
			echo '<p><span class="eurocomply-cbam-pill warn">' . esc_html__( 'CN-8 not in CBAM scope (or unmapped — Pro TARIC sync extends mapping).', 'eurocomply-cbam' ) . '</span></p>';
		}

		echo '<p><label>' . esc_html__( 'Country of origin (ISO-2)', 'eurocomply-cbam' ) . '<br />';
		echo '<input type="text" name="eurocomply_cbam[country]" value="' . esc_attr( $country ) . '" maxlength="2" class="widefat" /></label></p>';

		echo '<p><label>' . esc_html__( 'Direct emissions (tCO2e per unit)', 'eurocomply-cbam' ) . '<br />';
		echo '<input type="number" step="0.0001" min="0" name="eurocomply_cbam[direct]" value="' . esc_attr( $direct ) . '" class="widefat" /></label></p>';

		echo '<p><label>' . esc_html__( 'Indirect emissions (tCO2e per unit)', 'eurocomply-cbam' ) . '<br />';
		echo '<input type="number" step="0.0001" min="0" name="eurocomply_cbam[indirect]" value="' . esc_attr( $indirect ) . '" class="widefat" /></label></p>';

		echo '<p><label>' . esc_html__( 'Production route', 'eurocomply-cbam' ) . '<br />';
		echo '<input type="text" name="eurocomply_cbam[route]" value="' . esc_attr( $route ) . '" class="widefat" /></label></p>';

		echo '<p><label>' . esc_html__( 'Supplier (installation)', 'eurocomply-cbam' ) . '<br />';
		echo '<input type="text" name="eurocomply_cbam[supplier]" value="' . esc_attr( $supplier ) . '" class="widefat" /></label></p>';

		echo '<p><label><input type="checkbox" name="eurocomply_cbam[verified]" value="1"' . checked( 1, $verified, false ) . ' /> ' . esc_html__( 'Data verified by accredited verifier', 'eurocomply-cbam' ) . '</label></p>';
	}

	/**
	 * @param int $post_id
	 * @param \WP_Post $post
	 */
	public function save_meta( int $post_id, \WP_Post $post ) : void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST['eurocomply_cbam_product_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( (string) wp_unslash( $_POST['eurocomply_cbam_product_nonce'] ), self::NONCE ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$input = isset( $_POST['eurocomply_cbam'] ) && is_array( $_POST['eurocomply_cbam'] ) ? wp_unslash( (array) $_POST['eurocomply_cbam'] ) : array();

		$cn       = preg_replace( '/[^0-9]/', '', (string) ( $input['cn'] ?? '' ) );
		$country  = strtoupper( preg_replace( '/[^A-Z]/', '', (string) ( $input['country'] ?? '' ) ) );
		$country  = substr( $country, 0, 2 );
		$direct   = max( 0.0, (float) ( $input['direct']   ?? 0 ) );
		$indirect = max( 0.0, (float) ( $input['indirect'] ?? 0 ) );
		$route    = sanitize_text_field( (string) ( $input['route']    ?? '' ) );
		$supplier = sanitize_text_field( (string) ( $input['supplier'] ?? '' ) );
		$verified = ! empty( $input['verified'] ) ? 1 : 0;

		update_post_meta( $post_id, self::META_CN,         $cn );
		update_post_meta( $post_id, self::META_COUNTRY,    $country );
		update_post_meta( $post_id, self::META_DIRECT,     number_format( $direct, 4, '.', '' ) );
		update_post_meta( $post_id, self::META_INDIRECT,   number_format( $indirect, 4, '.', '' ) );
		update_post_meta( $post_id, self::META_PRODUCTION, $route );
		update_post_meta( $post_id, self::META_SUPPLIER,   $supplier );
		update_post_meta( $post_id, self::META_VERIFIED,   $verified );
	}

	public function append_emissions( string $content ) : string {
		if ( ! is_singular( 'product' ) ) {
			return $content;
		}
		if ( ! Settings::get()['show_emissions_on_frontend'] ) {
			return $content;
		}
		$post = get_post();
		if ( ! $post instanceof \WP_Post ) {
			return $content;
		}
		$cn = (string) get_post_meta( $post->ID, self::META_CN, true );
		if ( '' === $cn ) {
			return $content;
		}
		$cat      = CbamRegistry::category_for_cn( $cn );
		if ( '' === $cat ) {
			return $content;
		}
		$direct   = (float) get_post_meta( $post->ID, self::META_DIRECT, true );
		$indirect = (float) get_post_meta( $post->ID, self::META_INDIRECT, true );
		$country  = (string) get_post_meta( $post->ID, self::META_COUNTRY, true );
		$verified = (int) get_post_meta( $post->ID, self::META_VERIFIED, true );

		$total  = $direct + $indirect;
		$badge  = '<div class="eurocomply-cbam-card">';
		$badge .= '<strong>' . esc_html__( 'CBAM embedded emissions', 'eurocomply-cbam' ) . '</strong> · ';
		$badge .= esc_html( ucfirst( str_replace( '_', ' ', $cat ) ) );
		$badge .= ' · ' . esc_html( number_format( $total, 4 ) ) . ' tCO₂e';
		if ( '' !== $country ) {
			$badge .= ' · ' . esc_html__( 'Origin', 'eurocomply-cbam' ) . ' ' . esc_html( $country );
		}
		$badge .= ' · ' . ( $verified ? '<span class="eurocomply-cbam-pill ok">' . esc_html__( 'Verified', 'eurocomply-cbam' ) . '</span>' : '<span class="eurocomply-cbam-pill warn">' . esc_html__( 'Default values', 'eurocomply-cbam' ) . '</span>' );
		$badge .= '</div>';

		return $badge . $content;
	}
}
