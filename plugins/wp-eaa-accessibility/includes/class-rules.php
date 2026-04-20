<?php
/**
 * WCAG 2.1 rule catalogue (machine-checkable subset).
 *
 * Free-tier scanner implements these rules statically against rendered HTML.
 * Pro tier will add rules requiring a browser (computed contrast, reflow, focus order).
 *
 * @package EuroComply\Eaa
 */

declare( strict_types = 1 );

namespace EuroComply\Eaa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Rules {

	/**
	 * @return array<string,array<string,string>>
	 */
	public static function all() : array {
		return array(
			'img_alt_missing' => array(
				'label'    => 'Image missing alt attribute',
				'wcag'     => '1.1.1',
				'severity' => 'serious',
			),
			'img_alt_empty_non_decorative' => array(
				'label'    => 'Image alt="" with role outside decorative context',
				'wcag'     => '1.1.1',
				'severity' => 'moderate',
			),
			'heading_order_skip' => array(
				'label'    => 'Heading levels skipped (e.g. h1 → h3)',
				'wcag'     => '1.3.1',
				'severity' => 'moderate',
			),
			'heading_h1_missing' => array(
				'label'    => 'Page has no h1',
				'wcag'     => '2.4.6',
				'severity' => 'moderate',
			),
			'form_label_missing' => array(
				'label'    => 'Form control without label / aria-label / aria-labelledby',
				'wcag'     => '3.3.2',
				'severity' => 'serious',
			),
			'link_empty_text' => array(
				'label'    => 'Link with no accessible name',
				'wcag'     => '2.4.4',
				'severity' => 'serious',
			),
			'link_generic_text' => array(
				'label'    => 'Link text is generic (e.g. "click here", "read more")',
				'wcag'     => '2.4.4',
				'severity' => 'minor',
			),
			'button_empty_text' => array(
				'label'    => 'Button with no accessible name',
				'wcag'     => '4.1.2',
				'severity' => 'serious',
			),
			'html_lang_missing' => array(
				'label'    => 'Root <html> has no lang attribute',
				'wcag'     => '3.1.1',
				'severity' => 'serious',
			),
			'landmark_main_missing' => array(
				'label'    => 'Page has no <main> or role="main" landmark',
				'wcag'     => '1.3.1',
				'severity' => 'moderate',
			),
			'iframe_title_missing' => array(
				'label'    => '<iframe> without title attribute',
				'wcag'     => '4.1.2',
				'severity' => 'moderate',
			),
			'duplicate_ids' => array(
				'label'    => 'Duplicate id attributes on the page',
				'wcag'     => '4.1.1',
				'severity' => 'moderate',
			),
		);
	}

	public static function severity_order() : array {
		return array( 'serious', 'moderate', 'minor' );
	}
}
