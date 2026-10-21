<?php
/**
 * CSS und das kleine Nachlade-Skript.
 *
 * Steht das Wetter im Inhalt oder in einer Vorlage (z. B. Kopfzeile), wird
 * das CSS schon im <head> geladen – so springt das Layout nicht.
 *
 * @package B83\Wetter
 */

declare(strict_types=1);

namespace B83\Wetter\Frontend;

use B83\Wetter\Integrations\CachePurge;

defined( 'ABSPATH' ) || exit;

final class Assets {

	public function __construct( private CachePurge $pages ) {}

	public function register(): void {
		add_action( 'init', array( self::class, 'register_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_early' ) );
		add_action( 'elementor/preview/enqueue_styles', array( self::class, 'enqueue' ) );
	}

	public static function register_assets(): void {
		if ( wp_style_is( 'b83-wetter', 'registered' ) ) {
			return;
		}
		wp_register_style( 'b83-wetter', B83_WETTER_URL . 'assets/css/frontend.css', array(), B83_WETTER_VERSION );
		wp_register_script( 'b83-wetter', B83_WETTER_URL . 'assets/js/frontend.js', array(), B83_WETTER_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		wp_localize_script(
			'b83-wetter',
			'b83Wetter',
			array(
				'endpoint' => esc_url_raw( rest_url( Rest::NAMESPACE . '/widget' ) ),
			)
		);
	}

	public static function enqueue(): void {
		self::register_assets();
		wp_enqueue_style( 'b83-wetter' );
		wp_enqueue_script( 'b83-wetter' );
	}

	public function maybe_enqueue_early(): void {
		$found = false;
		$post  = is_singular() ? get_post() : null;
		if ( $post ) {
			$found = has_shortcode( (string) $post->post_content, Shortcode::TAG )
				|| in_array( (int) $post->ID, $this->pages->affected_pages(), true );
		}
		if ( ! $found ) {
			// Wetter in einer Vorlage (Kopfzeile, Fußzeile …) erscheint auf allen Seiten.
			foreach ( $this->pages->affected_pages() as $id ) {
				if ( in_array( get_post_type( $id ), array( 'elementor_library', 'wp_template_part', 'wp_template' ), true ) ) {
					$found = true;
					break;
				}
			}
		}

		/**
		 * CSS des Wetters im <head> laden.
		 *
		 * @param bool $found Wetter auf dieser Seite erwartet.
		 */
		if ( apply_filters( 'b83_wetter_enqueue_early', $found ) ) {
			self::enqueue();
		}
	}
}
