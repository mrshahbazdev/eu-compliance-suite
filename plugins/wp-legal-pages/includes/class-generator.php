<?php
/**
 * Generator — render a template with business-info substitutions.
 *
 * @package EuroComply\LegalPages
 */

namespace EuroComply\LegalPages;

defined( 'ABSPATH' ) || exit;

class Generator {

	/** @var Settings */
	private $settings;

	/** @var Templates */
	private $templates;

	/** @var License */
	private $license;

	public function __construct( Settings $settings, Templates $templates, License $license ) {
		$this->settings  = $settings;
		$this->templates = $templates;
		$this->license   = $license;
	}

	/**
	 * Render a legal page HTML snippet (without wrapping <html>/<body>).
	 *
	 * @param string $type Templates::TYPE_*
	 * @return array{ok:bool, html?:string, error?:string, paywall?:bool}
	 */
	public function render( $type ) {
		$values  = $this->settings->get_all();
		$country = ! empty( $values['country'] ) ? strtoupper( $values['country'] ) : 'DE';

		// Pro gating.
		if ( ! $this->templates->is_free_type( $type ) && ! $this->license->is_pro() ) {
			return array(
				'ok'      => false,
				'paywall' => true,
				'error'   => __( 'This legal document type is only available in EuroComply Pro.', 'eurocomply-legal' ),
			);
		}
		if ( ! $this->templates->is_free_country( $country ) && ! $this->license->is_pro() ) {
			return array(
				'ok'      => false,
				'paywall' => true,
				'error'   => __( 'Country templates outside DE/AT/CH require EuroComply Pro.', 'eurocomply-legal' ),
			);
		}

		$file = $this->templates->resolve( $country, $type );
		if ( ! $file ) {
			return array(
				'ok'    => false,
				'error' => sprintf(
					/* translators: 1: country code, 2: type */
					__( 'No template found for %1$s / %2$s.', 'eurocomply-legal' ),
					$country,
					$type
				),
			);
		}

		$missing = $this->settings->missing_required();
		if ( ! empty( $missing ) ) {
			return array(
				'ok'    => false,
				'error' => sprintf(
					/* translators: %s: comma-separated list of missing fields */
					__( 'Please fill in required fields first: %s', 'eurocomply-legal' ),
					implode( ', ', $missing )
				),
			);
		}

		// Template files return an array with 'title' and 'body' keys.
		$tpl = include $file;
		if ( ! is_array( $tpl ) || empty( $tpl['body'] ) ) {
			return array(
				'ok'    => false,
				'error' => __( 'Template file is malformed.', 'eurocomply-legal' ),
			);
		}

		$html = $this->substitute( (string) $tpl['body'], $values );

		/**
		 * Filter: final generated HTML before it is returned / published.
		 */
		$html = apply_filters( 'eurocomply_legal_generated_html', $html, $type, $country, $values );

		return array(
			'ok'    => true,
			'title' => isset( $tpl['title'] ) ? (string) $tpl['title'] : '',
			'html'  => $html,
		);
	}

	/**
	 * Replace {{field_name}} placeholders with sanitised values.
	 */
	private function substitute( $body, array $values ) {
		return preg_replace_callback(
			'/\{\{\s*([a-z_]+)\s*\}\}/',
			function ( $m ) use ( $values ) {
				$key = $m[1];
				if ( ! array_key_exists( $key, $values ) ) {
					return '';
				}
				$val = (string) $values[ $key ];
				return esc_html( $val );
			},
			$body
		);
	}
}
