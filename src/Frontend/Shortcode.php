<?php
/**
 * Shortcode [b83_wetter].
 *
 *   [b83_wetter]                                  kompakt (Standard)
 *   [b83_wetter ansicht="klein"]                  einzeilig, z. B. neben der Navigation
 *   [b83_wetter ansicht="ausfuehrlich" tage="5"]  Wetterseite
 *
 * Kurze Attribute (gelten für die gewählte Darstellung):
 *   farbe, hintergrund, schrift, temperatur, icongroesse, abstand, tage
 * Weitere: iconset, akzent, titel, link (URL, Seiten-ID oder „none“), class
 * Alle Design-Felder lassen sich zusätzlich mit ihrem Schlüssel setzen
 * (z. B. small_content="minmax"), siehe README.
 *
 * @package B83\Wetter
 */

declare(strict_types=1);

namespace B83\Wetter\Frontend;

use B83\Wetter\Design;
use B83\Wetter\Sanitizer;

defined( 'ABSPATH' ) || exit;

final class Shortcode {

	public const TAG = 'b83_wetter';

	public function __construct( private Renderer $renderer ) {}

	public function register(): void {
		add_shortcode( self::TAG, array( $this, 'render' ) );
	}

	/**
	 * @param array<string,string>|string $atts Attribute.
	 */
	public function render( $atts ): string {
		$atts = is_array( $atts ) ? array_change_key_case( $atts, CASE_LOWER ) : array();
		$view = Sanitizer::view( $atts['ansicht'] ?? $atts['view'] ?? 'kompakt' );

		return $this->renderer->render(
			$view,
			self::overrides( $atts, $view ),
			array(
				'class' => Sanitizer::text( $atts['class'] ?? '', 100 ),
				'link'  => Sanitizer::text( $atts['link'] ?? '', 300 ),
				'title' => Sanitizer::text( $atts['titel'] ?? $atts['title'] ?? $atts['ort'] ?? '', 80 ),
			)
		);
	}

	/**
	 * Attribute in geprüfte Design-Überschreibungen umwandeln.
	 * Wird auch von REST und Elementor verwendet.
	 *
	 * @param array<string,mixed> $atts Attribute.
	 * @return array<string,mixed>
	 */
	public static function overrides( array $atts, string $view ): array {
		$prefix = Design::VIEWS[ $view ];
		$raw    = array_intersect_key( $atts, Design::fields() );

		foreach ( Design::aliases() as $alias => $suffix ) {
			if ( ! isset( $atts[ $alias ] ) ) {
				continue;
			}
			$key = $prefix . '_' . $suffix;
			if ( isset( Design::fields()[ $key ] ) ) {
				$raw[ $key ] = $atts[ $alias ];
			}
		}
		if ( isset( $atts['iconset'] ) ) {
			$raw['icon_set'] = $atts['iconset'];
		}
		if ( isset( $atts['akzent'] ) ) {
			$raw['accent'] = $atts['akzent'];
		}
		if ( isset( $atts['inhalt'] ) ) {
			$raw['small_content'] = $atts['inhalt'];
		}

		return array_filter(
			Sanitizer::design( $raw, true ),
			static fn( $v ): bool => '' !== $v && null !== $v
		);
	}
}
