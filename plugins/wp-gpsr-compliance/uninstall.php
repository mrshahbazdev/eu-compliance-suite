<?php
/**
 * Uninstall cleanup for EuroComply GPSR Compliance Manager.
 *
 * @package EuroComply\Gpsr
 */

declare( strict_types = 1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'eurocomply_gpsr_settings' );
delete_option( 'eurocomply_gpsr_license' );

// Product meta is intentionally preserved — merchants may disable the plugin temporarily and
// re-activate later without losing compliance data. Advanced uninstall (meta purge) is available
// via the Pro tier once the data-deletion workflow ships.
