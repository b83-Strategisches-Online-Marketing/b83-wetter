<?php
/**
 * MET Norway (Norwegisches Meteorologisches Institut), Locationforecast 2.0.
 *
 * Offene Daten unter NLOD 2.0 und CC BY 4.0, weltweit, rund 9 Tage.
 * Nutzungsbedingungen: eindeutiger User-Agent mit Kontakt, Koordinaten mit
 * höchstens 4 Nachkommastellen, nicht öfter als nötig abfragen.
 * Dokumentation: https://docs.api.met.no/doc/TermsOfService
 *
 * Hinweis: Die Bezeichnung „Yr“ und das Yr-Logo dürfen nicht verwendet werden.
 *
 * @package B83\Wetter
 */

declare(strict_types=1);

namespace B83\Wetter\Data;

use B83\Wetter\Conditions;

defined( 'ABSPATH' ) || exit;

final class MetNorwaySource implements SourceInterface {

	private const ENDPOINT = 'https://api.met.no/weatherapi/locationforecast/2.0/compact';

	public function key(): string {
		return 'metno';
	}

	public function label(): string {
		return 'MET Norway';
	}

	public function url(): string {
		return 'https://api.met.no/';
	}

	public function license(): string {
		return 'CC BY 4.0';
	}

	public function license_url(): string {
		return 'https://creativecommons.org/licenses/by/4.0/deed.de';
	}

	public function covers( float $lat, float $lng ): bool {
		return $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180;
	}

	public function fetch( array $location ): array|\WP_Error {
		$args = array(
			'lat' => sprintf( '%.4F', (float) $location['lat'] ),
			'lon' => sprintf( '%.4F', (float) $location['lng'] ),
		);
		if ( isset( $location['altitude'] ) && '' !== (string) $location['altitude'] ) {
			$args['altitude'] = (string) (int) $location['altitude'];
		}
		$url = self::ENDPOINT . '?' . http_build_query( $args, '', '&', PHP_QUERY_RFC3986 );

		/**
		 * Abfrage-URL für MET Norway.
		 *
		 * @param string               $url      URL.
		 * @param array<string,string> $location Standort.
		 */
		$url = (string) apply_filters( 'b83_wetter_metno_url', $url, $location );

		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Accept'     => 'application/json',
					'User-Agent' => Http::user_agent(),
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		// 203 = Produkt wird bald abgelöst, Daten sind aber gültig.
		if ( ! in_array( $code, array( 200, 203 ), true ) || ! is_array( $body ) ) {
			return new \WP_Error(
				'b83_wetter_http',
				/* translators: %d: HTTP-Statuscode */
				sprintf( __( 'MET Norway antwortet mit HTTP %d.', 'b83-wetter' ), $code )
			);
		}

		return $this->parse( $body );
	}

	/**
	 * Antwort in Zeitschritte umwandeln.
	 *
	 * @param array<string,mixed> $body Antwort.
	 * @return array<int,array<string,mixed>>|\WP_Error
	 */
	public function parse( array $body ): array|\WP_Error {
		$series = $body['properties']['timeseries'] ?? null;
		if ( ! is_array( $series ) || empty( $series ) ) {
			return new \WP_Error( 'b83_wetter_format', __( 'MET Norway: unerwartetes Datenformat.', 'b83-wetter' ) );
		}
		$series = array_values( array_filter( $series, 'is_array' ) );

		$steps = array();
		$count = count( $series );
		for ( $i = 0; $i < $count; $i++ ) {
			$entry = $series[ $i ];
			$t     = strtotime( (string) ( $entry['time'] ?? '' ) );
			if ( false === $t ) {
				continue;
			}
			$next = isset( $series[ $i + 1 ]['time'] ) ? strtotime( (string) $series[ $i + 1 ]['time'] ) : false;
			$gap  = false !== $next ? (int) round( ( $next - $t ) / HOUR_IN_SECONDS ) : 6;

			$details = (array) ( $entry['data']['instant']['details'] ?? array() );
			if ( ! isset( $details['air_temperature'] ) || ! is_numeric( $details['air_temperature'] ) ) {
				continue;
			}

			// Stündlich, solange vorhanden; danach 6-Stunden-Blöcke.
			if ( $gap <= 1 && isset( $entry['data']['next_1_hours'] ) ) {
				$block = (array) $entry['data']['next_1_hours'];
				$span  = 1;
			} elseif ( isset( $entry['data']['next_6_hours'] ) ) {
				$block = (array) $entry['data']['next_6_hours'];
				$span  = 6;
			} elseif ( isset( $entry['data']['next_12_hours'] ) ) {
				$block = (array) $entry['data']['next_12_hours'];
				$span  = 6;
			} else {
				continue;
			}

			$precip = isset( $block['details']['precipitation_amount'] ) && is_numeric( $block['details']['precipitation_amount'] )
				? max( 0.0, (float) $block['details']['precipitation_amount'] )
				: 0.0;
			$temp   = (float) $details['air_temperature'];
			$cloud  = isset( $details['cloud_area_fraction'] ) && is_numeric( $details['cloud_area_fraction'] ) ? (float) $details['cloud_area_fraction'] : null;
			$cond   = Conditions::from_metno( (string) ( $block['summary']['symbol_code'] ?? '' ) )
				?? Conditions::derive( $cloud, $precip / $span, $temp );

			$steps[] = array(
				't'        => $t,
				'span'     => $span,
				'temp'     => round( $temp, 1 ),
				'cond'     => $cond,
				'precip'   => round( $precip, 2 ),
				'wind'     => isset( $details['wind_speed'] ) && is_numeric( $details['wind_speed'] ) ? round( (float) $details['wind_speed'] * 3.6, 1 ) : null,
				'dir'      => isset( $details['wind_from_direction'] ) && is_numeric( $details['wind_from_direction'] ) ? (int) round( (float) $details['wind_from_direction'] ) : null,
				'gust'     => null,
				'humidity' => isset( $details['relative_humidity'] ) && is_numeric( $details['relative_humidity'] ) ? (int) round( (float) $details['relative_humidity'] ) : null,
				'cloud'    => null !== $cloud ? (int) round( $cloud ) : null,
				'snowline' => null,
				'src'      => $this->key(),
			);

			// Nach einem 6-Stunden-Block die überdeckten Zeitpunkte überspringen.
			if ( 6 === $span && $gap < 6 ) {
				$skip_until = $t + 6 * HOUR_IN_SECONDS;
				while ( isset( $series[ $i + 1 ]['time'] ) && strtotime( (string) $series[ $i + 1 ]['time'] ) < $skip_until ) {
					++$i;
				}
			}
		}

		if ( count( $steps ) < 6 ) {
			return new \WP_Error( 'b83_wetter_empty', __( 'MET Norway: zu wenige Daten erhalten.', 'b83-wetter' ) );
		}
		return $steps;
	}
}
