<?php
/**
 * Gemeinsame Schnittstelle der Datenquellen.
 *
 * @package B83\Wetter
 */

declare(strict_types=1);

namespace B83\Wetter\Data;

defined( 'ABSPATH' ) || exit;

interface SourceInterface {

	/** Kurzer Schlüssel, z. B. „geosphere“. */
	public function key(): string;

	/** Name für Backend und Quellenangabe. */
	public function label(): string;

	/** Link zur Quelle (für die Quellenangabe). */
	public function url(): string;

	/** Lizenz der Daten, z. B. „CC BY 4.0“. */
	public function license(): string;

	/** Link zur Lizenz. */
	public function license_url(): string;

	/** Deckt die Quelle diesen Standort ab? */
	public function covers( float $lat, float $lng ): bool;

	/**
	 * Vorhersage abrufen.
	 *
	 * @param array<string,string> $location lat, lng, altitude.
	 * @return array<int,array<string,mixed>>|\WP_Error Zeitschritte (siehe Forecast).
	 */
	public function fetch( array $location ): array|\WP_Error;
}
