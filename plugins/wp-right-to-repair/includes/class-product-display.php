<?php
/**
 * Frontend display hooks (single product + shop grid).
 *
 * @package EuroComply\R2R
 */

declare( strict_types = 1 );

namespace EuroComply\R2R;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ProductDisplay {

	private static ?ProductDisplay $instance = null;

	public static function instance() : ProductDisplay {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register();
		}
		return self::$instance;
	}

	private function register() : void {
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
		$s = Settings::get();
		if ( ! empty( $s['show_repair_tab'] ) ) {
			add_filter( 'woocommerce_product_tabs', array( $this, 'register_tab' ) );
		}
		if ( ! empty( $s['show_energy_badge'] ) || ! empty( $s['show_repair_score_badge'] ) || ! empty( $s['show_spare_parts_years'] ) ) {
			add_action( 'woocommerce_single_product_summary', array( $this, 'render_summary_badges' ), 25 );
		}
		if ( ! empty( $s['show_on_shop_grid'] ) ) {
			add_action( 'woocommerce_after_shop_loop_item_title', array( $this, 'render_loop_badges' ), 8 );
		}
	}

	public function assets() : void {
		wp_enqueue_style( 'eurocomply-r2r-public', EUROCOMPLY_R2R_URL . 'assets/css/public.css', array(), EUROCOMPLY_R2R_VERSION );
	}

	/**
	 * @param array<string,array<string,mixed>> $tabs
	 * @return array<string,array<string,mixed>>
	 */
	public function register_tab( array $tabs ) : array {
		global $post;
		if ( ! $post instanceof \WP_Post ) {
			return $tabs;
		}
		$meta = ProductMeta::get_for_product( (int) $post->ID );
		if ( 'not_applicable' === $meta['category'] && '' === $meta['energy_class'] && '' === $meta['repair_index'] ) {
			return $tabs;
		}
		$tabs['eurocomply_r2r'] = array(
			'title'    => __( 'Repair & parts', 'eurocomply-r2r' ),
			'priority' => 40,
			'callback' => array( $this, 'render_tab' ),
		);
		return $tabs;
	}

	public function render_tab() : void {
		global $post;
		if ( ! $post instanceof \WP_Post ) {
			return;
		}
		$meta = ProductMeta::get_for_product( (int) $post->ID );
		$s    = Settings::get();

		echo '<h2>' . esc_html__( 'Right to Repair & Energy information', 'eurocomply-r2r' ) . '</h2>';
		echo '<table class="eurocomply-r2r-spec">';
		echo '<tbody>';

		$row = function ( string $label, string $value ) : void {
			echo '<tr><th>' . esc_html( $label ) . '</th><td>' . wp_kses_post( $value ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		};

		if ( '' !== $meta['category_label'] ) {
			$row( __( 'Product category', 'eurocomply-r2r' ), esc_html( $meta['category_label'] ) );
		}
		if ( '' !== $meta['energy_class'] ) {
			$value = self::energy_badge_html( $meta['energy_class'] );
			if ( $meta['energy_kwh'] > 0 ) {
				$value .= ' <span class="eurocomply-r2r-kwh">' . esc_html( sprintf( /* translators: %d: kWh */ __( '%d kWh / year', 'eurocomply-r2r' ), $meta['energy_kwh'] ) ) . '</span>';
			}
			$row( __( 'Energy class', 'eurocomply-r2r' ), $value );
		}
		if ( '' !== $meta['repair_index'] ) {
			$row( __( 'Reparability score', 'eurocomply-r2r' ), self::score_badge_html( $meta['repair_index'] ) . ' / 10' );
		}
		if ( '' !== $meta['disassembly_score'] ) {
			$row( __( 'Disassembly ease', 'eurocomply-r2r' ), esc_html( $meta['disassembly_score'] ) . ' / 10' );
		}
		if ( $meta['spare_parts_years'] > 0 ) {
			$row( __( 'Spare parts guaranteed', 'eurocomply-r2r' ), esc_html( sprintf( /* translators: %d: years */ _n( '%d year', '%d years', $meta['spare_parts_years'], 'eurocomply-r2r' ), $meta['spare_parts_years'] ) ) );
		}
		if ( '' !== $meta['spare_parts_url'] ) {
			$row( __( 'Spare parts catalogue', 'eurocomply-r2r' ), sprintf( '<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>', esc_url( $meta['spare_parts_url'] ), esc_html__( 'Open catalogue', 'eurocomply-r2r' ) ) );
		}
		if ( '' !== $meta['repair_manual_url'] ) {
			$row( __( 'Repair manual', 'eurocomply-r2r' ), sprintf( '<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>', esc_url( $meta['repair_manual_url'] ), esc_html__( 'Open manual', 'eurocomply-r2r' ) ) );
		}
		if ( '' !== $meta['eprel_id'] ) {
			$row( __( 'EPREL', 'eurocomply-r2r' ), sprintf( '<a href="https://eprel.ec.europa.eu/screen/product/%1$s" target="_blank" rel="noopener noreferrer">%1$s</a>', esc_html( $meta['eprel_id'] ) ) );
		}
		if ( $meta['warranty_years'] > 0 ) {
			$row( __( 'Warranty', 'eurocomply-r2r' ), esc_html( sprintf( /* translators: %d: years */ _n( '%d year', '%d years', $meta['warranty_years'], 'eurocomply-r2r' ), $meta['warranty_years'] ) ) );
		}
		if ( ! empty( $s['repair_contact_email'] ) ) {
			$row( __( 'Repair support contact', 'eurocomply-r2r' ), sprintf( '<a href="mailto:%1$s">%1$s</a>', esc_attr( (string) $s['repair_contact_email'] ) ) );
		}

		echo '</tbody></table>';

		if ( ! empty( $s['policy_url'] ) ) {
			printf(
				'<p class="eurocomply-r2r-policy"><a href="%1$s">%2$s</a></p>',
				esc_url( (string) $s['policy_url'] ),
				esc_html__( 'Repair, parts and energy policy', 'eurocomply-r2r' )
			);
		}
	}

	public function render_summary_badges() : void {
		global $post;
		if ( ! $post instanceof \WP_Post ) {
			return;
		}
		$meta = ProductMeta::get_for_product( (int) $post->ID );
		$s    = Settings::get();

		$badges = array();
		if ( ! empty( $s['show_energy_badge'] ) && '' !== $meta['energy_class'] ) {
			$badges[] = self::energy_badge_html( $meta['energy_class'] );
		}
		if ( ! empty( $s['show_repair_score_badge'] ) && '' !== $meta['repair_index'] ) {
			$badges[] = self::score_badge_html( $meta['repair_index'] );
		}
		if ( ! empty( $s['show_spare_parts_years'] ) && $meta['spare_parts_years'] > 0 ) {
			$badges[] = sprintf(
				'<span class="eurocomply-r2r-badge eurocomply-r2r-badge--parts">%s</span>',
				esc_html( sprintf( /* translators: %d: years */ _n( '%d-year spare parts', '%d-year spare parts', $meta['spare_parts_years'], 'eurocomply-r2r' ), $meta['spare_parts_years'] ) )
			);
		}
		if ( empty( $badges ) ) {
			return;
		}
		echo '<div class="eurocomply-r2r-badges">' . implode( ' ', $badges ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public function render_loop_badges() : void {
		global $post;
		if ( ! $post instanceof \WP_Post ) {
			return;
		}
		$meta = ProductMeta::get_for_product( (int) $post->ID );
		$html = '';
		if ( '' !== $meta['energy_class'] ) {
			$html .= self::energy_badge_html( $meta['energy_class'] );
		}
		if ( '' !== $meta['repair_index'] ) {
			$html .= ' ' . self::score_badge_html( $meta['repair_index'] );
		}
		if ( '' !== $html ) {
			echo '<div class="eurocomply-r2r-badges eurocomply-r2r-badges--loop">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	public static function energy_badge_html( string $class ) : string {
		$class    = strtoupper( $class );
		$palette  = array(
			'A'  => '#00a651',
			'B'  => '#4db848',
			'C'  => '#b8d531',
			'D'  => '#ffe800',
			'E'  => '#f9b233',
			'F'  => '#e84e1b',
			'G'  => '#e30613',
			'NA' => '#666',
		);
		$bg       = $palette[ $class ] ?? '#666';
		$label    = 'NA' === $class ? __( 'n/a', 'eurocomply-r2r' ) : $class;
		$fg       = in_array( $class, array( 'A', 'B', 'C', 'F', 'G' ), true ) ? '#fff' : '#222';
		return sprintf(
			'<span class="eurocomply-r2r-badge eurocomply-r2r-badge--energy" style="background:%1$s;color:%3$s;">%2$s</span>',
			esc_attr( $bg ),
			esc_html( $label ),
			esc_attr( $fg )
		);
	}

	public static function score_badge_html( string $score ) : string {
		$num = (float) $score;
		if ( $num >= 8.0 ) {
			$bg = '#00a651';
			$fg = '#fff';
		} elseif ( $num >= 6.0 ) {
			$bg = '#b8d531';
			$fg = '#222';
		} elseif ( $num >= 4.0 ) {
			$bg = '#ffe800';
			$fg = '#222';
		} else {
			$bg = '#e30613';
			$fg = '#fff';
		}
		return sprintf(
			'<span class="eurocomply-r2r-badge eurocomply-r2r-badge--score" style="background:%1$s;color:%3$s;">%2$s/10</span>',
			esc_attr( $bg ),
			esc_html( $score ),
			esc_attr( $fg )
		);
	}
}
