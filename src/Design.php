<?php
/**
 * Design-Felder: eine zentrale Definition für Standardwerte, Prüfung der
 * Eingaben, Backend-Formular und Shortcode-Attribute.
 *
 * Reihenfolge der Auflösung: Standardwerte → Backend (Design) → Shortcode/Widget.
 * Farbfelder dürfen leer sein: leer = vom Theme übernehmen bzw. transparent.
 * Die Felder einer Darstellung (Klein, Kompakt, Ausführlich) überschreiben die
 * allgemeinen Farben, wenn sie gesetzt sind.
 *
 * @package B83\Wetter
 */

declare(strict_types=1);

namespace B83\Wetter;

defined( 'ABSPATH' ) || exit;

final class Design {

	/** Darstellungen: Schlüssel => Präfix der Design-Felder. */
	public const VIEWS = array(
		'klein'       => 'small',
		'kompakt'     => 'compact',
		'ausfuehrlich' => 'detail',
	);

	/**
	 * Feld-Definitionen.
	 *
	 * type: enum | int | color | bool | text
	 * empty: true = Farbe darf leer bleiben (Theme / transparent)
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function fields(): array {
		static $fields = null;
		if ( null !== $fields ) {
			return $fields;
		}

		$fields = array(
			/* Allgemein */
			'icon_set'      => array(
				'group'   => 'general',
				'type'    => 'enum',
				'label'   => __( 'Icon-Set', 'b83-wetter' ),
				'options' => Icons::sets(),
				'default' => 'farbig',
			),
			'accent'        => array(
				'group'   => 'general',
				'type'    => 'color',
				'label'   => __( 'Akzentfarbe (Sonne, Icons im Duoton-Set)', 'b83-wetter' ),
				'default' => '#f2a31b',
			),
			'color'         => array(
				'group'   => 'general',
				'type'    => 'color',
				'empty'   => true,
				'label'   => __( 'Textfarbe (Vordergrund)', 'b83-wetter' ),
				'help'    => __( 'Leer = Schriftfarbe des Themes.', 'b83-wetter' ),
				'default' => '',
			),
			'muted'         => array(
				'group'   => 'general',
				'type'    => 'color',
				'empty'   => true,
				'label'   => __( 'Nebentext (Datum, Details, Quelle)', 'b83-wetter' ),
				'help'    => __( 'Leer = Textfarbe, etwas abgeschwächt.', 'b83-wetter' ),
				'default' => '',
			),
			'bg'            => array(
				'group'   => 'general',
				'type'    => 'color',
				'empty'   => true,
				'label'   => __( 'Hintergrund', 'b83-wetter' ),
				'help'    => __( 'Leer = transparent.', 'b83-wetter' ),
				'default' => '',
			),
			'border_width'  => array(
				'group'   => 'general',
				'type'    => 'int',
				'label'   => __( 'Rahmenstärke (px)', 'b83-wetter' ),
				'min'     => 0,
				'max'     => 10,
				'default' => 0,
			),
			'border_color'  => array(
				'group'   => 'general',
				'type'    => 'color',
				'empty'   => true,
				'label'   => __( 'Rahmenfarbe', 'b83-wetter' ),
				'default' => '',
			),
			'radius'        => array(
				'group'   => 'general',
				'type'    => 'int',
				'label'   => __( 'Eckenrundung (px)', 'b83-wetter' ),
				'min'     => 0,
				'max'     => 40,
				'default' => 10,
			),
			'font_weight'   => array(
				'group'   => 'general',
				'type'    => 'enum',
				'label'   => __( 'Stärke der Temperatur', 'b83-wetter' ),
				'options' => array(
					'400' => __( 'Normal', 'b83-wetter' ),
					'600' => __( 'Halbfett', 'b83-wetter' ),
					'700' => __( 'Fett', 'b83-wetter' ),
					'300' => __( 'Leicht', 'b83-wetter' ),
				),
				'default' => '600',
			),
			'show_location' => array(
				'group'   => 'general',
				'type'    => 'bool',
				'label'   => __( 'Ortsnamen anzeigen (Kompakt, Ausführlich)', 'b83-wetter' ),
				'default' => '1',
			),

