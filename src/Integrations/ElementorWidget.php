<?php
/**
 * Elementor-Widget „b83 Wetter“. Leere Felder übernehmen die Einstellungen
 * aus b83 → Wetter → Design.
 *
 * Diese Datei wird nur geladen, wenn Elementor aktiv ist.
 *
 * @package B83\Wetter
 */

declare(strict_types=1);

namespace B83\Wetter\Integrations;

use B83\Wetter\Frontend\Renderer;
use B83\Wetter\Frontend\Shortcode;
use B83\Wetter\Icons;
use B83\Wetter\Sanitizer;
use Elementor\Controls_Manager;
use Elementor\Widget_Base;

defined( 'ABSPATH' ) || exit;

final class ElementorWidget extends Widget_Base {

	private static ?Renderer $renderer = null;

	public static function set_renderer( Renderer $renderer ): void {
		self::$renderer = $renderer;
	}

	public function get_name(): string {
		return 'b83_wetter';
	}

	public function get_title(): string {
		return __( 'b83 Wetter', 'b83-wetter' );
	}

	public function get_icon(): string {
		return 'eicon-cloud-check';
	}

	/** @return string[] */
	public function get_categories(): array {
		return array( 'general' );
	}

	/** @return string[] */
	public function get_keywords(): array {
		return array( 'wetter', 'weather', 'vorhersage', 'b83' );
	}

	/** @return string[] */
	public function get_style_depends(): array {
		return array( 'b83-wetter' );
	}

	/** @return string[] */
	public function get_script_depends(): array {
		return array( 'b83-wetter' );
	}

	protected function register_controls(): void {
		$this->start_controls_section(
			'section_content',
			array( 'label' => __( 'Wetter', 'b83-wetter' ) )
		);

		$this->add_control(
			'ansicht',
			array(
				'label'   => __( 'Darstellung', 'b83-wetter' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'kompakt',
				'options' => array(
					'klein'        => __( 'Klein (einzeilig)', 'b83-wetter' ),
					'kompakt'      => __( 'Kompakt', 'b83-wetter' ),
					'ausfuehrlich' => __( 'Ausführlich', 'b83-wetter' ),
				),
			)
		);

		$this->add_control(
			'inhalt',
			array(
				'label'     => __( 'Inhalt', 'b83-wetter' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => '',
				'options'   => array(
					''          => __( 'Wie im Backend', 'b83-wetter' ),
					'temp'      => __( 'Icon + Temperatur', 'b83-wetter' ),
					'temp_cond' => __( 'Icon + Temperatur + Wetterlage', 'b83-wetter' ),
					'minmax'    => __( 'Icon + Temperatur + Höchst-/Tiefstwert', 'b83-wetter' ),
					'place'     => __( 'Ort + Icon + Temperatur', 'b83-wetter' ),
				),
				'condition' => array( 'ansicht' => 'klein' ),
			)
		);

		$this->add_control(
			'tage',
			array(
				'label'       => __( 'Tage', 'b83-wetter' ),
				'type'        => Controls_Manager::NUMBER,
				'min'         => 0,
				'max'         => 7,
				'description' => __( 'Leer = wie im Backend.', 'b83-wetter' ),
				'condition'   => array( 'ansicht!' => 'klein' ),
			)
		);

		$this->add_control(
			'titel',
			array(
				'label'       => __( 'Ortsname', 'b83-wetter' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => __( 'Wie im Backend', 'b83-wetter' ),
			)
		);

		$this->add_control(
			'link',
			array(
				'label'       => __( 'Link', 'b83-wetter' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => __( 'Leer = Wetterseite aus dem Backend, „none“ = kein Link', 'b83-wetter' ),
				'condition'   => array( 'ansicht!' => 'ausfuehrlich' ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_style',
			array(
				'label' => __( 'Gestaltung', 'b83-wetter' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'iconset',
			array(
				'label'   => __( 'Icon-Set', 'b83-wetter' ),
				'type'    => Controls_Manager::SELECT,
				'default' => '',
				'options' => array( '' => __( 'Wie im Backend', 'b83-wetter' ) ) + Icons::sets(),
			)
		);

		foreach ( array(
			'farbe'       => __( 'Textfarbe', 'b83-wetter' ),
			'hintergrund' => __( 'Hintergrund', 'b83-wetter' ),
			'akzent'      => __( 'Akzentfarbe', 'b83-wetter' ),
		) as $key => $label ) {
			$this->add_control(
				$key,
				array(
					'label'  => $label,
					'type'   => Controls_Manager::COLOR,
					'global' => array( 'active' => false ),
				)
			);
		}

		foreach ( array(
			'schrift'     => array( __( 'Schriftgröße (px)', 'b83-wetter' ), 10, 40, array() ),
			'temperatur'  => array( __( 'Schriftgröße Temperatur (px)', 'b83-wetter' ), 14, 120, array( 'ansicht!' => 'klein' ) ),
			'icongroesse' => array( __( 'Icon-Größe (px)', 'b83-wetter' ), 12, 200, array() ),
			'abstand'     => array( __( 'Innenabstand (px)', 'b83-wetter' ), 0, 80, array() ),
		) as $key => $def ) {
			$control = array(
				'label'       => $def[0],
				'type'        => Controls_Manager::NUMBER,
				'min'         => $def[1],
				'max'         => $def[2],
				'description' => __( 'Leer = wie im Backend.', 'b83-wetter' ),
			);
			if ( ! empty( $def[3] ) ) {
				$control['condition'] = $def[3];
			}
			$this->add_control( $key, $control );
		}

		$this->end_controls_section();
	}

	protected function render(): void {
		if ( null === self::$renderer ) {
			return;
		}
		$settings = (array) $this->get_settings_for_display();
		$view     = Sanitizer::view( $settings['ansicht'] ?? 'kompakt' );

		$atts = array();
		foreach ( array( 'inhalt', 'tage', 'iconset', 'farbe', 'hintergrund', 'akzent', 'schrift', 'temperatur', 'icongroesse', 'abstand' ) as $key ) {
			if ( isset( $settings[ $key ] ) && is_scalar( $settings[ $key ] ) && '' !== (string) $settings[ $key ] ) {
				$atts[ $key ] = (string) $settings[ $key ];
			}
		}

		$editor = class_exists( '\Elementor\Plugin' ) && \Elementor\Plugin::$instance->editor->is_edit_mode();

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer gibt geprüftes, escaptes HTML aus.
		echo self::$renderer->render(
			$view,
			Shortcode::overrides( $atts, $view ),
			array(
				'title'   => Sanitizer::text( $settings['titel'] ?? '', 80 ),
				'link'    => Sanitizer::text( $settings['link'] ?? '', 300 ),
				'preview' => $editor,
			)
		);
	}
}
