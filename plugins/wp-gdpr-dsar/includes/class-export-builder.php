<?php
/**
 * Builds an access / portability export ZIP from WordPress core + WooCommerce
 * + user meta + comments + post authorship.
 *
 * Delegates the heavy lifting to WordPress's built-in privacy exporter
 * registry (`wp_privacy_personal_data_exporters`) so any other plugin that
 * already registers exporters is automatically included.
 *
 * @package EuroComply\DSAR
 */

declare( strict_types = 1 );

namespace EuroComply\DSAR;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ExportBuilder {

	/**
	 * @return array{ok:bool,message:string,path:string,url:string}
	 */
	public static function build( int $request_id ) : array {
		$request = RequestStore::get( $request_id );
		if ( ! $request ) {
			return array( 'ok' => false, 'message' => __( 'Request not found.', 'eurocomply-dsar' ), 'path' => '', 'url' => '' );
		}

		$email = (string) $request['requester_email'];
		if ( '' === $email ) {
			return array( 'ok' => false, 'message' => __( 'Request has no associated email.', 'eurocomply-dsar' ), 'path' => '', 'url' => '' );
		}

		$data = self::collect( $email );
		if ( empty( $data ) ) {
			return array( 'ok' => false, 'message' => __( 'No exportable data was found for this email.', 'eurocomply-dsar' ), 'path' => '', 'url' => '' );
		}

		$upload = wp_get_upload_dir();
		$base   = trailingslashit( $upload['basedir'] ) . 'eurocomply-dsar';
		if ( ! wp_mkdir_p( $base ) ) {
			return array( 'ok' => false, 'message' => __( 'Could not create export directory.', 'eurocomply-dsar' ), 'path' => '', 'url' => '' );
		}
		self::protect_directory( $base );

		$stamp     = gmdate( 'Ymd-His' );
		$filename  = sprintf( 'eurocomply-dsar-%d-%s.zip', $request_id, $stamp );
		$full_path = trailingslashit( $base ) . $filename;

		if ( ! class_exists( '\\ZipArchive' ) ) {
			$plain_path = str_replace( '.zip', '.json', $full_path );
			file_put_contents( $plain_path, self::to_json( $request, $data ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			RequestStore::update( $request_id, array( 'export_path' => $plain_path ) );
			return array(
				'ok'      => true,
				'message' => __( 'ZIP support is unavailable on this server; a JSON file was written instead.', 'eurocomply-dsar' ),
				'path'    => $plain_path,
				'url'     => trailingslashit( $upload['baseurl'] ) . 'eurocomply-dsar/' . basename( $plain_path ),
			);
		}

		$zip = new \ZipArchive();
		if ( true !== $zip->open( $full_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) {
			return array( 'ok' => false, 'message' => __( 'Could not create ZIP archive.', 'eurocomply-dsar' ), 'path' => '', 'url' => '' );
		}

		$zip->addFromString( 'request.json', self::to_json( $request, $data ) );
		$zip->addFromString( 'export.csv', self::to_csv( $data ) );
		$zip->addFromString( 'README.txt', self::readme_body( $request ) );
		$zip->close();

		RequestStore::update( $request_id, array( 'export_path' => $full_path ) );

		$url = trailingslashit( $upload['baseurl'] ) . 'eurocomply-dsar/' . $filename;

		return array(
			'ok'      => true,
			'message' => __( 'Export archive created.', 'eurocomply-dsar' ),
			'path'    => $full_path,
			'url'     => $url,
		);
	}

	/**
	 * Aggregate by email address — includes any plugin-registered exporter
	 * via the core WP privacy registry.
	 *
	 * @return array<string,array<int,array<string,mixed>>>
	 */
	public static function collect( string $email ) : array {
		$out       = array();
		$exporters = apply_filters( 'wp_privacy_personal_data_exporters', array() );
		if ( ! is_array( $exporters ) ) {
			$exporters = array();
		}

		foreach ( $exporters as $slug => $exporter ) {
			if ( ! is_array( $exporter ) || ! isset( $exporter['callback'] ) || ! is_callable( $exporter['callback'] ) ) {
				continue;
			}
			$page = 1;
			do {
				$response = call_user_func( $exporter['callback'], $email, $page );
				if ( ! is_array( $response ) ) {
					break;
				}
				$items = isset( $response['data'] ) && is_array( $response['data'] ) ? $response['data'] : array();
				foreach ( $items as $item ) {
					$group_id = isset( $item['group_id'] ) ? (string) $item['group_id'] : (string) $slug;
					$out[ $group_id ][] = isset( $item['data'] ) && is_array( $item['data'] ) ? $item['data'] : array();
				}
				$done = ! empty( $response['done'] );
				$page++;
				if ( $page > 50 ) {
					// Safety cap — 50 pages is sufficient for any realistic personal dataset.
					break;
				}
			} while ( ! $done );
		}

		return $out;
	}

	/**
	 * @param array<string,mixed> $request
	 * @param array<string,array<int,array<string,mixed>>> $data
	 */
	private static function to_json( array $request, array $data ) : string {
		return (string) wp_json_encode(
			array(
				'schema'       => 'eurocomply-dsar-export-1',
				'generated_at' => gmdate( 'c' ),
				'request'      => array(
					'id'              => (int) ( $request['id'] ?? 0 ),
					'type'            => (string) ( $request['request_type'] ?? '' ),
					'requester_email' => (string) ( $request['requester_email'] ?? '' ),
					'requester_name'  => (string) ( $request['requester_name'] ?? '' ),
					'submitted_at'    => (string) ( $request['submitted_at'] ?? '' ),
				),
				'site'         => array(
					'name' => get_bloginfo( 'name' ),
					'url'  => home_url( '/' ),
				),
				'groups'       => $data,
			),
			JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);
	}

	/**
	 * @param array<string,array<int,array<string,mixed>>> $data
	 */
	private static function to_csv( array $data ) : string {
		$fp = fopen( 'php://temp', 'r+' );
		if ( ! $fp ) {
			return '';
		}
		fputcsv( $fp, array( 'group', 'name', 'value' ) );
		foreach ( $data as $group => $items ) {
			foreach ( $items as $item ) {
				foreach ( $item as $row ) {
					$name  = isset( $row['name'] ) ? (string) $row['name'] : '';
					$value = isset( $row['value'] ) ? (string) $row['value'] : '';
					if ( is_array( $value ) ) {
						$value = wp_json_encode( $value );
					}
					fputcsv( $fp, array( $group, $name, $value ) );
				}
			}
		}
		rewind( $fp );
		$csv = stream_get_contents( $fp );
		fclose( $fp );
		return (string) $csv;
	}

	/**
	 * @param array<string,mixed> $request
	 */
	private static function readme_body( array $request ) : string {
		return sprintf(
			"This archive contains personal data for %s (request #%d, %s).\n\nFiles:\n- request.json : structured export (all groups + request metadata)\n- export.csv   : flat CSV of the same data\n\nGenerated: %s\nPowered by EuroComply GDPR DSAR.\n",
			(string) ( $request['requester_email'] ?? '' ),
			(int) ( $request['id'] ?? 0 ),
			(string) ( $request['request_type'] ?? '' ),
			gmdate( 'c' )
		);
	}

	private static function protect_directory( string $dir ) : void {
		$htaccess = trailingslashit( $dir ) . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			file_put_contents( $htaccess, "Order allow,deny\nDeny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		$index = trailingslashit( $dir ) . 'index.html';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
	}
}
