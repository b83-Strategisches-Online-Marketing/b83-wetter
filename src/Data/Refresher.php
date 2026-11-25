<?php
/**
 * Holt die Vorhersage von der gewählten Quelle, springt bei Ausfall auf die
 * zweite Quelle um und ergänzt Tage, die die erste Quelle nicht abdeckt
 * (GeoSphere rechnet rund 2,5 Tage voraus, MET Norway rund 9 Tage).
 *
 * Schlägt alles fehl, bleiben die zuletzt gültigen Daten erhalten.
 *
 * @package B83\Wetter
 */

declare(strict_types=1);

namespace B83\Wetter\Data;

use B83\Wetter\Repository;

defined( 'ABSPATH' ) || exit;

final class Refresher {

	private const LOCK = 'b83_wetter_lock';

	public function __construct( private Repository $repository ) {}

	/**
	 * Daten aller Orte aktualisieren (Hauptort zuerst).
	 *
	 * Jeder Ort wird für sich abgerufen und gespeichert, ein Fehler bei einem Ort
	 * blockiert die anderen nicht.
	 *
	 * @return bool true, wenn für mindestens einen Ort neue Daten gespeichert wurden.
	 */
	public function refresh(): bool {
		$locations = $this->repository->locations();

		if ( empty( $locations ) ) {
			$status                 = $this->repository->get_status();
			$status['last_attempt'] = time();
			$status['errors']       = array( 'location' => __( 'Kein Standort eingetragen.', 'b83-wetter' ) );
			$this->repository->save_status( $status );
			return false;
		}

		$any   = false;
		$first = true;
		foreach ( array_keys( $locations ) as $slug ) {
			if ( ! $first ) {
				// Kurze Pause, damit die Anfragen an die Wetterdienste nicht gleichzeitig eintreffen.
				sleep( 1 );
			}
			$first = false;
			$any   = $this->refresh_location( (string) $slug ) || $any;
		}
		return $any;
	}

	/**
	 * Daten eines Orts aktualisieren.
	 *
	 * @param string $slug Kennung; „main“ = Hauptort.
	 * @return bool true, wenn neue Daten gespeichert wurden.
	 */
	public function refresh_location( string $slug ): bool {
		$location = $this->repository->location( $slug );
		$status   = $this->repository->get_status( $slug );

		$status['last_attempt'] = time();
		$status['errors']       = array();

		if ( null === $location ) {
			$status['errors']['location'] = __( 'Kein Standort eingetragen.', 'b83-wetter' );
			$this->repository->save_status( $status, $slug );
			return false;
		}

		$lock = self::LOCK . ( $this->repository->is_main( $slug ) ? '' : '_' . $slug );
		if ( get_transient( $lock ) ) {
			return false;
		}
		set_transient( $lock, 1, MINUTE_IN_SECONDS );

		$settings  = $this->repository->get_settings();
		$sources   = $this->repository->sources();
		$lat       = (float) $location['lat'];
		$lng       = (float) $location['lng'];
		$primary   = $sources[ $settings['source'] ] ?? reset( $sources );
		$secondary = null;
		if ( '1' === $settings['fallback'] ) {
			foreach ( $sources as $key => $source ) {
				if ( $key !== $primary->key() && $source->covers( $lat, $lng ) ) {
					$secondary = $source;
					break;
				}
			}
		}

		$steps   = array();
		$used    = array();
		$mode    = 'primary';
		$result1 = $primary->fetch( $location );

		if ( is_wp_error( $result1 ) ) {
			$status['errors'][ $primary->key() ] = $result1->get_error_message();
			if ( null !== $secondary ) {
				$result2 = $secondary->fetch( $location );
				if ( is_wp_error( $result2 ) ) {
					$status['errors'][ $secondary->key() ] = $result2->get_error_message();
				} else {
					$steps = $result2;
					$used  = array( $secondary->key() );
					$mode  = 'fallback';
				}
			}
		} else {
			$steps = $result1;
			$used  = array( $primary->key() );

			// Fehlende Tage von der zweiten Quelle ergänzen.
			$last = end( $result1 );
			$end  = (int) $last['t'] + max( 1, (int) $last['span'] ) * HOUR_IN_SECONDS;
			if ( null !== $secondary && $end < time() + ( $this->days_needed() - 1 ) * DAY_IN_SECONDS + 12 * HOUR_IN_SECONDS ) {
				$result2 = $secondary->fetch( $location );
				if ( is_wp_error( $result2 ) ) {
					$status['errors'][ $secondary->key() ] = $result2->get_error_message();
				} else {
					$added = 0;
					foreach ( $result2 as $step ) {
						if ( (int) $step['t'] >= $end ) {
							$steps[] = $step;
							++$added;
						}
					}
					if ( $added > 0 ) {
						$used[] = $secondary->key();
						$mode   = 'combined';
					}
				}
			}
		}

		delete_transient( $lock );

		if ( empty( $steps ) ) {
			$this->repository->save_status( $status, $slug );
			return false;
		}

		$data = array(
			'steps'    => array_values( $steps ),
			'sources'  => $used,
			'mode'     => $mode,
			'fetched'  => time(),
			'location' => $this->repository->location_key( $slug ),
		);
		$this->repository->save_data( $data, $slug );

		$status['last_success'] = $data['fetched'];
		$status['mode']         = $mode;
		$status['sources']      = $used;
		$this->repository->save_status( $status, $slug );

		/**
		 * Nach dem Speichern neuer Wetterdaten.
		 *
		 * @param array<string,mixed> $data Wetterdaten.
		 * @param string              $slug Kennung des Orts („main“ = Hauptort).
		 */
		do_action( 'b83_wetter_data_updated', $data, $slug );
		return true;
	}

	/** Wie viele Tage braucht die Website (größte Einstellung von Kompakt/Ausführlich)? */
	private function days_needed(): int {
		$design = $this->repository->design();
		$days   = max( (int) $design['detail_days'], (int) $design['compact_days'] + 1, 3 );

		/**
		 * Anzahl der Tage, für die Daten vorhanden sein sollen (steuert das Ergänzen aus der zweiten Quelle).
		 *
		 * @param int $days Tage.
		 */
		return max( 1, min( 9, (int) apply_filters( 'b83_wetter_days_needed', $days ) ) );
	}

	/**
	 * Liegen die Daten eines Orts länger als das Eineinhalbfache des Intervalls zurück?
	 *
	 * @param string|null $slug Kennung; leer = Hauptort.
	 */
	public function is_stale( ?string $slug = null ): bool {
		$data     = $this->repository->get_data( $slug );
		$interval = (int) $this->repository->get_settings()['interval'] * MINUTE_IN_SECONDS;
		if ( ( $data['location'] ?? '' ) !== $this->repository->location_key( $slug ) ) {
			return true;
		}
		return (int) ( $data['fetched'] ?? 0 ) < time() - (int) ( $interval * 1.5 );
	}

	/** Ist mindestens ein Ort veraltet? */
	public function is_stale_any(): bool {
		foreach ( array_keys( $this->repository->locations() ) as $slug ) {
			if ( $this->is_stale( (string) $slug ) ) {
				return true;
			}
		}
		return false;
	}
}
