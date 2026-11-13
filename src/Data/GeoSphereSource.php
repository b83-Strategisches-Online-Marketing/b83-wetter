<?php
/**
 * GeoSphere Austria (ehemals ZAMG), Modell „nwp-v2-1h-1km“ (1 km).
 *
 * Offene Daten unter CC BY 4.0, ohne Schlüssel. Stündliche Werte für rund
 * 60 Stunden, Gebiet: Alpenraum / Mitteleuropa.
 *
 * Das frühere Modell „nwp-v1-1h-2500m“ liefert seit 4.11.2026 keine neuen
 * Läufe mehr (leere Antworten); seit Version 1.3.1 wird v2 abgefragt.
 * Dokumentation: https://dataset.api.hub.geosphere.at/v1/docs
 *
 * @package B83\Wetter
 */

declare(strict_types=1);

namespace B83\Wetter\Data;

use B83\Wetter\Conditions;

defined( 'ABSPATH' ) || exit;

final class GeoSphereSource implements SourceInterface {

	private const ENDPOINT = 'https://dataset.api.hub.geosphere.at/v1/timeseries/forecast/nwp-v2-1h-1km';

	/**
	 * Parameter: 2t Temperatur °C, tp Niederschlag in der letzten Stunde (mm),
	 * sy Wettersymbol, tcc Bewölkung %, 10u/10v Wind m/s, 10fg Böe m/s,
	 * 2r Luftfeuchte %, snowlmt Schneefallgrenze m.
	 */
	private const PARAMETERS = array( '2t', 'tp', 'sy', 'tcc', '10u', '10v', '10fg', '2r', 'snowlmt' );

	/** Gebiet des Modells: Breite min/max, Länge min/max. */
	private const BBOX = array( 43.01, 51.49, 5.04, 22.56 );

	/** Modelllauf älter als diese Anzahl Stunden = Daten gelten als nicht aktuell. */
	private const MAX_RUN_AGE = 18;

	public function key(): string {
		return 'geosphere';
	}

	public function label(): string {
		return 'GeoSphere Austria';
	}

	public function url(): string {
		return 'https://data.hub.geosphere.at/';
	}

	public function license(): string {
		return 'CC BY 4.0';
	}

	public function license_url(): string {
		return 'https://creativecommons.org/licenses/by/4.0/deed.de';
	}

	public function covers( float $lat, float $lng ): bool {
		return $lat >= self::BBOX[0] && $lat <= self::BBOX[1] && $lng >= self::BBOX[2] && $lng <= self::BBOX[3];
	}

	public function fetch( array $location ): array|\WP_Error {
		$lat = (float) $location['lat'];
		$lng = (float) $location['lng'];
		if ( ! $this->covers( $lat, $lng ) ) {
			return new \WP_Error( 'b83_wetter_outside', __( 'Der Standort liegt außerhalb des Gebiets von GeoSphere Austria.', 'b83-wetter' ) );
		}

		// Kommas bleiben unkodiert (so dokumentiert GeoSphere die Abfragen).
		$url = self::ENDPOINT
			. '?parameters=' . implode( ',', self::PARAMETERS )
			. '&lat_lon=' . sprintf( '%.4F,%.4F', $lat, $lng )
			. '&output_format=geojson';

		/**
		 * Abfrage-URL für GeoSphere Austria.
		 *
		 * @param string               $url      URL.
		 * @param array<string,string> $location Standort.
		 */
		$url = (string) apply_filters( 'b83_wetter_geosphere_url', $url, $location );

		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Accept'     => 'application/geo+json, application/json',
					'User-Agent' => Http::user_agent(),
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $code || ! is_array( $body ) ) {
			return new \WP_Error(
				'b83_wetter_http',
				/* translators: %d: HTTP-Statuscode */
				sprintf( __( 'GeoSphere Austria antwortet mit HTTP %d.', 'b83-wetter' ), $code )
			);
		}

