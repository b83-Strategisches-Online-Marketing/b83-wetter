<?php
/**
 * Zentrale Prüfung aller Eingaben (Whitelist, Längenbegrenzung).
 *
 * @package B83\Wetter
 */

declare(strict_types=1);

namespace B83\Wetter;

defined( 'ABSPATH' ) || exit;

final class Sanitizer {

	/** Erlaubte Aktualisierungsintervalle in Minuten. */
	public const INTERVALS = array( 30, 60, 120, 180 );

	/* ---------------------------------------------------------------------
	 * Einzelwerte
	 * ------------------------------------------------------------------- */

	public static function text( mixed $value, int $max ): string {
		$value = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
		return mb_substr( $value, 0, $max );
	}

	/**
	 * Farbe: #abc, #aabbcc, #aabbccdd, rgb()/rgba(), „transparent“, „inherit“ oder ''.
	 */
	public static function color( mixed $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$value = strtolower( trim( (string) $value ) );
		if ( in_array( $value, array( 'transparent', 'inherit', 'currentcolor' ), true ) ) {
			return 'currentcolor' === $value ? 'currentColor' : $value;
		}
		if ( 1 === preg_match( '/^#?([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/', $value, $m ) ) {
			return '#' . $m[1];
		}
		if ( 1 === preg_match( '/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*(?:,\s*(0|1|0?\.\d{1,3}))?\s*\)$/', $value, $m ) ) {
			if ( max( (int) $m[1], (int) $m[2], (int) $m[3] ) > 255 ) {
				return '';
			}
			return isset( $m[4] ) && '' !== $m[4]
				? sprintf( 'rgba(%d,%d,%d,%s)', $m[1], $m[2], $m[3], $m[4] )
				: sprintf( 'rgb(%d,%d,%d)', $m[1], $m[2], $m[3] );
		}
		return '';
	}

	/** '1', '0' oder null bei ungültigem Wert. */
	public static function bool( mixed $value ): ?string {
		if ( is_bool( $value ) ) {
			return $value ? '1' : '0';
		}
		$value = is_scalar( $value ) ? strtolower( trim( (string) $value ) ) : '';
		return match ( $value ) {
			'1', 'yes', 'ja', 'true', 'on' => '1',
			'0', 'no', 'nein', 'false', 'off' => '0',
			default => null,
		};
	}

	/** Koordinate als String mit max. 5 Nachkommastellen oder ''. */
	public static function coordinate( mixed $value, float $limit ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$value = str_replace( ',', '.', trim( (string) $value ) );
		if ( '' === $value || ! is_numeric( $value ) ) {
			return '';
		}
		$float = (float) $value;
		if ( $float < -$limit || $float > $limit ) {
			return '';
		}
		return rtrim( rtrim( number_format( $float, 5, '.', '' ), '0' ), '.' );
	}

	/** Seehöhe in Metern (-500 bis 9000) oder ''. */
	public static function altitude( mixed $value ): string {
		if ( ! is_scalar( $value ) || '' === trim( (string) $value ) || ! is_numeric( str_replace( ',', '.', (string) $value ) ) ) {
			return '';
		}
		$alt = (int) round( (float) str_replace( ',', '.', (string) $value ) );
		return ( $alt >= -500 && $alt <= 9000 ) ? (string) $alt : '';
	}

	/* ---------------------------------------------------------------------
	 * Design
	 * ------------------------------------------------------------------- */

	/**
	 * Prüft Design-Werte.
	 *
	 * @param array<string,mixed> $input   Rohwerte.
	 * @param bool                $inherit true: leere/ungültige Werte werden '' (= Wert aus dem Backend übernehmen),
	 *                                     false: vollständiger Satz mit Standardwerten.
	 * @return array<string,mixed>
	 */
	public static function design( array $input, bool $inherit ): array {
		$out = array();
		foreach ( Design::fields() as $key => $field ) {
			$raw      = $input[ $key ] ?? null;
			$fallback = $inherit ? '' : $field['default'];

			if ( null === $raw || ( is_string( $raw ) && '' === trim( $raw ) ) ) {
				// Leere Farbe im Backend-Design = bewusst „Theme / transparent“.
				$out[ $key ] = ( ! $inherit && ! empty( $field['empty'] ) && null !== $raw ) ? '' : $fallback;
				continue;
			}

			$value = match ( $field['type'] ) {
				'enum'  => is_scalar( $raw ) && array_key_exists( (string) $raw, $field['options'] ) ? (string) $raw : null,
				'int'   => is_numeric( $raw ) ? max( (int) $field['min'], min( (int) $field['max'], (int) round( (float) $raw ) ) ) : null,
				'color' => self::color( $raw ) ?: null,
				'bool'  => self::bool( $raw ),
				'text'  => self::text( $raw, (int) ( $field['max'] ?? 200 ) ),
				default => null,
			};

			$out[ $key ] = null === $value ? $fallback : $value;
		}
		return $out;
	}

	/* ---------------------------------------------------------------------
	 * Einstellungen
	 * ------------------------------------------------------------------- */

	/**
	 * @param array<string,mixed> $input Rohwerte der Einstellungen.
	 * @return array<string,mixed>
	 */
	public static function settings( array $input ): array {
		$source   = is_scalar( $input['source'] ?? null ) ? (string) $input['source'] : 'geosphere';
		$interval = isset( $input['interval'] ) && is_numeric( $input['interval'] ) ? (int) $input['interval'] : 60;

		return array(
			'location_name'       => self::text( $input['location_name'] ?? '', 80 ),
			'lat'                 => self::coordinate( $input['lat'] ?? '', 90 ),
			'lng'                 => self::coordinate( $input['lng'] ?? '', 180 ),
			'altitude'            => self::altitude( $input['altitude'] ?? '' ),
			'source'              => in_array( $source, array( 'geosphere', 'metno' ), true ) ? $source : 'geosphere',
			'fallback'            => self::bool( $input['fallback'] ?? '1' ) ?? '1',
			'interval'            => in_array( $interval, self::INTERVALS, true ) ? $interval : 60,
			'link_page'           => isset( $input['link_page'] ) && is_numeric( $input['link_page'] ) ? max( 0, (int) $input['link_page'] ) : 0,
			'design'              => self::design( (array) ( $input['design'] ?? array() ), false ),
			'purge_cache'         => self::bool( $input['purge_cache'] ?? '1' ) ?? '1',
			'delete_on_uninstall' => self::bool( $input['delete_on_uninstall'] ?? '0' ) ?? '0',
		);
	}

	/**
	 * Darstellung prüfen (auch englische und kurze Schreibweisen).
	 */
	public static function view( mixed $value ): string {
		$value = is_scalar( $value ) ? strtolower( trim( (string) $value ) ) : '';
		return match ( $value ) {
			'klein', 'small', 'inline', 'mini', 'einzeilig' => 'klein',
			'ausfuehrlich', 'ausführlich', 'detail', 'details', 'gross', 'groß', 'large', 'full' => 'ausfuehrlich',
			default => 'kompakt',
		};
	}
}
