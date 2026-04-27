<?php
/**
 * Designated Recipient role + capability checks (Art. 9(1)(b), Art. 12).
 *
 * @package EuroComply\Whistleblower
 */

declare( strict_types = 1 );

namespace EuroComply\Whistleblower;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Recipient {

	public const ROLE        = 'eurocomply_wb_recipient';
	public const CAP_VIEW    = 'eurocomply_wb_view';
	public const CAP_MANAGE  = 'eurocomply_wb_manage';

	public static function register() : void {
		add_filter( 'user_has_cap', array( __CLASS__, 'grant_admin_cap' ), 10, 4 );
	}

	public static function ensure_role() : void {
		$role = get_role( self::ROLE );
		$caps = array(
			'read'             => true,
			self::CAP_VIEW     => true,
			self::CAP_MANAGE   => true,
		);
		if ( ! $role ) {
			add_role( self::ROLE, __( 'EuroComply Whistleblower Recipient', 'eurocomply-whistleblower' ), $caps );
		} else {
			foreach ( $caps as $c => $v ) {
				$role->add_cap( $c, (bool) $v );
			}
		}
	}

	public static function remove_role() : void {
		if ( get_role( self::ROLE ) ) {
			remove_role( self::ROLE );
		}
	}

	/**
	 * Administrators implicitly have both caps.
	 *
	 * @param array<string,bool> $allcaps
	 * @param array<int,string>  $caps
	 * @param array<int,mixed>   $args
	 */
	public static function grant_admin_cap( $allcaps, $caps, $args, $user ) {
		if ( ! is_array( $allcaps ) ) {
			$allcaps = (array) $allcaps;
		}
		if ( ! empty( $allcaps['manage_options'] ) ) {
			$allcaps[ self::CAP_VIEW ]   = true;
			$allcaps[ self::CAP_MANAGE ] = true;
		}
		// Users explicitly listed in settings are granted the recipient caps.
		if ( $user instanceof \WP_User ) {
			$settings = Settings::get();
			$ids      = isset( $settings['recipient_user_ids'] ) && is_array( $settings['recipient_user_ids'] )
				? array_map( 'intval', $settings['recipient_user_ids'] )
				: array();
			if ( in_array( (int) $user->ID, $ids, true ) ) {
				$allcaps[ self::CAP_VIEW ]   = true;
				$allcaps[ self::CAP_MANAGE ] = true;
			}
		}
		return $allcaps;
	}

	public static function can_view() : bool {
		return is_user_logged_in() && current_user_can( self::CAP_VIEW );
	}

	public static function can_manage() : bool {
		return is_user_logged_in() && current_user_can( self::CAP_MANAGE );
	}

	/**
	 * @return array<int,\WP_User>
	 */
	public static function get_recipients() : array {
		$settings = Settings::get();
		$ids      = isset( $settings['recipient_user_ids'] ) && is_array( $settings['recipient_user_ids'] )
			? array_map( 'intval', $settings['recipient_user_ids'] )
			: array();
		if ( empty( $ids ) ) {
			return array();
		}
		$users = get_users( array(
			'include'  => $ids,
			'fields'   => 'all',
			'orderby'  => 'display_name',
			'order'    => 'ASC',
		) );
		return is_array( $users ) ? $users : array();
	}
}
