<?php
/**
 * Wetter-Icons als Inline-SVG (eigene Zeichnungen, GPL wie das Plugin).
 *
 * Alle Icon-Sets verwenden dieselben Formen; das Aussehen kommt aus
 * assets/css/frontend.css über die Klasse b83w-set-<set> am Widget.
 * So lassen sich Farben und Strichstärken auch per CSS im Theme anpassen.
 * Es werden keine externen Dateien oder Schriften geladen.
 *
 * @package B83\Wetter
 */

declare(strict_types=1);

namespace B83\Wetter;

defined( 'ABSPATH' ) || exit;

final class Icons {

	/** Wolke in voller Größe (Ansichtsfeld 64 × 64). */
	private const CLOUD = 'M17 48H47a9 9 0 0 0 1.5-17.9 13 13 0 0 0-24.9-2.7A10.5 10.5 0 0 0 17 48z';

	/** Mondsichel um die Mitte 32/32. */
	private const MOON = 'M38 10.5a21.5 21.5 0 1 0 15.5 31.5A17 17 0 0 1 38 10.5z';

	private static int $counter = 0;

	/** @return array<string,string> Schlüssel => Bezeichnung */
	public static function sets(): array {
		$sets = array(
			'farbig' => __( 'Farbig', 'b83-wetter' ),
			'linie'  => __( 'Linie (einfarbig, passt sich der Textfarbe an)', 'b83-wetter' ),
			'duoton' => __( 'Duoton (Textfarbe + Akzentfarbe)', 'b83-wetter' ),
			'flach'  => __( 'Flach (farbig, ohne Konturen)', 'b83-wetter' ),
		);

		/**
		 * Verfügbare Icon-Sets. Eigene Sets brauchen passende CSS-Regeln für .b83w-set-<schlüssel>.
		 *
		 * @param array<string,string> $sets Schlüssel => Bezeichnung.
		 */
		return (array) apply_filters( 'b83_wetter_icon_sets', $sets );
	}

	/**
	 * SVG für eine Wetterlage.
	 *
	 * @param string $condition Schlüssel aus Conditions.
	 * @param bool   $night     Nacht-Variante (Mond statt Sonne), wenn vorhanden.
	 * @param string $label     Barrierefreier Text ('' = nur Dekoration).
	 */
	public static function svg( string $condition, bool $night = false, string $label = '' ): string {
		if ( ! Conditions::exists( $condition ) ) {
			$condition = 'partly';
		}
		$night = $night && Conditions::has_night_variant( $condition );
		$id    = 'b83w' . self::uid();

		$body = self::body( $condition, $night, $id );

		$a11y = '' !== $label
			? ' role="img" aria-label="' . esc_attr( $label ) . '"'
			: ' aria-hidden="true"';

		$svg = '<svg class="b83w-icon b83w-icon--' . esc_attr( $condition ) . ( $night ? ' is-night' : '' ) . '" viewBox="0 0 64 64" focusable="false"' . $a11y . '>' . $body . '</svg>';

		/**
		 * SVG eines Wetter-Icons – zum Ersetzen durch eigene Grafiken.
		 *
		 * @param string $svg       SVG-Markup.
		 * @param string $condition Wetterlage.
		 * @param bool   $night     Nacht-Variante.
		 */
		return (string) apply_filters( 'b83_wetter_icon_svg', $svg, $condition, $night );
	}

	private static function uid(): string {
		++self::$counter;
		static $prefix = null;
		if ( null === $prefix ) {
			// Eindeutig auch, wenn mehrere Widgets per REST nachgeladen werden.
			$prefix = substr( md5( uniqid( '', true ) ), 0, 5 );
		}
		return $prefix . self::$counter;
	}

	/* ---------------------------------------------------------------------
	 * Aufbau der Icons
	 * ------------------------------------------------------------------- */

