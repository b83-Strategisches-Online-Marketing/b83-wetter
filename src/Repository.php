<?php
/**
 * Speichert Einstellungen, Wetterdaten und Status (WordPress-Optionen).
 *
 * @package B83\Wetter
 */

declare(strict_types=1);

namespace B83\Wetter;

use B83\Wetter\Data\Forecast;
use B83\Wetter\Data\GeoSphereSource;
use B83\Wetter\Data\MetNorwaySource;
use B83\Wetter\Data\SourceInterface;

defined( 'ABSPATH' ) || exit;

final class Repository {

	public const OPTION_SETTINGS = 'b83_wetter_settings';
	public const OPTION_DATA     = 'b83_wetter_data';
	public const OPTION_STATUS   = 'b83_wetter_status';

	/** @var array<string,mixed>|null */
	private ?array $settings = null;

	/** @var array<string,mixed>|null */
	private ?array $data = null;

	/** @var array<string,SourceInterface>|null */
	private ?array $sources = null;

	/* ---------------------------------------------------------------------
	 * Einstellungen
	 * ------------------------------------------------------------------- */

	/** @return array<string,mixed> */
	public function get_settings(): array {
		if ( null === $this->settings ) {
			$this->settings = Sanitizer::settings( (array) get_option( self::OPTION_SETTINGS, array() ) );
		}
		return $this->settings;
	}

	/** @return array<string,mixed> Vollständiges Design aus dem Backend. */
	public function design(): array {
		return $this->get_settings()['design'];
	}

	/** @param array<string,mixed> $settings Rohwerte (werden geprüft). */
	public function save_settings( array $settings ): void {
		$settings = Sanitizer::settings( $settings );
		update_option( self::OPTION_SETTINGS, $settings, false );
		$this->settings = $settings;

		/**
		 * Nach dem Speichern der Einstellungen.
		 *
		 * @param array<string,mixed> $settings Einstellungen.
		 */
		do_action( 'b83_wetter_settings_saved', $settings );
	}

	/**
	 * Standort, sofern vollständig.
	 *
	 * @return array{name:string,lat:string,lng:string,altitude:string}|null
	 */
	public function location(): ?array {
		$s = $this->get_settings();
		if ( '' === $s['lat'] || '' === $s['lng'] ) {
			return null;
		}
		return array(
			'name'     => (string) $s['location_name'],
			'lat'      => (string) $s['lat'],
			'lng'      => (string) $s['lng'],
			'altitude' => (string) $s['altitude'],
		);
	}

	/** Kennung des Standorts – gespeicherte Daten gelten nur für diesen Standort. */
	public function location_key(): string {
		$loc = $this->location();
		return null === $loc ? '' : md5( $loc['lat'] . ',' . $loc['lng'] . ',' . $loc['altitude'] );
	}

	/* ---------------------------------------------------------------------
	 * Datenquellen
	 * ------------------------------------------------------------------- */

	/** @return array<string,SourceInterface> */
	public function sources(): array {
		if ( null === $this->sources ) {
			$list = array(
				'geosphere' => new GeoSphereSource(),
				'metno'     => new MetNorwaySource(),
			);

			/**
			 * Datenquellen. Eigene Quellen müssen SourceInterface umsetzen.
			 *
			 * @param array<string,SourceInterface> $list Schlüssel => Quelle.
			 */
			$list = (array) apply_filters( 'b83_wetter_sources', $list );

			$this->sources = array_filter( $list, static fn( $s ): bool => $s instanceof SourceInterface );
		}
		return $this->sources;
	}

	public function source( string $key ): ?SourceInterface {
		return $this->sources()[ $key ] ?? null;
	}

	/* ---------------------------------------------------------------------
	 * Wetterdaten
	 * ------------------------------------------------------------------- */

	/** @return array<string,mixed> */
	public function get_data(): array {
		if ( null === $this->data ) {
			$data       = get_option( self::OPTION_DATA, array() );
			$this->data = is_array( $data ) ? $data : array();
		}
		return $this->data;
	}

	/** @param array<string,mixed> $data Wetterdaten. */
	public function save_data( array $data ): void {
		update_option( self::OPTION_DATA, $data, false );
		$this->data = $data;
	}

	/** Vorhersage für den aktuellen Standort (leer, wenn die Daten zu einem anderen Standort gehören). */
	public function forecast(): Forecast {
		$data = $this->get_data();
		$loc  = $this->location();
		if ( null === $loc || ( $data['location'] ?? '' ) !== $this->location_key() ) {
			$data = array();
		}
		return new Forecast( $data, null !== $loc ? (float) $loc['lat'] : null, null !== $loc ? (float) $loc['lng'] : null );
	}

	/** @return array<string,mixed> */
	public function get_status(): array {
		$status = get_option( self::OPTION_STATUS, array() );
		return is_array( $status ) ? $status : array();
	}

	/** @param array<string,mixed> $status Status. */
	public function save_status( array $status ): void {
		update_option( self::OPTION_STATUS, $status, false );
	}
}
