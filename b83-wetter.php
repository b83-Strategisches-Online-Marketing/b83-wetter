<?php
/**
 * Plugin Name:       b83 Wetter
 * Plugin URI:        https://b83.at
 * Description:       Wettervorhersage aus offenen Daten (GeoSphere Austria, MET Norway) mit drei Darstellungen – klein, kompakt und ausführlich – per Shortcode oder Elementor-Widget.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      8.1
 * Author:            b83 – Wolfgang Grabs-Schrempf
 * Author URI:        https://b83.at
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       b83-wetter
 * Domain Path:       /languages
 * Update URI:        https://github.com/b83-Strategisches-Online-Marketing/b83-wetter
 *
 * @package B83\Wetter
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

define( 'B83_WETTER_VERSION', '1.0.0' );
define( 'B83_WETTER_FILE', __FILE__ );
define( 'B83_WETTER_DIR', plugin_dir_path( __FILE__ ) );
define( 'B83_WETTER_URL', plugin_dir_url( __FILE__ ) );

/*
 * PSR-4-Autoloader für den Namespace B83\Wetter → src/.
 */
spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'B83\\Wetter\\';
		if ( ! str_starts_with( $class, $prefix ) ) {
			return;
		}
		$relative = substr( $class, strlen( $prefix ) );
		$file     = B83_WETTER_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

register_activation_hook( __FILE__, array( \B83\Wetter\Migration::class, 'on_activation' ) );
register_deactivation_hook( __FILE__, array( \B83\Wetter\Scheduler::class, 'on_deactivation' ) );

add_action(
	'plugins_loaded',
	static function (): void {
		\B83\Wetter\Plugin::instance()->boot();
	}
);
