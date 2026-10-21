<?php
/**
 * Elementor: eigenes Widget „b83 Wetter“ (nur wenn Elementor aktiv ist).
 *
 * @package B83\Wetter
 */

declare(strict_types=1);

namespace B83\Wetter\Integrations;

use B83\Wetter\Frontend\Renderer;

defined( 'ABSPATH' ) || exit;

final class Elementor {

	public function __construct( private Renderer $renderer ) {}

	public function register(): void {
		add_action( 'elementor/widgets/register', array( $this, 'register_widget' ) );
	}

	/** @param object $widgets_manager Elementor\Widgets_Manager. */
	public function register_widget( $widgets_manager ): void {
		if ( ! class_exists( '\Elementor\Widget_Base' ) || ! is_object( $widgets_manager ) || ! method_exists( $widgets_manager, 'register' ) ) {
			return;
		}
		ElementorWidget::set_renderer( $this->renderer );
		$widgets_manager->register( new ElementorWidget() );
	}
}
