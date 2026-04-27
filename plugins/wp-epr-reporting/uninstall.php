<?php
/**
 * Uninstall handler — cleans up plugin options + license. Product EPR meta is
 * preserved so merchants can re-install without data loss.
 *
 * @package EuroComply\Epr
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'eurocomply_epr_settings' );
delete_option( 'eurocomply_epr_license' );
