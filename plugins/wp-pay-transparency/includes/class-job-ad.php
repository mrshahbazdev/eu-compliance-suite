<?php
/**
 * Art. 5 — pay-range disclosure on job ads.
 *
 * Hooks `the_content` for any post type listed in the settings to inject the
 * pay range badge if the post has the meta fields set; admin sidebar metabox
 * lets editors enter min/max + currency + period; saves with a nonce.
 *
 * @package EuroComply\PayTransparency
 */

declare( strict_types = 1 );

namespace EuroComply\PayTransparency;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class JobAd {

	private const META_MIN      = '_eurocomply_pt_pay_min';
	private const META_MAX      = '_eurocomply_pt_pay_max';
	private const META_CURRENCY = '_eurocomply_pt_pay_currency';
	private const META_PERIOD   = '_eurocomply_pt_pay_period';
	private const META_CATEGORY = '_eurocomply_pt_category';
	private const NONCE         = 'eurocomply_pt_jobad_save';

	private static ?JobAd $instance = null;

	public static function instance() : JobAd {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register();
		}
		return self::$instance;
	}

	private function register() : void {
		add_action( 'add_meta_boxes', array( $this, 'add_metabox' ) );
		add_action( 'save_post',      array( $this, 'save_meta' ), 10, 2 );
		add_filter( 'the_content',    array( $this, 'inject_badge' ), 9 );
	}

	public function add_metabox() : void {
		$types = (array) Settings::get()['job_post_types'];
		foreach ( $types as $type ) {
			add_meta_box(
				'eurocomply-pt-jobad',
				__( 'Pay transparency (Art. 5)', 'eurocomply-pay-transparency' ),
				array( $this, 'render_metabox' ),
				$type,
				'side',
				'default'
			);
		}
	}

	public function render_metabox( \WP_Post $post ) : void {
		wp_nonce_field( self::NONCE, 'eurocomply_pt_jobad_nonce' );
		$min      = (string) get_post_meta( $post->ID, self::META_MIN, true );
		$max      = (string) get_post_meta( $post->ID, self::META_MAX, true );
		$currency = (string) get_post_meta( $post->ID, self::META_CURRENCY, true );
		$period   = (string) get_post_meta( $post->ID, self::META_PERIOD, true );
		$category = (string) get_post_meta( $post->ID, self::META_CATEGORY, true );
		if ( '' === $currency ) {
			$currency = (string) Settings::get()['currency'];
		}
		if ( '' === $period ) {
			$period = 'year';
		}
		echo '<p><label>' . esc_html__( 'Min pay', 'eurocomply-pay-transparency' ) . '<br />';
		echo '<input type="number" step="0.01" min="0" name="eurocomply_pt[pay_min]" value="' . esc_attr( $min ) . '" class="widefat" /></label></p>';
		echo '<p><label>' . esc_html__( 'Max pay', 'eurocomply-pay-transparency' ) . '<br />';
		echo '<input type="number" step="0.01" min="0" name="eurocomply_pt[pay_max]" value="' . esc_attr( $max ) . '" class="widefat" /></label></p>';
		echo '<p><label>' . esc_html__( 'Currency (ISO 4217)', 'eurocomply-pay-transparency' ) . '<br />';
		echo '<input type="text" maxlength="3" name="eurocomply_pt[currency]" value="' . esc_attr( strtoupper( $currency ) ) . '" class="widefat" /></label></p>';
		echo '<p><label>' . esc_html__( 'Period', 'eurocomply-pay-transparency' ) . '<br />';
		echo '<select name="eurocomply_pt[period]" class="widefat">';
		foreach ( array( 'year' => __( 'per year',  'eurocomply-pay-transparency' ),
		                 'month' => __( 'per month', 'eurocomply-pay-transparency' ),
		                 'hour'  => __( 'per hour',  'eurocomply-pay-transparency' ) ) as $val => $label ) {
			echo '<option value="' . esc_attr( $val ) . '"' . selected( $period, $val, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></label></p>';

		echo '<p><label>' . esc_html__( 'Pay category', 'eurocomply-pay-transparency' ) . '<br />';
		echo '<select name="eurocomply_pt[category]" class="widefat">';
		echo '<option value="">' . esc_html__( '— None —', 'eurocomply-pay-transparency' ) . '</option>';
		foreach ( CategoryStore::all() as $cat ) {
			echo '<option value="' . esc_attr( (string) $cat['slug'] ) . '"' . selected( $category, (string) $cat['slug'], false ) . '>' . esc_html( (string) $cat['name'] ) . '</option>';
		}
		echo '</select></label></p>';
	}

	/**
	 * @param int $post_id
	 * @param \WP_Post $post
	 */
	public function save_meta( int $post_id, \WP_Post $post ) : void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST['eurocomply_pt_jobad_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( (string) wp_unslash( $_POST['eurocomply_pt_jobad_nonce'] ), self::NONCE ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$types = (array) Settings::get()['job_post_types'];
		if ( ! in_array( (string) $post->post_type, $types, true ) ) {
			return;
		}
		$input = isset( $_POST['eurocomply_pt'] ) && is_array( $_POST['eurocomply_pt'] )
			? wp_unslash( (array) $_POST['eurocomply_pt'] )
			: array();

		$min  = isset( $input['pay_min'] ) ? max( 0.0, (float) $input['pay_min'] ) : 0.0;
		$max  = isset( $input['pay_max'] ) ? max( 0.0, (float) $input['pay_max'] ) : 0.0;
		$cur  = isset( $input['currency'] ) ? strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', (string) $input['currency'] ), 0, 3 ) ) : '';
		$per  = isset( $input['period'] ) && in_array( (string) $input['period'], array( 'year', 'month', 'hour' ), true ) ? (string) $input['period'] : 'year';
		$cat  = isset( $input['category'] ) ? sanitize_key( (string) $input['category'] ) : '';

		update_post_meta( $post_id, self::META_MIN,      number_format( $min, 2, '.', '' ) );
		update_post_meta( $post_id, self::META_MAX,      number_format( $max, 2, '.', '' ) );
		update_post_meta( $post_id, self::META_CURRENCY, $cur );
		update_post_meta( $post_id, self::META_PERIOD,   $per );
		update_post_meta( $post_id, self::META_CATEGORY, $cat );
	}

	public function inject_badge( string $content ) : string {
		if ( ! is_singular() ) {
			return $content;
		}
		$settings = Settings::get();
		if ( empty( $settings['enable_job_ad_filter'] ) ) {
			return $content;
		}
		$post = get_post();
		if ( ! $post instanceof \WP_Post ) {
			return $content;
		}
		if ( ! in_array( (string) $post->post_type, (array) $settings['job_post_types'], true ) ) {
			return $content;
		}
		$min      = (float) get_post_meta( $post->ID, self::META_MIN, true );
		$max      = (float) get_post_meta( $post->ID, self::META_MAX, true );
		$currency = (string) get_post_meta( $post->ID, self::META_CURRENCY, true );
		$period   = (string) get_post_meta( $post->ID, self::META_PERIOD, true );

		if ( $min <= 0.0 && $max <= 0.0 ) {
			if ( ! empty( $settings['pay_range_required'] ) && current_user_can( 'edit_post', $post->ID ) ) {
				return '<div class="eurocomply-pt-warning">' . esc_html__( 'Art. 5 reminder: this job post does not yet declare a pay range. Set min/max in the Pay transparency sidebar.', 'eurocomply-pay-transparency' ) . '</div>' . $content;
			}
			return $content;
		}

		$badge = self::format_badge( $min, $max, $currency, $period );
		return '<div class="eurocomply-pt-pay-badge">' . $badge . '</div>' . $content;
	}

	public static function format_badge( float $min, float $max, string $currency, string $period ) : string {
		$currency = '' !== $currency ? $currency : (string) Settings::get()['currency'];
		$period_label = array(
			'year'  => __( 'per year',  'eurocomply-pay-transparency' ),
			'month' => __( 'per month', 'eurocomply-pay-transparency' ),
			'hour'  => __( 'per hour',  'eurocomply-pay-transparency' ),
		)[ $period ] ?? __( 'per year', 'eurocomply-pay-transparency' );
		if ( $min > 0.0 && $max > 0.0 && $max > $min ) {
			$range = number_format( $min, 2 ) . '–' . number_format( $max, 2 );
		} elseif ( $max > 0.0 ) {
			$range = number_format( $max, 2 );
		} else {
			$range = number_format( $min, 2 );
		}
		return sprintf(
			/* translators: 1: pay range, 2: currency, 3: period */
			'<strong>%s</strong> %s %s %s',
			esc_html__( 'Pay (Art. 5):', 'eurocomply-pay-transparency' ),
			esc_html( $range ),
			esc_html( $currency ),
			esc_html( $period_label )
		);
	}
}
