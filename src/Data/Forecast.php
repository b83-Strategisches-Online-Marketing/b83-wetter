<?php
/**
 * Aufbereitung der gespeicherten Vorhersage: aktuelle Werte und Tageswerte.
 *
 * Gespeichert wird eine Liste von Zeitschritten (stündlich, bei MET Norway
 * nach rund 2,5 Tagen sechsstündlich). Tageswerte werden erst bei der Ausgabe
 * in der Zeitzone der Website gebildet, damit „Heute“ immer stimmt.
 *
 * Zeitschritt:
 *   t        Unix-Zeit (Beginn)
 *   span     Länge in Stunden (1 oder 6)
 *   temp     Temperatur °C
 *   cond     Wetterlage (Conditions)
 *   precip   Niederschlag mm im Zeitschritt
 *   wind     Wind km/h, gust Böen km/h, dir Richtung ° (woher)
 *   humidity Luftfeuchte %, cloud Bewölkung %, snowline Schneefallgrenze m
 *   src      Quelle (geosphere | metno)
 *
 * @package B83\Wetter
 */

declare(strict_types=1);

namespace B83\Wetter\Data;

use B83\Wetter\Conditions;

defined( 'ABSPATH' ) || exit;

final class Forecast {

	/** @var array<int,array<string,mixed>> */
	private array $steps;

	/**
	 * @param array<string,mixed> $data     Gespeicherte Daten (Repository::get_data()).
	 * @param float|null          $lat      Breite (für Sonnenauf-/-untergang).
	 * @param float|null          $lng      Länge.
	 */
	public function __construct( private array $data, private ?float $lat = null, private ?float $lng = null ) {
		$steps = array();
		foreach ( (array) ( $data['steps'] ?? array() ) as $step ) {
			if ( is_array( $step ) && isset( $step['t'] ) ) {
				$steps[] = $step;
			}
		}
		usort( $steps, static fn( array $a, array $b ): int => (int) $a['t'] <=> (int) $b['t'] );
		$this->steps = $steps;
	}

	public function is_empty(): bool {
		return empty( $this->steps );
	}

	public function fetched(): int {
		return (int) ( $this->data['fetched'] ?? 0 );
	}

	/** @return string[] Verwendete Quellen. */
	public function sources(): array {
		return array_values( array_filter( array_map( 'strval', (array) ( $this->data['sources'] ?? array() ) ) ) );
	}

	/**
	 * Zeitschritt, der den Zeitpunkt $now enthält.
	 *
	 * @return array<string,mixed>|null
	 */
	public function current( ?int $now = null ): ?array {
		$now = $now ?? time();
		foreach ( $this->steps as $step ) {
			$end = (int) $step['t'] + max( 1, (int) ( $step['span'] ?? 1 ) ) * HOUR_IN_SECONDS;
			if ( $now < $end ) {
				// Daten liegen in der Zukunft (Uhr falsch / Daten verschoben): nur bis 3 h akzeptieren.
				if ( (int) $step['t'] - $now > 3 * HOUR_IN_SECONDS ) {
					return null;
				}
				$step['night'] = $this->is_night( $now );
				return $step;
			}
		}
		return null;
	}

	/** Bis wann reichen die Daten (Unix-Zeit des Endes)? */
	public function coverage_end(): int {
		$last = end( $this->steps );
		return $last ? (int) $last['t'] + max( 1, (int) ( $last['span'] ?? 1 ) ) * HOUR_IN_SECONDS : 0;
	}

