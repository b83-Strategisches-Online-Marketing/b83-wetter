<?php
/**
 * Einheitliche Wetterlagen. Beide Datenquellen werden auf diese Schlüssel
 * abgebildet, damit Icons, Texte und Tageswerte immer gleich funktionieren.
 *
 * @package B83\Wetter
 */

declare(strict_types=1);

namespace B83\Wetter;

defined( 'ABSPATH' ) || exit;

final class Conditions {

	/**
	 * Schlüssel => [Text Tag, Text Nacht, Schwere (Niederschlag), Bewölkung 0–4 oder null].
	 *
	 * @return array<string,array{0:string,1:string,2:int,3:int|null}>
	 */
	public static function all(): array {
		static $all = null;
		if ( null !== $all ) {
			return $all;
		}
		$all = array(
			'clear'        => array( __( 'Sonnig', 'b83-wetter' ), __( 'Klar', 'b83-wetter' ), 0, 0 ),
			'fair'         => array( __( 'Heiter', 'b83-wetter' ), __( 'Überwiegend klar', 'b83-wetter' ), 0, 1 ),
			'partly'       => array( __( 'Teilweise bewölkt', 'b83-wetter' ), __( 'Teilweise bewölkt', 'b83-wetter' ), 0, 2 ),
			'cloudy'       => array( __( 'Stark bewölkt', 'b83-wetter' ), __( 'Stark bewölkt', 'b83-wetter' ), 0, 3 ),
			'overcast'     => array( __( 'Bedeckt', 'b83-wetter' ), __( 'Bedeckt', 'b83-wetter' ), 0, 4 ),
			'fog'          => array( __( 'Nebel', 'b83-wetter' ), __( 'Nebel', 'b83-wetter' ), 1, null ),
			'rain_light'   => array( __( 'Leichter Regen', 'b83-wetter' ), __( 'Leichter Regen', 'b83-wetter' ), 3, null ),
			'showers'      => array( __( 'Regenschauer', 'b83-wetter' ), __( 'Regenschauer', 'b83-wetter' ), 4, null ),
			'snow_light'   => array( __( 'Leichter Schneefall', 'b83-wetter' ), __( 'Leichter Schneefall', 'b83-wetter' ), 4, null ),
			'snow_showers' => array( __( 'Schneeschauer', 'b83-wetter' ), __( 'Schneeschauer', 'b83-wetter' ), 5, null ),
			'rain'         => array( __( 'Regen', 'b83-wetter' ), __( 'Regen', 'b83-wetter' ), 6, null ),
			'sleet'        => array( __( 'Schneeregen', 'b83-wetter' ), __( 'Schneeregen', 'b83-wetter' ), 6, null ),
			'snow'         => array( __( 'Schneefall', 'b83-wetter' ), __( 'Schneefall', 'b83-wetter' ), 7, null ),
			'rain_heavy'   => array( __( 'Starker Regen', 'b83-wetter' ), __( 'Starker Regen', 'b83-wetter' ), 8, null ),
			'snow_heavy'   => array( __( 'Starker Schneefall', 'b83-wetter' ), __( 'Starker Schneefall', 'b83-wetter' ), 9, null ),
			'thunder'      => array( __( 'Gewitter', 'b83-wetter' ), __( 'Gewitter', 'b83-wetter' ), 10, null ),
		);
		return $all;
	}

	public static function exists( string $key ): bool {
		return isset( self::all()[ $key ] );
	}

	public static function label( string $key, bool $night = false ): string {
		$c = self::all()[ $key ] ?? null;
		if ( null === $c ) {
			return '';
		}
		return $night ? $c[1] : $c[0];
	}

	public static function severity( string $key ): int {
		return self::all()[ $key ][2] ?? 0;
	}

	public static function cloud_level( string $key ): ?int {
		return self::all()[ $key ][3] ?? null;
	}

