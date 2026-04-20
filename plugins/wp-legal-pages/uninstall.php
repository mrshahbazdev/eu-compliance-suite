<?php
/**
 * Uninstall handler — removes all plugin data if the user deletes the plugin.
 *
 * @package EuroComply\LegalPages
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$options = array(
	'eurocomply_legal_settings',
	'eurocomply_legal_pages',
	'eurocomply_legal_license',
	'eurocomply_legal_version',
);

foreach ( $options as $option ) {
	delete_option( $option );
	delete_site_option( $option );
}

// Do not delete the generated WP pages themselves — those belong to the site.
