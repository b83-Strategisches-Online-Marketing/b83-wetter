<?php
/**
 * REST-Endpunkt zum Nachladen: Liefert ein Widget aus dem Seiten-Cache
 * veraltete Werte, holt es sich hier das aktuelle HTML (gleiche Domain,
 * keine Verbindung des Besuchers zu einem Wetterdienst).
 *
 *   GET /wp-json/b83-wetter/v1/widget?view=kompakt&args={…}
 *
 * Öffentlich lesbar (nur öffentliche Wetterdaten), alle Werte werden geprüft.
 *
 * @package B83\Wetter
 */

declare(strict_types=1);

namespace B83\Wetter\Frontend;

use B83\Wetter\Sanitizer;

defined( 'ABSPATH' ) || exit;

final class Rest {

	public const NAMESPACE = 'b83-wetter/v1';

	public function __construct( private Renderer $renderer ) {}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/widget',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'widget' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'view' => array(
						'type'              => 'string',
						'default'           => 'kompakt',
						'sanitize_callback' => array( Sanitizer::class, 'view' ),
					),
					'args' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);
	}

	public function widget( \WP_REST_Request $request ): \WP_REST_Response {
		$view = Sanitizer::view( $request->get_param( 'view' ) );
		$raw  = (string) $request->get_param( 'args' );
		$args = array();
		if ( '' !== $raw && strlen( $raw ) <= 2000 ) {
			$decoded = json_decode( $raw, true );
			$args    = is_array( $decoded ) ? $decoded : array();
		}
		// Nur skalare Werte zulassen.
		$args = array_filter( $args, 'is_scalar' );

		$html = $this->renderer->render(
			$view,
			Shortcode::overrides( $args, $view ),
			array(
				'link'  => Sanitizer::text( $args['link'] ?? '', 300 ),
				'title' => Sanitizer::text( $args['title'] ?? '', 80 ),
				'class' => Sanitizer::text( $args['class'] ?? '', 100 ),
			)
		);

		$response = new \WP_REST_Response( array( 'html' => $html ) );
		// Kurz zwischenspeichern dürfen Browser und Proxy (Daten ändern sich höchstens alle 30 Minuten).
		$response->header( 'Cache-Control', 'public, max-age=300' );
		return $response;
	}
}
