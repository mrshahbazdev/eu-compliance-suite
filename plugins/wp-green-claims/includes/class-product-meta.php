<?php
/**
 * Per-product CRD Art. 5a disclosures (durability, software updates, repairability).
 *
 * @package EuroComply\GreenClaims
 */

declare( strict_types = 1 );

namespace EuroComply\GreenClaims;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ProductMeta {

	private const META_DURABILITY  = '_eurocomply_gc_durability_months';
	private const META_SOFTWARE    = '_eurocomply_gc_software_update_years';
	private const META_REPAIR      = '_eurocomply_gc_repairability_score';
	private const META_GUARANTEE   = '_eurocomply_gc_guarantee_extended';

	private static ?ProductMeta $instance = null;

	public static function instance() : ProductMeta {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register();
		}
		return self::$instance;
	}

	private function register() : void {
		add_action( 'add_meta_boxes', array( $this, 'add_box' ) );
		add_action( 'save_post', array( $this, 'save_box' ), 10, 2 );
		add_action( 'wp_head', array( $this, 'inject_schema' ), 11 );
	}

	public function add_box() : void {
		$post_types = apply_filters( 'eurocomply_gc_post_types', array( 'post', 'page', 'product' ) );
		foreach ( (array) $post_types as $pt ) {
			add_meta_box( 'eurocomply_gc_box', __( 'EuroComply Green Claims (CRD Art. 5a)', 'eurocomply-green-claims' ), array( $this, 'render_box' ), (string) $pt, 'side', 'low' );
		}
	}

	public function render_box( \WP_Post $post ) : void {
		wp_nonce_field( 'eurocomply_gc_box_' . $post->ID, '_eurocomply_gc_box_nonce' );
		$d  = Settings::get();
		$dm = (int) get_post_meta( $post->ID, self::META_DURABILITY, true );
		$sw = (int) get_post_meta( $post->ID, self::META_SOFTWARE, true );
		$rs = (int) get_post_meta( $post->ID, self::META_REPAIR, true );
		$ge = (int) get_post_meta( $post->ID, self::META_GUARANTEE, true );
		if ( 0 === $dm ) {
			$dm = (int) $d['default_durability_m'];
		}
		if ( 0 === $sw ) {
			$sw = (int) $d['default_software_y'];
		}
		echo '<p><label>' . esc_html__( 'Expected durability (months)', 'eurocomply-green-claims' ) . '<br>';
		printf( '<input type="number" name="eurocomply_gc_durability" min="0" max="600" value="%d" class="widefat"></label></p>', $dm );
		echo '<p><label>' . esc_html__( 'Software / security updates (years)', 'eurocomply-green-claims' ) . '<br>';
		printf( '<input type="number" name="eurocomply_gc_software" min="0" max="50" value="%d" class="widefat"></label></p>', $sw );
		echo '<p><label>' . esc_html__( 'Repairability score (0–10)', 'eurocomply-green-claims' ) . '<br>';
		printf( '<input type="number" name="eurocomply_gc_repair" min="0" max="10" value="%d" class="widefat"></label></p>', $rs );
		echo '<p><label>' . esc_html__( 'Extended commercial guarantee (months)', 'eurocomply-green-claims' ) . '<br>';
		printf( '<input type="number" name="eurocomply_gc_guarantee" min="0" max="600" value="%d" class="widefat"></label></p>', $ge );
	}

	public function save_box( int $post_id, \WP_Post $post ) : void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( empty( $_POST['_eurocomply_gc_box_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['_eurocomply_gc_box_nonce'] ) ), 'eurocomply_gc_box_' . $post_id ) ) {
			return;
		}
		foreach ( array(
			self::META_DURABILITY => 'eurocomply_gc_durability',
			self::META_SOFTWARE   => 'eurocomply_gc_software',
			self::META_REPAIR     => 'eurocomply_gc_repair',
			self::META_GUARANTEE  => 'eurocomply_gc_guarantee',
		) as $meta_key => $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				$val = max( 0, (int) $_POST[ $field ] );
				if ( self::META_REPAIR === $meta_key ) {
					$val = min( 10, $val );
				}
				update_post_meta( $post_id, $meta_key, $val );
			}
		}
	}

	public function inject_schema() : void {
		if ( ! is_singular() ) {
			return;
		}
		$post_id = (int) get_queried_object_id();
		if ( ! $post_id ) {
			return;
		}
		$dm = (int) get_post_meta( $post_id, self::META_DURABILITY, true );
		$sw = (int) get_post_meta( $post_id, self::META_SOFTWARE, true );
		$rs = (int) get_post_meta( $post_id, self::META_REPAIR, true );
		if ( 0 === $dm && 0 === $sw && 0 === $rs ) {
			return;
		}
		$data = array(
			'@context'           => 'https://schema.org/',
			'@type'              => 'Product',
			'name'               => get_the_title( $post_id ),
			'additionalProperty' => array(),
		);
		if ( $dm > 0 ) {
			$data['additionalProperty'][] = array( '@type' => 'PropertyValue', 'name' => 'durability_months', 'value' => $dm );
		}
		if ( $sw > 0 ) {
			$data['additionalProperty'][] = array( '@type' => 'PropertyValue', 'name' => 'software_update_years', 'value' => $sw );
		}
		if ( $rs > 0 ) {
			$data['additionalProperty'][] = array( '@type' => 'PropertyValue', 'name' => 'repairability_score', 'value' => $rs, 'maxValue' => 10 );
		}
		echo '<script type="application/ld+json">' . wp_json_encode( $data ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public static function get_meta( int $post_id ) : array {
		return array(
			'durability_months'    => (int) get_post_meta( $post_id, self::META_DURABILITY, true ),
			'software_update_years' => (int) get_post_meta( $post_id, self::META_SOFTWARE, true ),
			'repairability_score'  => (int) get_post_meta( $post_id, self::META_REPAIR, true ),
			'guarantee_months'     => (int) get_post_meta( $post_id, self::META_GUARANTEE, true ),
		);
	}
}
