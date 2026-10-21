<?php
/**
 * Deinstallation. Daten werden nur entfernt, wenn das in den Einstellungen
 * ausdrücklich aktiviert wurde.
 *
 * @package B83\Wetter
 */

declare(strict_types=1);

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

wp_clear_scheduled_hook( 'b83_wetter_refresh' );
wp_clear_scheduled_hook( 'b83_wetter_refresh_async' );

$b83_wetter_settings = get_option( 'b83_wetter_settings', array() );

if ( is_array( $b83_wetter_settings ) && ! empty( $b83_wetter_settings['delete_on_uninstall'] ) && '0' !== (string) $b83_wetter_settings['delete_on_uninstall'] ) {
	foreach ( array(
		'b83_wetter_settings',
		'b83_wetter_data',
		'b83_wetter_status',
		'b83_wetter_db_version',
	) as $b83_wetter_option ) {
		delete_option( $b83_wetter_option );
	}
	foreach ( array( 'b83_wetter_pages_scan', 'b83_wetter_release', 'b83_wetter_lock', 'b83_wetter_async_planned', 'b83_wetter_geo_lock' ) as $b83_wetter_transient ) {
		delete_transient( $b83_wetter_transient );
	}
}