	/**
	 * Tageswerte ab heute.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function days( int $count, ?int $now = null ): array {
		$now   = $now ?? time();
		$tz    = wp_timezone();
		$today = ( new \DateTimeImmutable( '@' . $now ) )->setTimezone( $tz )->format( 'Y-m-d' );

		$groups = array();
		foreach ( $this->steps as $step ) {
			$local = ( new \DateTimeImmutable( '@' . (int) $step['t'] ) )->setTimezone( $tz );
			$date  = $local->format( 'Y-m-d' );
			if ( $date < $today ) {
				continue;
			}
			$step['hour']     = (int) $local->format( 'G' );
			$groups[ $date ][] = $step;
		}

		$days = array();
		foreach ( $groups as $date => $steps ) {
			$day = $this->summarize( $date, $steps, $date === $today );
			if ( null === $day ) {
				continue;
			}
			$days[] = $day;
			if ( count( $days ) >= $count ) {
				break;
			}
		}
		return $days;
	}

	/**
	 * @param array<int,array<string,mixed>> $steps Zeitschritte eines Tages.
	 * @return array<string,mixed>|null
	 */
	private function summarize( string $date, array $steps, bool $is_today ): ?array {
		$hours = 0;
		foreach ( $steps as $s ) {
			$hours += max( 1, (int) ( $s['span'] ?? 1 ) );
		}
		// Unvollständige Tage am Ende der Vorhersage weglassen (heute zählt immer).
		if ( ! $is_today && $hours < 12 ) {
			return null;
		}

		$temps  = array();
		$precip = 0.0;
		$wind   = 0.0;
		$gust   = 0.0;
		$snow   = array();
		$day    = array();

		foreach ( $steps as $s ) {
			if ( isset( $s['temp'] ) && is_numeric( $s['temp'] ) ) {
				$temps[] = (float) $s['temp'];
			}
			$precip += (float) ( $s['precip'] ?? 0 );
			$wind    = max( $wind, (float) ( $s['wind'] ?? 0 ) );
			$gust    = max( $gust, (float) ( $s['gust'] ?? 0 ) );
			if ( isset( $s['snowline'] ) && is_numeric( $s['snowline'] ) ) {
				$snow[] = (float) $s['snowline'];
			}
			// Tagsüber: Zeitschritte, die zwischen 7 und 19 Uhr liegen oder dorthin reichen.
			$start = (int) $s['hour'];
			$end   = $start + max( 1, (int) ( $s['span'] ?? 1 ) );
			if ( $end > 7 && $start < 19 ) {
				$day[] = $s;
			}
		}
		if ( empty( $temps ) ) {
			return null;
		}

		return array(
			'date'     => $date,
			'max'      => max( $temps ),
			'min'      => min( $temps ),
			'precip'   => round( $precip, 1 ),
			'wind'     => $wind,
			'gust'     => $gust,
			'snowline' => empty( $snow ) ? null : min( $snow ),
			'cond'     => $this->day_condition( empty( $day ) ? $steps : $day, $precip ),
			'partial'  => $is_today && $hours < 18,
		);
	}

	/**
	 * Wetterlage eines Tages: bei nennenswertem Niederschlag die markanteste
	 * Niederschlagsart, sonst die mittlere Bewölkung tagsüber.
	 *
	 * @param array<int,array<string,mixed>> $steps Zeitschritte tagsüber.
	 */
	private function day_condition( array $steps, float $precip_total ): string {
		$conds = array();
		foreach ( $steps as $s ) {
			$c = (string) ( $s['cond'] ?? '' );
			if ( Conditions::exists( $c ) ) {
				$weight             = max( 1, (int) ( $s['span'] ?? 1 ) );
				$conds[ $c ]        = ( $conds[ $c ] ?? 0 ) + $weight;
			}
		}
		if ( empty( $conds ) ) {
			return 'partly';
		}

		$total = array_sum( $conds );

		// Gewitter zählt immer, wenn es vorkommt.
		if ( isset( $conds['thunder'] ) ) {
			return 'thunder';
		}

		if ( $precip_total >= 0.5 ) {
			$best = '';
			foreach ( array_keys( $conds ) as $c ) {
				if ( Conditions::severity( $c ) >= 3 && ( '' === $best || Conditions::severity( $c ) > Conditions::severity( $best ) ) ) {
					$best = $c;
				}
			}
			if ( '' !== $best ) {
				// Nur kurz starker Niederschlag: eine Stufe milder darstellen.
				if ( $conds[ $best ] / $total < .2 ) {
					$best = match ( $best ) {
						'rain_heavy', 'rain' => 'showers',
						'snow_heavy', 'snow' => 'snow_showers',
						default              => $best,
					};
				}
				return $best;
			}
		}

		if ( ( $conds['fog'] ?? 0 ) / $total >= .5 ) {
			return 'fog';
		}

		$sum    = 0.0;
		$weight = 0;
		foreach ( $conds as $c => $w ) {
			$level = Conditions::cloud_level( $c );
			if ( null === $level ) {
				// Niederschlag ohne nennenswerte Menge: als stark bewölkt werten.
				$level = 3;
			}
			$sum    += $level * $w;
			$weight += $w;
		}
		return Conditions::from_cloud_level( $weight > 0 ? $sum / $weight : 2 );
	}

	/** Nacht am Standort (Sonne unter dem Horizont)? */
	public function is_night( int $time ): bool {
		if ( null === $this->lat || null === $this->lng ) {
			$hour = (int) ( new \DateTimeImmutable( '@' . $time ) )->setTimezone( wp_timezone() )->format( 'G' );
			return $hour < 6 || $hour >= 19;
		}
		$info = date_sun_info( $time, $this->lat, $this->lng );
		if ( ! is_array( $info ) || ! is_int( $info['sunrise'] ?? null ) || ! is_int( $info['sunset'] ?? null ) ) {
			return false;
		}
		return $time < $info['sunrise'] || $time > $info['sunset'];
	}
}
