<?php
/**
 * Compliance evaluator: inspects products for missing GPSR fields.
 *
 * @package EuroComply\Gpsr
 */

declare( strict_types = 1 );

namespace EuroComply\Gpsr;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Compliance {

	public const STATUS_OK      = 'ok';
	public const STATUS_WARNING = 'warning';
	public const STATUS_ERROR   = 'error';

	/**
	 * Evaluate a single product.
	 *
	 * @return array{status:string,missing_required:array<int,string>,missing_recommended:array<int,string>}
	 */
	public static function evaluate( int $product_id ) : array {
		$missing_required    = array();
		$missing_recommended = array();

		foreach ( Settings::REQUIRED_META as $meta_key ) {
			if ( '' === ProductFields::resolve( $product_id, $meta_key ) ) {
				$missing_required[] = $meta_key;
			}
		}

		foreach ( Settings::RECOMMENDED_META as $meta_key ) {
			$value = $meta_key === '_gpsr_warnings' || $meta_key === '_gpsr_batch'
				? (string) get_post_meta( $product_id, $meta_key, true )
				: ProductFields::resolve( $product_id, $meta_key );
			if ( '' === $value ) {
				$missing_recommended[] = $meta_key;
			}
		}

		$status = self::STATUS_OK;
		if ( ! empty( $missing_required ) ) {
			$status = self::STATUS_ERROR;
		} elseif ( ! empty( $missing_recommended ) ) {
			$status = self::STATUS_WARNING;
		}

		return array(
			'status'              => $status,
			'missing_required'    => $missing_required,
			'missing_recommended' => $missing_recommended,
		);
	}

	/**
	 * Scan all published products and return compliance summary.
	 *
	 * @param int $limit Max products to scan (dashboard cap).
	 *
	 * @return array{counts:array<string,int>,rows:array<int,array<string,mixed>>}
	 */
	public static function scan( int $limit = 200 ) : array {
		$query = new \WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => max( 1, $limit ),
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
				'fields'         => 'ids',
			)
		);

		$counts = array(
			self::STATUS_OK      => 0,
			self::STATUS_WARNING => 0,
			self::STATUS_ERROR   => 0,
		);
		$rows   = array();

		foreach ( (array) $query->posts as $product_id ) {
			$product_id = (int) $product_id;
			$result     = self::evaluate( $product_id );
			$counts[ $result['status'] ]++;
			$rows[] = array(
				'id'                  => $product_id,
				'title'               => (string) get_the_title( $product_id ),
				'status'              => $result['status'],
				'missing_required'    => $result['missing_required'],
				'missing_recommended' => $result['missing_recommended'],
			);
		}

		return array(
			'counts' => $counts,
			'rows'   => $rows,
		);
	}

	public static function label_for( string $meta_key ) : string {
		foreach ( ProductFields::FIELDS as $field ) {
			if ( $field['key'] === $meta_key ) {
				return $field['label'];
			}
		}
		return $meta_key;
	}
}