		return $this->parse( $body );
	}

	/**
	 * GeoJSON in Zeitschritte umwandeln.
	 *
	 * @param array<string,mixed> $body Antwort.
	 * @return array<int,array<string,mixed>>|\WP_Error
	 */
	public function parse( array $body ): array|\WP_Error {
		$times  = (array) ( $body['timestamps'] ?? array() );
		$params = $body['features'][0]['properties']['parameters'] ?? null;

		// Veralteter Modelllauf: GeoSphere antwortet dann mit leeren Zeitreihen.
		$run = isset( $body['reference_time'] ) ? strtotime( (string) $body['reference_time'] ) : false;
		if ( false !== $run && $run < time() - self::MAX_RUN_AGE * HOUR_IN_SECONDS ) {
			return new \WP_Error(
				'b83_wetter_outdated',
				sprintf(
					/* translators: %s: Datum und Uhrzeit des letzten Modelllaufs */
					__( 'GeoSphere Austria liefert keine aktuellen Vorhersagen (letzter Modelllauf: %s).', 'b83-wetter' ),
					wp_date( _x( 'j.n.Y, H:i \\U\\h\\r', 'Datumsformat Status', 'b83-wetter' ), $run )
				)
			);
		}
		if ( empty( $times ) || ! is_array( $params ) ) {
			return new \WP_Error( 'b83_wetter_format', __( 'GeoSphere Austria: unerwartetes Datenformat.', 'b83-wetter' ) );
		}

		$series = static function ( string $name ) use ( $params ): array {
			$data = $params[ $name ]['data'] ?? array();
			return is_array( $data ) ? array_values( $data ) : array();
		};
		$num = static fn( array $list, int $i ): ?float => isset( $list[ $i ] ) && is_numeric( $list[ $i ] ) ? (float) $list[ $i ] : null;

		$t2m  = $series( '2t' );
		$tp   = $series( 'tp' );
		$sy   = $series( 'sy' );
		$tcc  = $series( 'tcc' );
		$u    = $series( '10u' );
		$v    = $series( '10v' );
		$gust = $series( '10fg' );
		$rh   = $series( '2r' );
		$snow = $series( 'snowlmt' );

		$steps = array();
		foreach ( array_values( $times ) as $i => $iso ) {
			$t = strtotime( (string) $iso );
			if ( false === $t ) {
				continue;
			}
			$temp = $num( $t2m, $i );
			if ( null === $temp ) {
				continue;
			}

			// tp zum Zeitpunkt T = Niederschlag der Stunde bis T. Der Zeitschritt beginnt bei T,
			// daher gehört der Wert des nächsten Zeitpunkts zu ihm.
			$precip = max( 0.0, $num( $tp, $i + 1 ) ?? 0.0 );

			// Bewölkung kommt in Prozent.
			$cloud = $num( $tcc, $i );
			$cloud = null !== $cloud ? max( 0.0, min( 100.0, $cloud ) ) : null;

			$symbol = $num( $sy, $i );
			$cond   = null !== $symbol ? Conditions::from_geosphere( (int) round( $symbol ) ) : null;
			$cond   = $cond ?? Conditions::derive( $cloud, $precip, $temp );

			$wu = $num( $u, $i );
			$wv = $num( $v, $i );
			$gs = $num( $gust, $i );

			$steps[] = array(
				't'        => $t,
				'span'     => 1,
				'temp'     => round( $temp, 1 ),
				'cond'     => $cond,
				'precip'   => round( $precip, 2 ),
				'wind'     => ( null !== $wu && null !== $wv ) ? round( sqrt( $wu ** 2 + $wv ** 2 ) * 3.6, 1 ) : null,
				'dir'      => ( null !== $wu && null !== $wv ) ? (int) round( fmod( rad2deg( atan2( -$wu, -$wv ) ) + 360, 360 ) ) : null,
				'gust'     => null !== $gs ? round( max( 0.0, $gs ) * 3.6, 1 ) : null,
				'humidity' => null !== $num( $rh, $i ) ? (int) round( (float) $num( $rh, $i ) ) : null,
				'cloud'    => null !== $cloud ? (int) round( $cloud ) : null,
				'snowline' => null !== $num( $snow, $i ) ? (int) round( (float) $num( $snow, $i ) ) : null,
				'src'      => $this->key(),
			);
		}

		if ( count( $steps ) < 6 ) {
			return new \WP_Error( 'b83_wetter_empty', __( 'GeoSphere Austria: zu wenige Daten erhalten.', 'b83-wetter' ) );
		}
		return $steps;
	}
}
