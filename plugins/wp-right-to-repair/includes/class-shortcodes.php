<?php
/**
 * Public shortcodes.
 *
 * [eurocomply_r2r_info id="123"]        Renders the repair-and-parts spec sheet for a product.
 * [eurocomply_r2r_spares category="washing_machine" country="DE"]  Spare-parts supplier directory.
 * [eurocomply_r2r_repairers category="" country=""]                Authorised-repairer directory.
 *
 * @package EuroComply\R2R
 */

declare( strict_types = 1 );

namespace EuroComply\R2R;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Shortcodes {

	private static ?Shortcodes $instance = null;

	public static function instance() : Shortcodes {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register();
		}
		return self::$instance;
	}

	private function register() : void {
		add_shortcode( 'eurocomply_r2r_info', array( $this, 'render_info' ) );
		add_shortcode( 'eurocomply_r2r_spares', array( $this, 'render_spares' ) );
		add_shortcode( 'eurocomply_r2r_repairers', array( $this, 'render_repairers' ) );
	}

	public function render_info( $atts ) : string {
		$atts = shortcode_atts( array( 'id' => 0 ), (array) $atts, 'eurocomply_r2r_info' );
		$id   = (int) $atts['id'];
		if ( $id <= 0 ) {
			$id = (int) get_the_ID();
		}
		if ( $id <= 0 ) {
			return '';
		}
		ob_start();
		$GLOBALS['post'] = get_post( $id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		setup_postdata( $GLOBALS['post'] );
		ProductDisplay::instance(); // ensures CSS is enqueued.
		ProductDisplay::instance()->render_tab();
		wp_reset_postdata();
		return (string) ob_get_clean();
	}

	public function render_spares( $atts ) : string {
		$atts = shortcode_atts(
			array(
				'category' => '',
				'country'  => '',
				'limit'    => 100,
			),
			(array) $atts,
			'eurocomply_r2r_spares'
		);
		$rows = SparePartsStore::all( sanitize_key( (string) $atts['category'] ), strtoupper( (string) $atts['country'] ), max( 1, min( 500, (int) $atts['limit'] ) ) );
		$cats = Settings::product_categories();

		ob_start();
		echo '<div class="eurocomply-r2r-directory eurocomply-r2r-directory--spares">';
		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No spare-parts suppliers are listed yet.', 'eurocomply-r2r' ) . '</p>';
		} else {
			echo '<table class="eurocomply-r2r-table"><thead><tr>';
			foreach ( array( __( 'Supplier', 'eurocomply-r2r' ), __( 'Category', 'eurocomply-r2r' ), __( 'Country', 'eurocomply-r2r' ), __( 'Parts years', 'eurocomply-r2r' ), __( 'Contact', 'eurocomply-r2r' ) ) as $h ) {
				echo '<th>' . esc_html( $h ) . '</th>';
			}
			echo '</tr></thead><tbody>';
			foreach ( $rows as $r ) {
				$cat_label = isset( $cats[ $r['product_category'] ] ) ? (string) $cats[ $r['product_category'] ]['label'] : (string) $r['product_category'];
				echo '<tr>';
				printf( '<td>%s</td>', esc_html( (string) $r['name'] ) );
				printf( '<td>%s</td>', esc_html( $cat_label ) );
				printf( '<td>%s</td>', esc_html( (string) $r['country'] ) );
				printf( '<td>%d</td>', (int) $r['availability_years'] );
				$contact = array();
				if ( ! empty( $r['website'] ) ) {
					$contact[] = sprintf( '<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>', esc_url( (string) $r['website'] ), esc_html__( 'Website', 'eurocomply-r2r' ) );
				}
				if ( ! empty( $r['email'] ) ) {
					$contact[] = sprintf( '<a href="mailto:%1$s">%1$s</a>', esc_attr( (string) $r['email'] ) );
				}
				if ( ! empty( $r['phone'] ) ) {
					$contact[] = esc_html( (string) $r['phone'] );
				}
				echo '<td>' . implode( ' · ', $contact ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '</tr>';
			}
			echo '</tbody></table>';
		}
		echo '</div>';
		return (string) ob_get_clean();
	}

	public function render_repairers( $atts ) : string {
		$atts = shortcode_atts(
			array(
				'category' => '',
				'country'  => '',
				'limit'    => 200,
			),
			(array) $atts,
			'eurocomply_r2r_repairers'
		);
		$rows = RepairerStore::all( sanitize_key( (string) $atts['category'] ), strtoupper( (string) $atts['country'] ), max( 1, min( 500, (int) $atts['limit'] ) ) );
		$cats = Settings::product_categories();

		ob_start();
		echo '<div class="eurocomply-r2r-directory eurocomply-r2r-directory--repairers">';
		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No authorised repairers are listed yet.', 'eurocomply-r2r' ) . '</p>';
		} else {
			echo '<table class="eurocomply-r2r-table"><thead><tr>';
			foreach ( array( __( 'Repairer', 'eurocomply-r2r' ), __( 'Category', 'eurocomply-r2r' ), __( 'Country / City', 'eurocomply-r2r' ), __( 'Certification', 'eurocomply-r2r' ), __( 'Contact', 'eurocomply-r2r' ) ) as $h ) {
				echo '<th>' . esc_html( $h ) . '</th>';
			}
			echo '</tr></thead><tbody>';
			foreach ( $rows as $r ) {
				$cat_label = isset( $cats[ $r['product_category'] ] ) ? (string) $cats[ $r['product_category'] ]['label'] : (string) $r['product_category'];
				echo '<tr>';
				printf( '<td>%s</td>', esc_html( (string) $r['name'] ) );
				printf( '<td>%s</td>', esc_html( $cat_label ) );
				printf( '<td>%s / %s</td>', esc_html( (string) $r['country'] ), esc_html( (string) $r['city'] ) );
				printf( '<td>%s</td>', esc_html( (string) $r['certification'] ) );
				$contact = array();
				if ( ! empty( $r['website'] ) ) {
					$contact[] = sprintf( '<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>', esc_url( (string) $r['website'] ), esc_html__( 'Website', 'eurocomply-r2r' ) );
				}
				if ( ! empty( $r['email'] ) ) {
					$contact[] = sprintf( '<a href="mailto:%1$s">%1$s</a>', esc_attr( (string) $r['email'] ) );
				}
				if ( ! empty( $r['phone'] ) ) {
					$contact[] = esc_html( (string) $r['phone'] );
				}
				echo '<td>' . implode( ' · ', $contact ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '</tr>';
			}
			echo '</tbody></table>';
		}
		echo '</div>';
		return (string) ob_get_clean();
	}
}
