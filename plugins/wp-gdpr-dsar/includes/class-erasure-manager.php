<?php
/**
 * Erasure workflow.
 *
 * Implements a GDPR Art. 17 erasure by delegating to the WordPress built-in
 * personal-data eraser registry (`wp_privacy_personal_data_erasers`), so any
 * other plugin that already registers erasers is automatically invoked. Also
 * schedules a grace window before hard-deleting the user account (if any).
 *
 * @package EuroComply\DSAR
 */

declare( strict_types = 1 );

namespace EuroComply\DSAR;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ErasureManager {

	public const CRON_HOOK = 'eurocomply_dsar_finalise_erasure';

	private static ?ErasureManager $instance = null;

	public static function instance() : ErasureManager {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register();
		}
		return self::$instance;
	}

	private function register() : void {
		add_action( self::CRON_HOOK, array( $this, 'finalise_erasure' ) );
	}

	/**
	 * Run erasers for the given request. Returns a summary that admins can
	 * display in the UI.
	 *
	 * @return array{ok:bool,message:string,erased:int,retained:int,messages:array<int,string>}
	 */
	public static function run( int $request_id, bool $force_delete_user = false ) : array {
		$request = RequestStore::get( $request_id );
		$summary = array(
			'ok'       => false,
			'message'  => '',
			'erased'   => 0,
			'retained' => 0,
			'messages' => array(),
		);

		if ( ! $request ) {
			$summary['message'] = __( 'Request not found.', 'eurocomply-dsar' );
			return $summary;
		}
		$email = (string) $request['requester_email'];
		if ( '' === $email ) {
			$summary['message'] = __( 'Request has no associated email address.', 'eurocomply-dsar' );
			return $summary;
		}

		$erasers = apply_filters( 'wp_privacy_personal_data_erasers', array() );
		if ( ! is_array( $erasers ) ) {
			$erasers = array();
		}

		foreach ( $erasers as $slug => $eraser ) {
			if ( ! is_array( $eraser ) || ! isset( $eraser['callback'] ) || ! is_callable( $eraser['callback'] ) ) {
				continue;
			}
			$page = 1;
			do {
				$response = call_user_func( $eraser['callback'], $email, $page );
				if ( ! is_array( $response ) ) {
					break;
				}
				$summary['erased']   += isset( $response['items_removed'] ) ? (int) $response['items_removed'] : 0;
				$summary['retained'] += isset( $response['items_retained'] ) ? (int) $response['items_retained'] : 0;
				if ( ! empty( $response['messages'] ) && is_array( $response['messages'] ) ) {
					foreach ( $response['messages'] as $msg ) {
						$summary['messages'][] = (string) $msg;
					}
				}
				$done = ! empty( $response['done'] );
				$page++;
				if ( $page > 50 ) {
					break;
				}
			} while ( ! $done );
		}

		$user = get_user_by( 'email', $email );
		if ( $user ) {
			if ( $force_delete_user ) {
				self::hard_delete_user( (int) $user->ID );
				$summary['messages'][] = sprintf( /* translators: %d: user id */ __( 'User #%d was deleted immediately.', 'eurocomply-dsar' ), (int) $user->ID );
			} else {
				$grace = max( 0, (int) ( Settings::get()['erasure_grace_days'] ?? 7 ) );
				if ( $grace > 0 ) {
					self::schedule_finalise( $request_id, $grace );
					$summary['messages'][] = sprintf( /* translators: %d: grace days */ __( 'User account will be hard-deleted after %d days grace period.', 'eurocomply-dsar' ), $grace );
				} else {
					self::hard_delete_user( (int) $user->ID );
					$summary['messages'][] = __( 'User account deleted.', 'eurocomply-dsar' );
				}
			}
		} else {
			$summary['messages'][] = __( 'No matching WordPress user account was found.', 'eurocomply-dsar' );
		}

		$summary['ok']      = true;
		$summary['message'] = __( 'Erasure completed.', 'eurocomply-dsar' );
		return $summary;
	}

	public static function schedule_finalise( int $request_id, int $days ) : void {
		$when = time() + max( 0, $days ) * DAY_IN_SECONDS;
		wp_clear_scheduled_hook( self::CRON_HOOK, array( $request_id ) );
		wp_schedule_single_event( $when, self::CRON_HOOK, array( $request_id ) );
	}

	public function finalise_erasure( int $request_id ) : void {
		$request = RequestStore::get( $request_id );
		if ( ! $request ) {
			return;
		}
		$email = (string) $request['requester_email'];
		$user  = $email ? get_user_by( 'email', $email ) : null;
		if ( $user ) {
			self::hard_delete_user( (int) $user->ID );
		}
		RequestStore::update( $request_id, array(
			'status'       => 'completed',
			'completed_at' => current_time( 'mysql' ),
		) );
	}

	private static function hard_delete_user( int $user_id ) : void {
		if ( $user_id <= 0 ) {
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/user.php';
		if ( is_multisite() ) {
			if ( function_exists( 'wpmu_delete_user' ) ) {
				wpmu_delete_user( $user_id );
			}
		} else {
			wp_delete_user( $user_id );
		}
	}
}
