<?php
/**
 * Beispieldaten für die Vorschau im Backend, solange noch keine echten
 * Wetterdaten vorliegen (z. B. vor dem Eintragen des Standorts).
 *
 * @package B83\Wetter
 */

declare(strict_types=1);

namespace B83\Wetter\Data;

defined( 'ABSPATH' ) || exit;

final class Demo {

	public static function forecast(): Forecast {
		$tz    = wp_timezone();
		$start = ( new \DateTimeImmutable( 'today', $tz ) )->getTimestamp();

		// Tagesverlauf je Tag: [Wetterlage tagsüber, Höchstwert, Tiefstwert, Niederschlag gesamt].
		$days = array(
			array( 'fair', 14, 3, 0 ),
			array( 'partly', 12, 4, 0 ),
			array( 'showers', 9, 5, 4.2 ),
			array( 'snow_light', 2, -3, 3.0 ),
			array( 'cloudy', 5, -1, 0.2 ),
			array( 'clear', 8, -4, 0 ),
			array( 'fog', 6, 0, 0 ),
		);

		$steps = array();
		foreach ( $days as $d => $day ) {
			for ( $h = 0; $h < 24; $h++ ) {
				$t     = $start + ( $d * 24 + $h ) * HOUR_IN_SECONDS;
				$curve = ( 1 - cos( ( max( 0, $h - 4 ) / 20 ) * M_PI * 2 ) ) / 2;
				$day_h = $h >= 7 && $h < 19;
				$steps[] = array(
					't'        => $t,
					'span'     => 1,
					'temp'     => round( $day[2] + ( $day[1] - $day[2] ) * $curve, 1 ),
					'cond'     => $day_h ? $day[0] : ( in_array( $day[0], array( 'fair', 'clear' ), true ) ? 'clear' : 'cloudy' ),
					'precip'   => $day_h ? $day[3] / 12 : 0,
					'wind'     => 8 + ( $d * 3 ) % 14,
					'dir'      => 290,
					'gust'     => 24,
					'humidity' => 72,
					'cloud'    => 30,
					'snowline' => 1600,
					'src'      => 'geosphere',
				);
			}
		}

		return new Forecast(
			array(
				'steps'   => $steps,
				'sources' => array( 'geosphere', 'metno' ),
				'fetched' => time(),
			),
			47.56,
			13.35
		);
	}
}
