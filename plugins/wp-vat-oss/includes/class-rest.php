<?php
/**
 * REST endpoints:
 *   POST /eurocomply-vat/v1/validate    — validate a VAT number via VIES (nonce-gated, checkout uses it)
 *   GET  /eurocomply-vat/v1/rates       — return the EU-27 rates table (public, read-only)
 *
 * @package EuroComply\VatOss
 */

declare( strict_types = 1 );

namespace EuroComply\VatOss;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Rest {

	public const NAMESPACE = 'eurocomply-vat/v1';
	public const NONCE     = 'eurocomply_vat_validate';

	private static ?Rest $instance = null;

	public static function instance() : Rest {
		if ( null === self::$instance ) {
			self::$instance = new self();
			add_action( 'rest_api_init', array( self::$instance, 'register' ) );
		}
		return self::$instance;
	}

	public function register() : void {
		register_rest_route(
			self::NAMESPACE,
			'/validate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'validate' ),
				'permission_callback' => array( $this, 'permission' ),
				'args'                => array(
					'vat' => array(
						'required' => true,
						'type'     => 'string',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/rates',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rates' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Permission callback. Returns true on valid nonce, WP_Error on failure.
	 *
	 * @return true|\WP_Error
	 */
	public function permission( \WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new \WP_Error( 'eurocomply_vat_invalid_nonce', __( 'Invalid or missing REST nonce.', 'eurocomply-vat-oss' ), array( 'status' => 403 ) );
		}
		return true;
	}

	public function validate( \WP_REST_Request $request ) : \WP_REST_Response {
		$settings = Settings::get();
		$vat_raw  = (string) $request->get_param( 'vat' );
		$vat      = Vies::normalise( $vat_raw );

		if ( '' === $vat ) {
			return new \WP_REST_Response( array( 'valid' => false, 'reason' => 'empty' ), 400 );
		}

		$format_ok = Vies::local_format_ok( $vat );
		if ( ! $format_ok ) {
			return new \WP_REST_Response(
				array(
					'valid'  => false,
					'reason' => 'format',
					'input'  => $vat,
				),
				200
			);
		}

		// Live VIES only if enabled; otherwise fall back to local format pass.
		if ( '1' !== (string) ( $settings['validate_via_vies'] ?? '1' ) ) {
			return new \WP_REST_Response(
				array(
					'valid'  => true,
					'source' => 'local',
					'input'  => $vat,
				),
				200
			);
		}

		$result = Vies::validate( $vat, (int) ( $settings['vies_timeout'] ?? 8 ) );

		TaxLog::insert(
			array(
				'event'         => 'vies_check',
				'buyer_country' => Rates::vat_prefix_to_iso( $result['prefix'] ),
				'shop_country'  => (string) ( $settings['shop_country'] ?? '' ),
				'vat_prefix'    => $result['prefix'],
				'vat_number'    => $result['number'],
				'vat_valid'     => $result['valid'] ? 1 : 0,
				'vies_source'   => $result['source'],
				'vies_name'     => substr( (string) $result['name'], 0, 250 ),
			)
		);

		return new \WP_REST_Response(
			array(
				'valid'  => $result['valid'],
				'source' => $result['source'],
				'name'   => $result['name'],
				'input'  => $vat,
			),
			200
		);
	}

	public function rates( \WP_REST_Request $request ) : \WP_REST_Response {
		unset( $request );
		return new \WP_REST_Response(
			array(
				'rates' => Rates::all(),
				'eu27'  => Rates::EU27,
			),
			200
		);
	}
}
