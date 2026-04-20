<?php
/**
 * Frontend GPSR block on single-product pages.
 *
 * Renders a "Product safety information (GPSR)" section after the product summary
 * and emits optional schema.org structured data for accessibility / crawlers.
 *
 * @package EuroComply\Gpsr
 */

declare( strict_types = 1 );

namespace EuroComply\Gpsr;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Frontend {

	private static ?Frontend $instance = null;

	public static function instance() : Frontend {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}
		return self::$instance;
	}

	private function boot() : void {
		// Classic themes: hook into the WooCommerce product summary action.
		add_action( 'woocommerce_single_product_summary', array( $this, 'render_block' ), 45 );
		// Block themes (WP 6.0+) do not fire the classic action on single product templates.
		// Append the GPSR section to the_content as a fallback so the block always renders.
		add_filter( 'the_content', array( $this, 'append_to_content' ), 20 );
		add_shortcode( 'eurocomply_gpsr', array( $this, 'shortcode' ) );
	}

	public function render_block() : void {
		$settings = Settings::get();
		if ( empty( $settings['render_frontend'] ) ) {
			return;
		}

		global $product;
		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		echo $this->render_html( (int) $product->get_id(), $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_html escapes its own output.
	}

	/**
	 * Append the GPSR section to single-product content. Primary path for block themes
	 * (Twenty Twenty-Four+, any FSE theme) where the classic
	 * `woocommerce_single_product_summary` action does not fire.
	 */
	public function append_to_content( string $content ) : string {
		if ( ! is_singular( 'product' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		// Block themes auto-generate a post excerpt from the_content when
		// post_excerpt is empty (wp_trim_excerpt → apply_filters('the_content')).
		// Skip in that context so the GPSR HTML does not leak into the
		// wp-block-post-excerpt block as a plaintext duplicate.
		if ( doing_filter( 'get_the_excerpt' ) ) {
			return $content;
		}
		$settings = Settings::get();
		if ( empty( $settings['render_frontend'] ) ) {
			return $content;
		}
		$product_id = (int) get_the_ID();
		if ( ! $product_id ) {
			return $content;
		}
		// Avoid double-render in classic themes that already ran the action.
		if ( false !== strpos( $content, 'class="eurocomply-gpsr"' ) ) {
			return $content;
		}
		$html = $this->render_html( $product_id, $settings );
		if ( '' === $html ) {
			return $content;
		}
		return $content . $html;
	}

	public function shortcode( $atts ) : string {
		$settings   = Settings::get();
		$product_id = 0;
		if ( is_array( $atts ) && ! empty( $atts['id'] ) ) {
			$product_id = (int) $atts['id'];
		} elseif ( is_singular( 'product' ) ) {
			$product_id = (int) get_the_ID();
		}
		if ( ! $product_id ) {
			return '';
		}
		return $this->render_html( $product_id, $settings );
	}

	/**
	 * @param array<string,mixed> $settings Plugin settings.
	 */
	private function render_html( int $product_id, array $settings ) : string {
		$fields = array(
			'manufacturer' => array(
				'heading' => __( 'Manufacturer', 'eurocomply-gpsr' ),
				'name'    => ProductFields::resolve( $product_id, '_gpsr_manufacturer_name' ),
				'address' => ProductFields::resolve( $product_id, '_gpsr_manufacturer_address' ),
			),
			'importer'     => array(
				'heading' => __( 'Importer', 'eurocomply-gpsr' ),
				'name'    => ProductFields::resolve( $product_id, '_gpsr_importer_name' ),
				'address' => ProductFields::resolve( $product_id, '_gpsr_importer_address' ),
			),
			'eu_rep'       => array(
				'heading' => __( 'EU Responsible Person', 'eurocomply-gpsr' ),
				'name'    => ProductFields::resolve( $product_id, '_gpsr_eu_rep_name' ),
				'address' => ProductFields::resolve( $product_id, '_gpsr_eu_rep_address' ),
			),
		);

		$warnings = (string) get_post_meta( $product_id, '_gpsr_warnings', true );
		$batch    = (string) get_post_meta( $product_id, '_gpsr_batch', true );

		$has_any = $warnings || $batch;
		foreach ( $fields as $group ) {
			if ( $group['name'] || $group['address'] ) {
				$has_any = true;
				break;
			}
		}
		if ( ! $has_any ) {
			return '';
		}

		ob_start();
		?>
		<section class="eurocomply-gpsr" aria-labelledby="eurocomply-gpsr-heading">
			<h2 id="eurocomply-gpsr-heading" class="eurocomply-gpsr__heading">
				<?php echo esc_html( (string) $settings['frontend_heading'] ); ?>
			</h2>
			<dl class="eurocomply-gpsr__list">
				<?php foreach ( $fields as $group ) : ?>
					<?php if ( $group['name'] || $group['address'] ) : ?>
						<dt><?php echo esc_html( $group['heading'] ); ?></dt>
						<dd>
							<?php if ( $group['name'] ) : ?>
								<strong><?php echo esc_html( $group['name'] ); ?></strong><br />
							<?php endif; ?>
							<?php if ( $group['address'] ) : ?>
								<?php echo nl2br( esc_html( $group['address'] ) ); ?>
							<?php endif; ?>
						</dd>
					<?php endif; ?>
				<?php endforeach; ?>
				<?php if ( $warnings ) : ?>
					<dt><?php esc_html_e( 'Safety warnings', 'eurocomply-gpsr' ); ?></dt>
					<dd><?php echo nl2br( esc_html( $warnings ) ); ?></dd>
				<?php endif; ?>
				<?php if ( $batch ) : ?>
					<dt><?php esc_html_e( 'Batch / lot', 'eurocomply-gpsr' ); ?></dt>
					<dd><code><?php echo esc_html( $batch ); ?></code></dd>
				<?php endif; ?>
			</dl>
		</section>
		<?php
		return (string) ob_get_clean();
	}
}