	/** Wetterlage aus Bewölkungsgrad 0–4. */
	public static function from_cloud_level( float $level ): string {
		$map = array( 'clear', 'fair', 'partly', 'cloudy', 'overcast' );
		return $map[ max( 0, min( 4, (int) round( $level ) ) ) ];
	}

	/**
	 * Ersatz, wenn eine Quelle keinen Wettercode liefert.
	 *
	 * @param float|null $cloud  Bewölkung in Prozent.
	 * @param float      $precip Niederschlag in mm pro Stunde.
	 * @param float|null $temp   Temperatur in °C.
	 */
	public static function derive( ?float $cloud, float $precip, ?float $temp ): string {
		if ( $precip >= 0.1 ) {
			$snow = null !== $temp && $temp <= 0.5;
			if ( $precip >= 4 ) {
				return $snow ? 'snow_heavy' : 'rain_heavy';
			}
			if ( $precip >= 1 ) {
				return $snow ? 'snow' : 'rain';
			}
			return $snow ? 'snow_light' : 'rain_light';
		}
		if ( null === $cloud ) {
			return 'partly';
		}
		return self::from_cloud_level( $cloud / 25 );
	}

	/**
	 * Wettersymbol von GeoSphere Austria (sy, 1–32).
	 * Quelle: https://github.com/Geosphere-Austria/dataset-api-docs/issues/30
	 */
	public static function from_geosphere( int $sy ): ?string {
		return match ( true ) {
			1 === $sy               => 'clear',
			2 === $sy               => 'fair',
			3 === $sy               => 'partly',
			4 === $sy               => 'cloudy',
			5 === $sy               => 'overcast',
			6 === $sy, 7 === $sy    => 'fog',
			8 === $sy               => 'rain_light',
			9 === $sy               => 'rain',
			10 === $sy              => 'rain_heavy',
			$sy >= 11 && $sy <= 13  => 'sleet',
			14 === $sy              => 'snow_light',
			15 === $sy              => 'snow',
			16 === $sy              => 'snow_heavy',
			$sy >= 17 && $sy <= 19  => 'showers',
			$sy >= 20 && $sy <= 22  => 'sleet',
			$sy >= 23 && $sy <= 25  => 'snow_showers',
			$sy >= 26 && $sy <= 32  => 'thunder',
			default                 => null,
		};
	}

	/**
	 * Symbolcode von MET Norway, z. B. „partlycloudy_day“, „lightrainshowersandthunder_night“.
	 */
	public static function from_metno( string $code ): ?string {
		$code = strtolower( (string) preg_replace( '/_(day|night|polartwilight)$/', '', $code ) );
		if ( '' === $code ) {
			return null;
		}
		if ( str_contains( $code, 'thunder' ) ) {
			return 'thunder';
		}
		return match ( $code ) {
			'clearsky'                                               => 'clear',
			'fair'                                                   => 'fair',
			'partlycloudy'                                           => 'partly',
			'cloudy'                                                 => 'overcast',
			'fog'                                                    => 'fog',
			'lightrain'                                              => 'rain_light',
			'rain'                                                   => 'rain',
			'heavyrain'                                              => 'rain_heavy',
			'lightrainshowers', 'rainshowers', 'heavyrainshowers'    => 'showers',
			'lightsleet', 'sleet', 'heavysleet',
			'lightsleetshowers', 'sleetshowers', 'heavysleetshowers' => 'sleet',
			'lightsnow'                                              => 'snow_light',
			'snow'                                                   => 'snow',
			'heavysnow'                                              => 'snow_heavy',
			'lightsnowshowers', 'snowshowers', 'heavysnowshowers'    => 'snow_showers',
			default                                                  => null,
		};
	}

	/** Diese Wetterlagen haben ein eigenes Nacht-Icon (Mond statt Sonne). */
	public static function has_night_variant( string $key ): bool {
		return in_array( $key, array( 'clear', 'fair', 'partly', 'showers', 'snow_showers' ), true );
	}
}