	private static function body( string $c, bool $night, string $id ): string {
		$celestial_small = static fn( float $x, float $y, float $s ): string => $night
			? self::moon( $x, $y, $s )
			: self::sun( $x, $y, $s );

		switch ( $c ) {
			case 'clear':
				return $night ? self::moon( 32, 32, 1 ) : self::sun( 32, 32, 1 );

			case 'fair':
				$cloud = 'translate(23 25) scale(.66)';
				return self::masked( $celestial_small( 25, 25, .85 ), $cloud, $id )
					. self::cloud( $cloud );

			case 'partly':
				$cloud = 'translate(3 5)';
				return self::masked( $celestial_small( 22, 22, .72 ), $cloud, $id )
					. self::cloud( $cloud );

			case 'cloudy':
			case 'overcast':
				$dark  = 'overcast' === $c;
				$front = 'translate(-2 7)';
				return self::masked( self::cloud( 'translate(19 3) scale(.72)', 'b83w-cloud--back' . ( $dark ? ' is-dark' : '' ) ), $front, $id )
					. self::cloud( $front, $dark ? 'is-dark' : '' );

			case 'fog':
				return self::cloud( 'translate(0 -9)' )
					. self::lines(
						array(
							array( 12, 46, 46, 46 ),
							array( 18, 53, 52, 53 ),
							array( 14, 60, 40, 60 ),
						),
						'b83w-fog'
					);

			case 'rain_light':
			case 'rain':
			case 'rain_heavy':
				$drops = array(
					'rain_light' => array( array( 26, 45 ), array( 38, 45 ) ),
					'rain'       => array( array( 21, 45 ), array( 32, 45 ), array( 43, 45 ) ),
					'rain_heavy' => array( array( 19, 44 ), array( 28, 44 ), array( 37, 44 ), array( 46, 44 ) ),
				)[ $c ];
				return self::cloud( 'translate(0 -10)', 'rain_heavy' === $c ? 'is-dark' : '' )
					. self::drops( $drops, 'rain_heavy' === $c ? 12 : 9 );

			case 'showers':
			case 'snow_showers':
				$cloud = 'translate(7 -3) scale(.86)';
				$fall  = 'showers' === $c
					? self::drops( array( array( 27, 46 ), array( 39, 46 ) ), 9 )
					: self::flakes( array( array( 27, 50 ), array( 41, 50 ) ) );
				return self::masked( $celestial_small( 20, 18, .62 ), $cloud, $id )
					. self::cloud( $cloud )
					. $fall;

			case 'sleet':
				return self::cloud( 'translate(0 -10)' )
					. self::drops( array( array( 21, 45 ), array( 45, 45 ) ), 9 )
					. self::flakes( array( array( 32, 51 ) ) );

			case 'snow_light':
			case 'snow':
			case 'snow_heavy':
				$flakes = array(
					'snow_light' => array( array( 25, 49 ), array( 40, 49 ) ),
					'snow'       => array( array( 21, 47 ), array( 32, 54 ), array( 43, 47 ) ),
					'snow_heavy' => array( array( 19, 46 ), array( 32, 46 ), array( 45, 46 ), array( 25.5, 57 ), array( 38.5, 57 ) ),
				)[ $c ];
				return self::cloud( 'translate(0 -10)', 'snow_heavy' === $c ? 'is-dark' : '' )
					. self::flakes( $flakes );

			case 'thunder':
				return self::cloud( 'translate(0 -10)', 'is-dark' )
					. self::drops( array( array( 19, 45 ), array( 47, 45 ) ), 9 )
					. '<path class="b83w-bolt" d="M35 37 26 51h7l-4 12 12-16h-7l4-10z"/>';
		}
		return self::cloud( '' );
	}

