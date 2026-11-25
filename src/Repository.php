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

	/** Wetterdaten der zusätzlichen Orte, nach Kennung (Slug) geordnet. */
	public const OPTION_DATA_EXTRA = 'b83_wetter_data_extra';

	/** Kennung des Hauptorts (nur intern, nicht als Slug eines Zusatzorts erlaubt). */
	public const MAIN = 'main';

	/** @var array<string,mixed>|null */
	private ?array $settings = null;

	/** @var array<string,mixed>|null */
	private ?array $data = null;

	/** @var array<string,mixed>|null */
	private ?array $data_extra = null;

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
	 * @param string|null $slug Kennung eines Zusatzorts; leer oder „main“ = Hauptort.
	 * @return array{name:string,lat:string,lng:string,altitude:string}|null
	 */
	public function location( ?string $slug = null ): ?array {
		$s = $this->get_settings();
		if ( $this->is_main( $slug ) ) {
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
		foreach ( (array) $s['locations'] as $loc ) {
			if ( ( $loc['slug'] ?? '' ) === $slug ) {
				return array(
					'name'     => (string) $loc['name'],
					'lat'      => (string) $loc['lat'],
					'lng'      => (string) $loc['lng'],
					'altitude' => (string) $loc['altitude'],
				);
			}
		}
		return null;
	}

	/**
	 * Alle vollständigen Orte: Kennung => Standort. Der Hauptort steht unter „main“ an erster Stelle.
	 *
	 * @return array<string,array{name:string,lat:string,lng:string,altitude:string}>
	 */
	public function locations(): array {
		$list = array();
		$main = $this->location();
		if ( null !== $main ) {
			$list[ self::MAIN ] = $main;
		}
		foreach ( (array) $this->get_settings()['locations'] as $loc ) {
			$slug = (string) ( $loc['slug'] ?? '' );
			$item = '' !== $slug ? $this->location( $slug ) : null;
			if ( null !== $item ) {
				$list[ $slug ] = $item;
			}
		}

		/**
		 * Orte, für die Wetterdaten abgerufen werden.
		 *
		 * @param array<string,array<string,string>> $list Kennung => name, lat, lng, altitude.
		 */
		return (array) apply_filters( 'b83_wetter_locations', $list );
	}

	/** Hauptort (leer, „main“) oder Zusatzort? */
	public function is_main( ?string $slug ): bool {
		return null === $slug || '' === $slug || self::MAIN === $slug;
	}

	/** Kennung des Standorts – gespeicherte Daten gelten nur für diesen Standort. */
	public function location_key( ?string $slug = null ): string {
		$loc = $this->location( $slug );
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

	/**
	 * Gespeicherte Daten eines Orts.
	 *
	 * @param string|null $slug Kennung; leer oder „main“ = Hauptort.
	 * @return array<string,mixed>
	 */
	public function get_data( ?string $slug = null ): array {
		if ( $this->is_main( $slug ) ) {
			if ( null === $this->data ) {
				$data       = get_option( self::OPTION_DATA, array() );
				$this->data = is_array( $data ) ? $data : array();
			}
			return $this->data;
		}
		$extra = $this->get_data_extra();
		return isset( $extra[ $slug ] ) && is_array( $extra[ $slug ] ) ? $extra[ $slug ] : array();
	}

	/** @return array<string,mixed> */
	private function get_data_extra(): array {
		if ( null === $this->data_extra ) {
			$data             = get_option( self::OPTION_DATA_EXTRA, array() );
			$this->data_extra = is_array( $data ) ? $data : array();
		}
		return $this->data_extra;
	}

	/**
	 * @param array<string,mixed> $data Wetterdaten.
	 * @param string|null         $slug Kennung; leer oder „main“ = Hauptort.
	 */
	public function save_data( array $data, ?string $slug = null ): void {
		if ( $this->is_main( $slug ) ) {
			update_option( self::OPTION_DATA, $data, false );
			$this->data = $data;
			return;
		}
		$extra          = $this->get_data_extra();
		$extra[ $slug ] = $data;
		// Daten gelöschter Orte entfernen.
		$known = array_keys( $this->locations() );
		$extra = array_intersect_key( $extra, array_flip( $known ) );
		update_option( self::OPTION_DATA_EXTRA, $extra, false );
		$this->data_extra = $extra;
	}

	/**
	 * Vorhersage für einen Standort (leer, wenn die Daten zu einem anderen Standort gehören).
	 *
	 * @param string|null $slug Kennung; leer oder „main“ = Hauptort.
	 */
	public function forecast( ?string $slug = null ): Forecast {
		$data = $this->get_data( $slug );
		$loc  = $this->location( $slug );
		if ( null === $loc || ( $data['location'] ?? '' ) !== $this->location_key( $slug ) ) {
			$data = array();
		}
		return new Forecast( $data, null !== $loc ? (float) $loc['lat'] : null, null !== $loc ? (float) $loc['lng'] : null );
	}

	/**
	 * Status eines Orts (Hauptort: oberste Ebene, Zusatzorte: unter „extra“).
	 *
	 * @param string|null $slug Kennung; leer oder „main“ = Hauptort.
	 * @return array<string,mixed>
	 */
	public function get_status( ?string $slug = null ): array {
		$status = get_option( self::OPTION_STATUS, array() );
		$status = is_array( $status ) ? $status : array();
		if ( $this->is_main( $slug ) ) {
			unset( $status['extra'] );
			return $status;
		}
		return isset( $status['extra'][ $slug ] ) && is_array( $status['extra'][ $slug ] ) ? $status['extra'][ $slug ] : array();
	}

	/**
	 * @param array<string,mixed> $status Status.
	 * @param string|null         $slug   Kennung; leer oder „main“ = Hauptort.
	 */
	public function save_status( array $status, ?string $slug = null ): void {
		$all = get_option( self::OPTION_STATUS, array() );
		$all = is_array( $all ) ? $all : array();
		if ( $this->is_main( $slug ) ) {
			unset( $status['extra'] );
			if ( isset( $all['extra'] ) ) {
				$status['extra'] = $all['extra'];
			}
			$all = $status;
		} else {
			$all['extra']          = isset( $all['extra'] ) && is_array( $all['extra'] ) ? $all['extra'] : array();
			$all['extra'][ $slug ] = $status;
		}
		update_option( self::OPTION_STATUS, $all, false );
	}
}