			/* Klein – einzeilig, z. B. neben der Navigation */
			'small_content'   => array(
				'group'   => 'small',
				'type'    => 'enum',
				'label'   => __( 'Inhalt', 'b83-wetter' ),
				'options' => array(
					'temp'      => __( 'Icon + Temperatur', 'b83-wetter' ),
					'temp_cond' => __( 'Icon + Temperatur + Wetterlage', 'b83-wetter' ),
					'minmax'    => __( 'Icon + Temperatur + Höchst-/Tiefstwert heute', 'b83-wetter' ),
					'place'     => __( 'Ort + Icon + Temperatur', 'b83-wetter' ),
				),
				'default' => 'temp',
			),
			'small_font_size' => array(
				'group'   => 'small',
				'type'    => 'int',
				'label'   => __( 'Schriftgröße (px)', 'b83-wetter' ),
				'min'     => 10,
				'max'     => 40,
				'default' => 15,
			),
			'small_icon_size' => array(
				'group'   => 'small',
				'type'    => 'int',
				'label'   => __( 'Icon-Größe (px)', 'b83-wetter' ),
				'min'     => 12,
				'max'     => 64,
				'default' => 24,
			),
			'small_color'     => array(
				'group'   => 'small',
				'type'    => 'color',
				'empty'   => true,
				'label'   => __( 'Textfarbe', 'b83-wetter' ),
				'help'    => __( 'Leer = allgemeine Textfarbe.', 'b83-wetter' ),
				'default' => '',
			),
			'small_bg'        => array(
				'group'   => 'small',
				'type'    => 'color',
				'empty'   => true,
				'label'   => __( 'Hintergrund', 'b83-wetter' ),
				'help'    => __( 'Leer = allgemeiner Hintergrund.', 'b83-wetter' ),
				'default' => '',
			),
			'small_padding'   => array(
				'group'   => 'small',
				'type'    => 'int',
				'label'   => __( 'Innenabstand (px)', 'b83-wetter' ),
				'min'     => 0,
				'max'     => 30,
				'default' => 0,
			),

			/* Kompakt – Kopfzeile, Sidebar */
			'compact_days'      => array(
				'group'   => 'compact',
				'type'    => 'int',
				'label'   => __( 'Folgetage anzeigen', 'b83-wetter' ),
				'help'    => __( '0 = nur aktuelles Wetter.', 'b83-wetter' ),
				'min'     => 0,
				'max'     => 5,
				'default' => 3,
			),
			'compact_font_size' => array(
				'group'   => 'compact',
				'type'    => 'int',
				'label'   => __( 'Schriftgröße (px)', 'b83-wetter' ),
				'min'     => 10,
				'max'     => 30,
				'default' => 15,
			),
			'compact_temp_size' => array(
				'group'   => 'compact',
				'type'    => 'int',
				'label'   => __( 'Schriftgröße Temperatur (px)', 'b83-wetter' ),
				'min'     => 14,
				'max'     => 90,
				'default' => 36,
			),
			'compact_icon_size' => array(
				'group'   => 'compact',
				'type'    => 'int',
				'label'   => __( 'Icon-Größe (px)', 'b83-wetter' ),
				'min'     => 20,
				'max'     => 160,
				'default' => 56,
			),
			'compact_color'     => array(
				'group'   => 'compact',
				'type'    => 'color',
				'empty'   => true,
				'label'   => __( 'Textfarbe', 'b83-wetter' ),
				'help'    => __( 'Leer = allgemeine Textfarbe.', 'b83-wetter' ),
				'default' => '',
			),
			'compact_bg'        => array(
				'group'   => 'compact',
				'type'    => 'color',
				'empty'   => true,
				'label'   => __( 'Hintergrund', 'b83-wetter' ),
				'help'    => __( 'Leer = allgemeiner Hintergrund.', 'b83-wetter' ),
				'default' => '',
			),
			'compact_padding'   => array(
				'group'   => 'compact',
				'type'    => 'int',
				'label'   => __( 'Innenabstand (px)', 'b83-wetter' ),
				'min'     => 0,
				'max'     => 60,
				'default' => 16,
			),