	/** Hintergrund-Element hinter einer Wolke: wird an der Wolke ausgeschnitten (für Linien-Icons). */
	private static function masked( string $back, string $cloud_transform, string $id ): string {
		$mask = '<mask id="' . esc_attr( $id ) . '" maskUnits="userSpaceOnUse" x="0" y="0" width="64" height="64">'
			. '<rect width="64" height="64" fill="#fff"/>'
			. '<path d="' . self::CLOUD . '"' . ( '' !== $cloud_transform ? ' transform="' . esc_attr( $cloud_transform ) . '"' : '' ) . ' fill="#000" stroke="#000" stroke-width="7" stroke-linejoin="round"/>'
			. '</mask>';
		return $mask . '<g mask="url(#' . esc_attr( $id ) . ')">' . $back . '</g>';
	}

	private static function cloud( string $transform, string $modifier = '' ): string {
		return '<path class="b83w-cloud' . ( '' !== $modifier ? ' ' . esc_attr( $modifier ) : '' ) . '" d="' . self::CLOUD . '"'
			. ( '' !== $transform ? ' transform="' . esc_attr( $transform ) . '"' : '' ) . '/>';
	}

	private static function sun( float $cx, float $cy, float $scale ): string {
		$r     = 11 * $scale;
		$inner = 16 * $scale;
		$outer = 22.5 * $scale;
		$rays  = '';
		for ( $k = 0; $k < 8; $k++ ) {
			$a     = deg2rad( $k * 45 );
			$rays .= sprintf(
				'M%s %sL%s %s',
				self::n( $cx + cos( $a ) * $inner ),
				self::n( $cy + sin( $a ) * $inner ),
				self::n( $cx + cos( $a ) * $outer ),
				self::n( $cy + sin( $a ) * $outer )
			);
		}
		return '<path class="b83w-ray" d="' . $rays . '"/>'
			. '<circle class="b83w-sun" cx="' . self::n( $cx ) . '" cy="' . self::n( $cy ) . '" r="' . self::n( $r ) . '"/>';
	}

	private static function moon( float $cx, float $cy, float $scale ): string {
		$tx = $cx - 32 * $scale;
		$ty = $cy - 32 * $scale;
		return '<path class="b83w-moon" d="' . self::MOON . '" transform="translate(' . self::n( $tx ) . ' ' . self::n( $ty ) . ') scale(' . self::n( $scale ) . ')"/>';
	}

	/** @param array<int,array{0:float,1:float}> $points Startpunkte. */
	private static function drops( array $points, float $length ): string {
		$d = '';
		foreach ( $points as $p ) {
			$d .= sprintf( 'M%s %sl-%s %s', self::n( $p[0] ), self::n( $p[1] ), self::n( $length * .35 ), self::n( $length ) );
		}
		return '<path class="b83w-drop" d="' . $d . '"/>';
	}

	/** @param array<int,array{0:float,1:float}> $points Mittelpunkte. */
	private static function flakes( array $points ): string {
		$d = '';
		$r = 4.2;
		foreach ( $points as $p ) {
			foreach ( array( 90, 30, 150 ) as $deg ) {
				$a  = deg2rad( $deg );
				$dx = cos( $a ) * $r;
				$dy = sin( $a ) * $r;
				$d .= sprintf( 'M%s %sL%s %s', self::n( $p[0] - $dx ), self::n( $p[1] - $dy ), self::n( $p[0] + $dx ), self::n( $p[1] + $dy ) );
			}
		}
		return '<path class="b83w-flake" d="' . $d . '"/>';
	}

	/** @param array<int,array{0:float,1:float,2:float,3:float}> $lines Linien x1 y1 x2 y2. */
	private static function lines( array $lines, string $class ): string {
		$d = '';
		foreach ( $lines as $l ) {
			$d .= sprintf( 'M%s %sH%s', self::n( $l[0] ), self::n( $l[1] ), self::n( $l[2] ) );
		}
		return '<path class="' . esc_attr( $class ) . '" d="' . $d . '"/>';
	}

	private static function n( float $v ): string {
		return rtrim( rtrim( number_format( $v, 2, '.', '' ), '0' ), '.' );
	}
}
