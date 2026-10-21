<?php
/**
 * Gemeinsame Angaben für Anfragen an die Wetterdienste.
 *
 * @package B83\Wetter
 */

declare(strict_types=1);

namespace B83\Wetter\Data;

defined( 'ABSPATH' ) || exit;

final class Http {

	/**
	 * Eindeutige Kennung mit Kontakt (verlangt MET Norway, höflich gegenüber GeoSphere).
	 * Besucher-IP-Adressen werden nie übertragen – Abfragen laufen nur über den Server.
	 */
	public static function user_agent(): string {
		$host = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$ua   = sprintf( 'b83-wetter/%s (+https://b83.at; office@b83.at; %s)', B83_WETTER_VERSION, $host );

		/**
		 * User-Agent für Anfragen an die Wetterdienste.
		 *
		 * @param string $ua User-Agent.
		 */
		return (string) apply_filters( 'b83_wetter_user_agent', $ua );
	}
}