			/* Ausführlich – Wetterseite */
			'detail_days'      => array(
				'group'   => 'detail',
				'type'    => 'int',
				'label'   => __( 'Tage anzeigen', 'b83-wetter' ),
				'help'    => __( 'GeoSphere rechnet rund 2,5 Tage voraus; weitere Tage kommen von MET Norway (Fallback muss aktiv sein).', 'b83-wetter' ),
				'min'     => 1,
				'max'     => 7,
				'default' => 7,
			),
			'detail_font_size' => array(
				'group'   => 'detail',
				'type'    => 'int',
				'label'   => __( 'Schriftgröße (px)', 'b83-wetter' ),
				'min'     => 10,
				'max'     => 30,
				'default' => 16,
			),
			'detail_temp_size' => array(
				'group'   => 'detail',
				'type'    => 'int',
				'label'   => __( 'Schriftgröße Temperatur (px)', 'b83-wetter' ),
				'min'     => 16,
				'max'     => 120,
				'default' => 56,
			),
			'detail_icon_size' => array(
				'group'   => 'detail',
				'type'    => 'int',
				'label'   => __( 'Icon-Größe aktuell (px)', 'b83-wetter' ),
				'min'     => 24,
				'max'     => 200,
				'default' => 96,
			),
			'detail_day_icon'  => array(
				'group'   => 'detail',
				'type'    => 'int',
				'label'   => __( 'Icon-Größe Tage (px)', 'b83-wetter' ),
				'min'     => 16,
				'max'     => 120,
				'default' => 48,
			),
			'detail_color'     => array(
				'group'   => 'detail',
				'type'    => 'color',
				'empty'   => true,
				'label'   => __( 'Textfarbe', 'b83-wetter' ),
				'help'    => __( 'Leer = allgemeine Textfarbe.', 'b83-wetter' ),
				'default' => '',
			),
			'detail_bg'        => array(
				'group'   => 'detail',
				'type'    => 'color',
				'empty'   => true,
				'label'   => __( 'Hintergrund', 'b83-wetter' ),
				'help'    => __( 'Leer = allgemeiner Hintergrund.', 'b83-wetter' ),
				'default' => '',
			),
			'detail_card_bg'   => array(
				'group'   => 'detail',
				'type'    => 'color',
				'empty'   => true,
				'label'   => __( 'Hintergrund der Tageskarten', 'b83-wetter' ),
				'help'    => __( 'Leer = leicht getönt.', 'b83-wetter' ),
				'default' => '',
			),
			'detail_padding'   => array(
				'group'   => 'detail',
				'type'    => 'int',
				'label'   => __( 'Innenabstand (px)', 'b83-wetter' ),
				'min'     => 0,
				'max'     => 80,
				'default' => 24,
			),
		);

		return $fields;
	}

	/** @return array<string,string> Gruppen-Schlüssel => Überschrift */
	public static function groups(): array {
		return array(
			'general' => __( 'Allgemein (alle Darstellungen)', 'b83-wetter' ),
			'small'   => __( 'Klein – einzeilig, z. B. neben der Navigation', 'b83-wetter' ),
			'compact' => __( 'Kompakt – Kopfzeile und Sidebar', 'b83-wetter' ),
			'detail'  => __( 'Ausführlich – Wetterseite', 'b83-wetter' ),
		);
	}

	/** @return array<string,mixed> */
	public static function defaults(): array {
		return array_map( static fn( array $f ) => $f['default'], self::fields() );
	}

	/**
	 * Kurze Shortcode-Attribute, die sich auf die gewählte Darstellung beziehen.
	 * Beispiel: [b83_wetter ansicht="klein" farbe="#fff" schrift="14"]
	 *
	 * @return array<string,string> Attribut => Feld-Suffix ('' = allgemeines Feld)
	 */
	public static function aliases(): array {
		return array(
			'farbe'       => 'color',
			'hintergrund' => 'bg',
			'schrift'     => 'font_size',
			'temperatur'  => 'temp_size',
			'icongroesse' => 'icon_size',
			'abstand'     => 'padding',
			'tage'        => 'days',
		);
	}

	/**
	 * Werte zusammenführen: leere Werte ('') in $override übernehmen den Wert aus $base.
	 *
	 * @param array<string,mixed> $base     Vollständige Werte.
	 * @param array<string,mixed> $override Überschreibungen (bereits geprüft).
	 * @return array<string,mixed>
	 */
	public static function merge( array $base, array $override ): array {
		foreach ( self::fields() as $key => $field ) {
			if ( array_key_exists( $key, $override ) && '' !== $override[ $key ] && null !== $override[ $key ] ) {
				$base[ $key ] = $override[ $key ];
			}
		}
		return $base;
	}
}
