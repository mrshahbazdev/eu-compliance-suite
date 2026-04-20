<?php
/**
 * Frontend consent banner.
 *
 * @package EuroComply\CookieConsent
 */

declare( strict_types = 1 );

namespace EuroComply\CookieConsent;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the consent banner, enqueues its assets, and exposes the runtime config.
 */
final class Banner {

	private static ?Banner $instance = null;

	public static function instance() : Banner {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}
		return self::$instance;
	}

	private function boot() : void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_footer', array( $this, 'render' ), 5 );
	}

	/**
	 * Enqueue banner CSS / JS on the frontend.
	 */
	public function enqueue() : void {
		wp_enqueue_style(
			'eurocomply-cc-banner',
			EUROCOMPLY_CC_URL . 'assets/css/banner.css',
			array(),
			EUROCOMPLY_CC_VERSION
		);
		wp_enqueue_script(
			'eurocomply-cc-banner',
			EUROCOMPLY_CC_URL . 'assets/js/banner.js',
			array(),
			EUROCOMPLY_CC_VERSION,
			true
		);

		$settings = Settings::get();
		$lang     = $this->pick_language( $settings );
		$text     = 'de' === $lang ? $settings['text_de'] : $settings['text_en'];

		wp_localize_script(
			'eurocomply-cc-banner',
			'EuroComplyCC',
			array(
				'rest'       => esc_url_raw( rest_url( 'eurocomply-cc/v1/consent' ) ),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'cookie'     => 'eurocomply_cc',
				'days'       => (int) $settings['consent_days'],
				'version'    => (string) $settings['consent_version'],
				'language'   => $lang,
				'categories' => $this->public_categories( $settings ),
				'text'       => $text,
				'colors'     => array(
					'bg'     => $settings['banner_color_bg'],
					'text'   => $settings['banner_color_text'],
					'accent' => $settings['banner_color_accent'],
				),
				'layout'     => $settings['banner_layout'],
				'position'   => $settings['banner_position'],
				'policy'     => $settings['privacy_policy_page'] ? get_permalink( (int) $settings['privacy_policy_page'] ) : '',
				'showReject' => ! empty( $settings['show_reject_button'] ),
			)
		);

		$inline_css = sprintf(
			':root{--eurocomply-cc-bg:%s;--eurocomply-cc-text:%s;--eurocomply-cc-accent:%s;}',
			esc_attr( $settings['banner_color_bg'] ),
			esc_attr( $settings['banner_color_text'] ),
			esc_attr( $settings['banner_color_accent'] )
		);
		wp_add_inline_style( 'eurocomply-cc-banner', $inline_css );
	}

	/**
	 * Print the banner markup. The banner.js script decides whether to show it.
	 */
	public function render() : void {
		$settings = Settings::get();
		$lang     = $this->pick_language( $settings );
		$text     = 'de' === $lang ? $settings['text_de'] : $settings['text_en'];
		?>
		<div
			class="eurocomply-cc-root"
			data-position="<?php echo esc_attr( $settings['banner_position'] ); ?>"
			data-layout="<?php echo esc_attr( $settings['banner_layout'] ); ?>"
			hidden
		>
			<div class="eurocomply-cc-banner" role="dialog" aria-modal="false" aria-labelledby="eurocomply-cc-title">
				<div class="eurocomply-cc-body">
					<h2 id="eurocomply-cc-title" class="eurocomply-cc-title"><?php echo esc_html( $text['title'] ); ?></h2>
					<p class="eurocomply-cc-message"><?php echo esc_html( $text['body'] ); ?></p>
					<?php if ( ! empty( $settings['privacy_policy_page'] ) ) : ?>
						<p class="eurocomply-cc-policy"><a href="<?php echo esc_url( get_permalink( (int) $settings['privacy_policy_page'] ) ); ?>"><?php echo esc_html( $text['policy_link'] ); ?></a></p>
					<?php endif; ?>
				</div>
				<div class="eurocomply-cc-prefs" hidden>
					<?php foreach ( $this->public_categories( $settings ) as $slug => $cat ) : ?>
						<label class="eurocomply-cc-category">
							<input
								type="checkbox"
								data-category="<?php echo esc_attr( $slug ); ?>"
								<?php disabled( ! empty( $cat['locked'] ) ); ?>
								<?php checked( ! empty( $cat['locked'] ) ); ?>
							/>
							<span class="eurocomply-cc-category-label"><?php echo esc_html( $cat['label'] ); ?></span>
							<span class="eurocomply-cc-category-desc"><?php echo esc_html( $cat['description'] ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
				<div class="eurocomply-cc-actions">
					<?php if ( ! empty( $settings['show_reject_button'] ) ) : ?>
						<button type="button" class="eurocomply-cc-btn eurocomply-cc-reject" data-action="reject"><?php echo esc_html( $text['reject_all'] ); ?></button>
					<?php endif; ?>
					<button type="button" class="eurocomply-cc-btn eurocomply-cc-customize" data-action="customize"><?php echo esc_html( $text['customize'] ); ?></button>
					<button type="button" class="eurocomply-cc-btn eurocomply-cc-save" data-action="save" hidden><?php echo esc_html( $text['save'] ); ?></button>
					<button type="button" class="eurocomply-cc-btn eurocomply-cc-accept" data-action="accept"><?php echo esc_html( $text['accept_all'] ); ?></button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Choose which banner language to render based on settings + site locale.
	 *
	 * @param array<string,mixed> $settings Merged settings.
	 */
	private function pick_language( array $settings ) : string {
		$primary = in_array( $settings['primary_language'], array( 'en', 'de' ), true ) ? $settings['primary_language'] : 'en';
		if ( empty( $settings['auto_language'] ) ) {
			return $primary;
		}
		$locale = strtolower( (string) determine_locale() );
		return 0 === strpos( $locale, 'de' ) ? 'de' : 'en';
	}

	/**
	 * Expose category data to the frontend (labels + GCM mapping only, no internal flags).
	 *
	 * @param array<string,mixed> $settings Merged settings.
	 * @return array<string,array<string,mixed>>
	 */
	private function public_categories( array $settings ) : array {
		$out = array();
		foreach ( $settings['categories'] as $slug => $row ) {
			if ( empty( $row['enabled'] ) && empty( $row['locked'] ) ) {
				continue;
			}
			$out[ $slug ] = array(
				'label'       => (string) $row['label'],
				'description' => (string) $row['description'],
				'locked'      => ! empty( $row['locked'] ),
				'gcm'         => is_array( $row['gcm'] ?? null ) ? array_values( $row['gcm'] ) : array(),
			);
		}
		return $out;
	}
}
