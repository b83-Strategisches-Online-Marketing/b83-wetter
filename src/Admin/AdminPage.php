<?php
/**
 * Backend: Standort und Datenquelle, Design mit Vorschau, Status, Hilfe.
 * Hängt sich als Unterpunkt in das gemeinsame Menü „b83“ ein.
 *
 * @package B83\Wetter
 */

declare(strict_types=1);

namespace B83\Wetter\Admin;

use B83\Wetter\Conditions;
use B83\Wetter\Data\Demo;
use B83\Wetter\Data\Refresher;
use B83\Wetter\Design;
use B83\Wetter\Frontend\Assets;
use B83\Wetter\Frontend\Renderer;
use B83\Wetter\Icons;
use B83\Wetter\Integrations\CachePurge;
use B83\Wetter\Repository;
use B83\Wetter\Sanitizer;
use B83\Wetter\Scheduler;

defined( 'ABSPATH' ) || exit;

final class AdminPage {

	public const SLUG = 'b83-wetter';

	public function __construct(
		private Repository $repository,
		private Renderer $renderer,
		private Refresher $refresher
	) {}

	/**
	 * Hinweise per festem Schlüssel in der URL (nie Freitext aus der URL ausgeben).
	 *
	 * @return array<string,array{0:string,1:string}>
	 */
	private function notices(): array {
		return array(
			'saved'         => array( 'success', __( 'Einstellungen gespeichert, Wetterdaten aktualisiert.', 'b83-wetter' ) ),
			'saved_nodata'  => array( 'warning', __( 'Einstellungen gespeichert. Die Wetterdaten konnten nicht abgerufen werden – Details unter „Status“.', 'b83-wetter' ) ),
			'saved_noloc'   => array( 'warning', __( 'Einstellungen gespeichert. Bitte Breite und Länge des Standorts eintragen.', 'b83-wetter' ) ),
			'design'        => array( 'success', __( 'Design gespeichert.', 'b83-wetter' ) ),
			'refreshed'     => array( 'success', __( 'Wetterdaten aktualisiert.', 'b83-wetter' ) ),
			'refresh_error' => array( 'error', __( 'Die Wetterdaten konnten nicht abgerufen werden – Details siehe unten.', 'b83-wetter' ) ),
		);
	}

	public static function capability(): string {
		/**
		 * Berechtigung für die Wetter-Einstellungen.
		 *
		 * @param string $capability Standard: manage_options.
		 */
		return (string) apply_filters( 'b83_wetter_capability', 'manage_options' );
	}

	public function register(): void {
		( new B83Menu( B83_WETTER_URL, B83_WETTER_DIR ) )->register();

		add_action( 'admin_menu', array( $this, 'add_page' ), 20 );
		add_filter( 'b83_admin_plugin_links', array( $this, 'overview_link' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( B83_WETTER_FILE ), array( $this, 'action_links' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );

		add_action( 'admin_post_b83_wetter_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_b83_wetter_save_design', array( $this, 'handle_save_design' ) );
		add_action( 'admin_post_b83_wetter_refresh', array( $this, 'handle_refresh' ) );

		add_action( 'wp_ajax_b83_wetter_preview', array( $this, 'ajax_preview' ) );
		add_action( 'wp_ajax_b83_wetter_geocode', array( $this, 'ajax_geocode' ) );
	}

	public function add_page(): void {
		add_submenu_page(
			B83Menu::SLUG,
			__( 'Wetter', 'b83-wetter' ),
			__( 'Wetter', 'b83-wetter' ),
			self::capability(),
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * @param array<string,string> $links Plugin-Datei => URL.
	 * @return array<string,string>
	 */
	public function overview_link( array $links ): array {
		$links[ plugin_basename( B83_WETTER_FILE ) ] = $this->url();
		return $links;
	}

	/**
	 * @param string[] $links Links in der Plugin-Liste.
	 * @return string[]
	 */
	public function action_links( array $links ): array {
		array_unshift( $links, '<a href="' . esc_url( $this->url() ) . '">' . esc_html__( 'Einstellungen', 'b83-wetter' ) . '</a>' );
		return $links;
	}

	/** @param array<string,string|int> $args Zusätzliche Parameter. */
	private function url( array $args = array() ): string {
		return add_query_arg( array_merge( array( 'page' => self::SLUG ), $args ), admin_url( 'admin.php' ) );
	}

	/* ---------------------------------------------------------------------
	 * Dateien
	 * ------------------------------------------------------------------- */

	public function enqueue( string $hook ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nur Seitenerkennung.
		if ( ! str_ends_with( $hook, '_page_' . self::SLUG ) && ( $_GET['page'] ?? '' ) !== self::SLUG ) {
			return;
		}
		Assets::register_assets();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'b83-wetter-admin', B83_WETTER_URL . 'assets/css/admin.css', array( 'b83-wetter', 'wp-color-picker' ), B83_WETTER_VERSION );
		wp_enqueue_script( 'b83-wetter-admin', B83_WETTER_URL . 'assets/js/admin.js', array( 'jquery', 'wp-color-picker' ), B83_WETTER_VERSION, true );
		wp_localize_script(
			'b83-wetter-admin',
			'b83WetterAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'b83_wetter_ajax' ),
				'i18n'    => array(
					'copied'    => __( 'Kopiert!', 'b83-wetter' ),
					'searching' => __( 'Suche …', 'b83-wetter' ),
					'notFound'  => __( 'Ort nicht gefunden. Bitte genauer angeben oder Koordinaten eintragen.', 'b83-wetter' ),
					'found'     => __( 'Gefunden – Koordinaten übernommen. Bitte speichern.', 'b83-wetter' ),
					'empty'     => __( 'Bitte zuerst einen Ort eingeben.', 'b83-wetter' ),
					'loading'   => __( 'Vorschau wird geladen …', 'b83-wetter' ),
				),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Seiten
	 * ------------------------------------------------------------------- */

	public function render(): void {
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'b83-wetter' ), '', array( 'response' => 403 ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- nur Anzeige.
		$tab    = sanitize_key( wp_unslash( $_GET['tab'] ?? '' ) );
		$notice = sanitize_key( wp_unslash( $_GET['notice'] ?? '' ) );
		// phpcs:enable

		$tabs = array(
			'settings' => __( 'Standort und Daten', 'b83-wetter' ),
			'design'   => __( 'Design', 'b83-wetter' ),
			'status'   => __( 'Status', 'b83-wetter' ),
			'help'     => __( 'Hilfe', 'b83-wetter' ),
		);
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = null === $this->repository->location() ? 'settings' : 'design';
		}

		echo '<div class="wrap b83-wetter-admin">';
		echo '<h1>' . esc_html__( 'b83 Wetter', 'b83-wetter' ) . '</h1>';

		$notices = $this->notices();
		if ( isset( $notices[ $notice ] ) ) {
			printf(
				'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
				esc_attr( $notices[ $notice ][0] ),
				esc_html( $notices[ $notice ][1] )
			);
		}

		echo '<nav class="nav-tab-wrapper">';
		foreach ( $tabs as $key => $label ) {
			printf(
				'<a href="%1$s" class="nav-tab%2$s">%3$s</a>',
				esc_url( $this->url( array( 'tab' => $key ) ) ),
				$key === $tab ? ' nav-tab-active' : '',
				esc_html( $label )
			);
		}
		echo '</nav>';

		match ( $tab ) {
			'design' => $this->render_design(),
			'status' => $this->render_status(),
			'help'   => $this->render_help(),
			default  => $this->render_settings(),
		};

		echo '<p class="b83-wetter-admin__foot">' . esc_html(
			sprintf(
				/* translators: %s: Version */
				__( 'b83 Wetter %s · b83 – Strategisches Online Marketing', 'b83-wetter' ),
				B83_WETTER_VERSION
			)
		) . '</p>';
		echo '</div>';
	}

	private function render_settings(): void {
		$s = $this->repository->get_settings();

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="b83-wetter-form">';
		wp_nonce_field( 'b83_wetter_save_settings' );
		echo '<input type="hidden" name="action" value="b83_wetter_save_settings">';

		/* Standort */
		echo '<div class="b83-wetter-card"><h2>' . esc_html__( 'Standort', 'b83-wetter' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Ort suchen oder Koordinaten direkt eintragen (z. B. aus Google Maps: Rechtsklick auf den Ort).', 'b83-wetter' ) . '</p>';

		echo '<div class="b83-wetter-geocode">';
		echo '<input type="search" id="b83-wetter-geo-q" class="regular-text" placeholder="' . esc_attr__( 'z. B. Abtenau, Salzburg', 'b83-wetter' ) . '">';
		echo ' <button type="button" class="button" id="b83-wetter-geo-btn">' . esc_html__( 'Ort suchen', 'b83-wetter' ) . '</button>';
		echo ' <span class="b83-wetter-geocode__msg" aria-live="polite"></span>';
		echo '</div>';

		echo '<div class="b83-wetter-grid">';
		$this->text_field( 'settings[location_name]', 'b83-wetter-name', __( 'Ortsname (Anzeige)', 'b83-wetter' ), (string) $s['location_name'], __( 'Abtenau', 'b83-wetter' ), 80 );
		$this->text_field( 'settings[altitude]', 'b83-wetter-alt', __( 'Seehöhe in m (optional)', 'b83-wetter' ), (string) $s['altitude'], '714', 6 );
		$this->text_field( 'settings[lat]', 'b83-wetter-lat', __( 'Breite (Latitude)', 'b83-wetter' ), (string) $s['lat'], '47.5633', 12 );
		$this->text_field( 'settings[lng]', 'b83-wetter-lng', __( 'Länge (Longitude)', 'b83-wetter' ), (string) $s['lng'], '13.3473', 12 );
		echo '</div>';
		echo '<p class="description">' . esc_html__( 'Die Seehöhe hilft MET Norway, die Temperatur für Orte im Gebirge richtig zu berechnen.', 'b83-wetter' ) . '</p>';
		echo '</div>';

		/* Datenquelle */
		echo '<div class="b83-wetter-card"><h2>' . esc_html__( 'Datenquelle', 'b83-wetter' ) . '</h2>';
		echo '<table class="form-table" role="presentation"><tbody>';

		echo '<tr><th scope="row"><label for="b83-wetter-source">' . esc_html__( 'Hauptquelle', 'b83-wetter' ) . '</label></th><td>';
		echo '<select id="b83-wetter-source" name="settings[source]">';
		foreach ( array(
			'geosphere' => __( 'GeoSphere Austria – Österreich und Alpenraum, 2,5 km, rund 2,5 Tage', 'b83-wetter' ),
			'metno'     => __( 'MET Norway – weltweit, rund 9 Tage', 'b83-wetter' ),
		) as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $s['source'], $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Fallback', 'b83-wetter' ) . '</th><td>';
		$this->checkbox( 'settings[fallback]', (string) $s['fallback'], __( 'Bei Ausfall die andere Quelle verwenden und fehlende Tage von dort ergänzen (empfohlen)', 'b83-wetter' ) );
		echo '<p class="description">' . esc_html__( 'GeoSphere rechnet nur rund 2,5 Tage voraus. Für 5–7 Tage ergänzt das Plugin die weiteren Tage automatisch von MET Norway.', 'b83-wetter' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row"><label for="b83-wetter-interval">' . esc_html__( 'Aktualisieren', 'b83-wetter' ) . '</label></th><td>';
		echo '<select id="b83-wetter-interval" name="settings[interval]">';
		foreach ( Sanitizer::INTERVALS as $minutes ) {
			/* translators: %d: Minuten */
			$label = $minutes < 60 ? sprintf( __( 'alle %d Minuten', 'b83-wetter' ), $minutes ) : sprintf( _n( 'jede Stunde', 'alle %d Stunden', (int) ( $minutes / 60 ), 'b83-wetter' ), (int) ( $minutes / 60 ) );
			echo '<option value="' . (int) $minutes . '"' . selected( (int) $s['interval'], $minutes, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Die Daten werden auf dem Server abgerufen und zwischengespeichert. Besucher verbinden sich nie mit einem Wetterdienst.', 'b83-wetter' ) . '</p>';
		echo '</td></tr>';

		echo '</tbody></table></div>';

		/* Darstellung */
		echo '<div class="b83-wetter-card"><h2>' . esc_html__( 'Wetterseite und Cache', 'b83-wetter' ) . '</h2>';
		echo '<table class="form-table" role="presentation"><tbody>';

		echo '<tr><th scope="row"><label for="b83-wetter-link">' . esc_html__( 'Wetterseite', 'b83-wetter' ) . '</label></th><td>';
		wp_dropdown_pages(
			array(
				'name'              => 'settings[link_page]',
				'id'                => 'b83-wetter-link',
				'selected'          => (int) $s['link_page'],
				'show_option_none'  => esc_html__( '— keine —', 'b83-wetter' ),
				'option_none_value' => '0',
			)
		);
		echo '<p class="description">' . esc_html__( 'Klein und Kompakt verlinken auf diese Seite. Dort am besten die ausführliche Darstellung einbinden – sie enthält auch die vollständige Quellenangabe.', 'b83-wetter' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Seiten-Cache', 'b83-wetter' ) . '</th><td>';
		$this->checkbox( 'settings[purge_cache]', (string) $s['purge_cache'], __( 'Nach dem Speichern den Cache der Seiten mit Wetter leeren', 'b83-wetter' ) );
		$cache = CachePurge::detected_plugin();
		echo '<p class="description">' . esc_html(
			'' !== $cache
				/* translators: %s: Name des Cache-Plugins */
				? sprintf( __( 'Erkannt: %s. Stündliche Wetterdaten leeren den Cache nicht – veraltete Widgets laden die aktuellen Werte selbst nach.', 'b83-wetter' ), $cache )
				: __( 'Kein Cache-Plugin erkannt. Veraltete Widgets aus einem Seiten-Cache laden die aktuellen Werte selbst nach.', 'b83-wetter' )
		) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Deinstallation', 'b83-wetter' ) . '</th><td>';
		$this->checkbox( 'settings[delete_on_uninstall]', (string) $s['delete_on_uninstall'], __( 'Beim Löschen des Plugins alle Einstellungen und Daten entfernen', 'b83-wetter' ) );
		echo '</td></tr>';

		echo '</tbody></table></div>';

		submit_button( __( 'Speichern und Wetter abrufen', 'b83-wetter' ) );
		echo '</form>';
	}

	private function render_design(): void {
		$settings = $this->repository->get_settings();

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" id="b83-wetter-design-form">';
		wp_nonce_field( 'b83_wetter_save_design' );
		echo '<input type="hidden" name="action" value="b83_wetter_save_design">';

		echo '<div class="b83-wetter-editor">';
		echo '<div class="b83-wetter-editor__main"><div class="b83-wetter-card">';
		echo '<p class="description">' . esc_html__( 'Diese Werte gelten überall, wo das Wetter eingebunden ist. Einzelne Widgets lassen sich per Shortcode-Attribut oder im Elementor-Widget anders gestalten.', 'b83-wetter' ) . '</p>';
		$this->render_design_fields( $settings['design'] );
		echo '</div>';
		submit_button( __( 'Design speichern', 'b83-wetter' ) );
		echo '</div>';

		echo '<div class="b83-wetter-editor__side"><div class="b83-wetter-sticky">';
		echo '<div class="b83-wetter-card b83-wetter-preview">';
		echo '<div class="b83-wetter-preview__bar"><h2>' . esc_html__( 'Vorschau', 'b83-wetter' ) . '</h2>';
		echo '<span class="b83-wetter-preview__bg" role="group" aria-label="' . esc_attr__( 'Hintergrund der Vorschau', 'b83-wetter' ) . '">';
		foreach ( array(
			'light' => __( 'Hell', 'b83-wetter' ),
			'dark'  => __( 'Dunkel', 'b83-wetter' ),
		) as $key => $label ) {
			echo '<button type="button" class="button button-small' . ( 'light' === $key ? ' is-active' : '' ) . '" data-bg="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</button>';
		}
		echo '</span></div>';
		echo '<div class="b83-wetter-preview__stage" data-bg="light">';
		echo $this->preview_html( $settings['design'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escapt.
		echo '</div></div>';
		echo '</div></div>';

		echo '</div></form>';
	}

	/**
	 * Vorschau der drei Darstellungen.
	 *
	 * @param array<string,mixed> $design Design.
	 */
	private function preview_html( array $design ): string {
		$demo = null === $this->repository->location() || $this->repository->forecast()->is_empty();
		$opts = array(
			'preview' => true,
			'design'  => $design,
			'link'    => 'none',
		);
		$html = '';
		if ( $demo ) {
			$opts['forecast'] = Demo::forecast();
			$opts['title']    = __( 'Musterort', 'b83-wetter' );
			$html            .= '<p class="b83-wetter-preview__demo">' . esc_html__( 'Beispieldaten – echte Werte erscheinen, sobald ein Standort eingetragen und abgerufen ist.', 'b83-wetter' ) . '</p>';
		}
		foreach ( array(
			'klein'        => __( 'Klein', 'b83-wetter' ),
			'kompakt'      => __( 'Kompakt', 'b83-wetter' ),
			'ausfuehrlich' => __( 'Ausführlich', 'b83-wetter' ),
		) as $view => $label ) {
			$html .= '<div class="b83-wetter-preview__item b83-wetter-preview__item--' . esc_attr( $view ) . '">';
			$html .= '<div class="b83-wetter-preview__label">' . esc_html( $label ) . '</div>';
			if ( 'klein' === $view ) {
				$html .= '<div class="b83-wetter-preview__nav"><span>' . esc_html__( 'Start', 'b83-wetter' ) . '</span><span>' . esc_html__( 'Zimmer', 'b83-wetter' ) . '</span><span>' . esc_html__( 'Kontakt', 'b83-wetter' ) . '</span>';
				$html .= $this->renderer->render( $view, array(), $opts );
				$html .= '</div>';
			} else {
				$html .= $this->renderer->render( $view, array(), $opts );
			}
			$html .= '</div>';
		}
		return $html;
	}

	/**
	 * @param array<string,mixed> $values Design-Werte.
	 */
	private function render_design_fields( array $values ): void {
		$fields = Design::fields();
		foreach ( Design::groups() as $group => $heading ) {
			echo '<details class="b83-wetter-group" ' . ( 'general' === $group ? 'open' : '' ) . '><summary>' . esc_html( $heading ) . '</summary><div class="b83-wetter-grid">';
			foreach ( $fields as $key => $field ) {
				if ( $field['group'] === $group ) {
					$this->render_design_field( $key, $field, $values[ $key ] ?? $field['default'] );
				}
			}
			echo '</div></details>';
		}
	}

	/**
	 * @param array<string,mixed> $field Feld-Definition.
	 */
	private function render_design_field( string $key, array $field, mixed $value ): void {
		$name  = 'design[' . $key . ']';
		$id    = 'b83-wetter-design-' . $key;
		$value = (string) $value;
		$wide  = 'enum' === $field['type'] && 'icon_set' !== $key && str_ends_with( $key, '_content' ) ? ' b83-wetter-col-2' : '';

		echo '<div class="b83-wetter-field' . esc_attr( $wide ) . '">';

		if ( 'bool' === $field['type'] ) {
			echo '<label class="b83-wetter-check"><input type="hidden" name="' . esc_attr( $name ) . '" value="0">';
			echo '<input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"' . checked( '1', $value, false ) . '> ' . esc_html( $field['label'] ) . '</label>';
		} else {
			echo '<label class="b83-wetter-label" for="' . esc_attr( $id ) . '">' . esc_html( $field['label'] ) . '</label>';
			switch ( $field['type'] ) {
				case 'enum':
					echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
					foreach ( $field['options'] as $opt => $label ) {
						echo '<option value="' . esc_attr( (string) $opt ) . '"' . selected( $value, (string) $opt, false ) . '>' . esc_html( $label ) . '</option>';
					}
					echo '</select>';
					break;

				case 'int':
					printf(
						'<input type="number" class="small-text" id="%1$s" name="%2$s" value="%3$s" min="%4$d" max="%5$d" step="1" required>',
						esc_attr( $id ),
						esc_attr( $name ),
						esc_attr( $value ),
						(int) $field['min'],
						(int) $field['max']
					);
					break;

				case 'color':
					printf(
						'<input type="text" class="b83-wetter-color" id="%1$s" name="%2$s" value="%3$s" data-default-color="%4$s">',
						esc_attr( $id ),
						esc_attr( $name ),
						esc_attr( $value ),
						esc_attr( (string) $field['default'] )
					);
					break;

				case 'text':
					printf(
						'<input type="text" class="widefat" id="%1$s" name="%2$s" value="%3$s" maxlength="%4$d">',
						esc_attr( $id ),
						esc_attr( $name ),
						esc_attr( $value ),
						(int) ( $field['max'] ?? 200 )
					);
					break;
			}
		}
		if ( ! empty( $field['help'] ) ) {
			echo '<p class="description">' . esc_html( $field['help'] ) . '</p>';
		}
		echo '</div>';
	}

	private function render_status(): void {
		$status   = $this->repository->get_status();
		$data     = $this->repository->get_data();
		$forecast = $this->repository->forecast();
		$location = $this->repository->location();
		$fmt      = static fn( int $ts ): string => $ts > 0 ? wp_date( 'j.n.Y, H:i', $ts ) . ' ' . __( 'Uhr', 'b83-wetter' ) : '–';

		$modes = array(
			'primary'  => __( 'Hauptquelle', 'b83-wetter' ),
			'fallback' => __( 'Fallback (Hauptquelle nicht erreichbar)', 'b83-wetter' ),
			'combined' => __( 'Hauptquelle, weitere Tage ergänzt', 'b83-wetter' ),
		);
		$labels = array();
		foreach ( (array) ( $data['sources'] ?? array() ) as $key ) {
			$source   = $this->repository->source( (string) $key );
			$labels[] = null !== $source ? $source->label() : (string) $key;
		}

		$rows = array(
			__( 'Standort', 'b83-wetter' )                     => null !== $location ? trim( $location['name'] . ' (' . $location['lat'] . ', ' . $location['lng'] . ( '' !== $location['altitude'] ? ', ' . $location['altitude'] . ' m' : '' ) . ')' ) : __( 'nicht eingetragen', 'b83-wetter' ),
			__( 'Letzte erfolgreiche Abfrage', 'b83-wetter' )  => $fmt( (int) ( $status['last_success'] ?? 0 ) ),
			__( 'Letzter Versuch', 'b83-wetter' )              => $fmt( (int) ( $status['last_attempt'] ?? 0 ) ),
			__( 'Verwendete Daten', 'b83-wetter' )             => empty( $labels ) ? '–' : implode( ' + ', $labels ) . ( isset( $modes[ $data['mode'] ?? '' ] ) ? ' · ' . $modes[ $data['mode'] ] : '' ),
			__( 'Vorhersage reicht bis', 'b83-wetter' )        => $forecast->is_empty() ? '–' : $fmt( $forecast->coverage_end() ),
			__( 'Nächste geplante Abfrage', 'b83-wetter' )     => $fmt( (int) wp_next_scheduled( Scheduler::HOOK ) ),
		);

		echo '<div class="b83-wetter-card"><h2>' . esc_html__( 'Wetterdaten', 'b83-wetter' ) . '</h2>';
		echo '<table class="widefat striped b83-wetter-status"><tbody>';
		foreach ( $rows as $label => $value ) {
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . esc_html( $value ) . '</td></tr>';
		}
		echo '</tbody></table>';

		$errors = (array) ( $status['errors'] ?? array() );
		if ( ! empty( $errors ) ) {
			echo '<div class="notice notice-warning inline"><p><strong>' . esc_html__( 'Meldungen beim letzten Versuch:', 'b83-wetter' ) . '</strong></p><ul>';
			foreach ( $errors as $key => $message ) {
				$source = $this->repository->source( (string) $key );
				echo '<li>' . esc_html( ( null !== $source ? $source->label() . ': ' : '' ) . (string) $message ) . '</li>';
			}
			echo '</ul></div>';
		}

		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			echo '<p class="description">' . esc_html__( 'Hinweis: WP-Cron ist deaktiviert (DISABLE_WP_CRON). Bitte sicherstellen, dass ein Server-Cron wp-cron.php regelmäßig aufruft.', 'b83-wetter' ) . '</p>';
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'b83_wetter_refresh' );
		echo '<input type="hidden" name="action" value="b83_wetter_refresh">';
		submit_button( __( 'Jetzt aktualisieren', 'b83-wetter' ), 'secondary', 'submit', false );
		echo '</form></div>';

		// Kurze Übersicht der nächsten Tage zum Prüfen der Werte.
		if ( ! $forecast->is_empty() ) {
			$days = $forecast->days( 9 );
			echo '<div class="b83-wetter-card"><h2>' . esc_html__( 'Tageswerte zur Kontrolle', 'b83-wetter' ) . '</h2>';
			echo '<table class="widefat striped"><thead><tr>';
			foreach ( array( __( 'Tag', 'b83-wetter' ), __( 'Wetterlage', 'b83-wetter' ), __( 'Max', 'b83-wetter' ), __( 'Min', 'b83-wetter' ), __( 'Niederschlag', 'b83-wetter' ), __( 'Wind max.', 'b83-wetter' ) ) as $th ) {
				echo '<th>' . esc_html( $th ) . '</th>';
			}
			echo '</tr></thead><tbody>';
			foreach ( $days as $day ) {
				$ts = ( new \DateTimeImmutable( $day['date'] . ' 12:00', wp_timezone() ) )->getTimestamp();
				echo '<tr><td>' . esc_html( wp_date( 'D, j.n.', $ts ) ) . '</td>'
					. '<td class="b83-wetter-status__cond b83w-set-farbig">' . Icons::svg( (string) $day['cond'] ) . esc_html( Conditions::label( (string) $day['cond'] ) ) . '</td>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG aus Icons.
					. '<td>' . esc_html( $this->renderer->temp( (float) $day['max'] ) ) . '</td>'
					. '<td>' . esc_html( $this->renderer->temp( (float) $day['min'] ) ) . '</td>'
					. '<td>' . esc_html( number_format_i18n( (float) $day['precip'], 1 ) ) . ' mm</td>'
					. '<td>' . esc_html( number_format_i18n( (float) $day['wind'] ) ) . ' km/h</td></tr>';
			}
			echo '</tbody></table></div>';
		}
	}

	private function render_help(): void {
		echo '<div class="b83-wetter-card b83-wetter-help">';
		echo '<h2>' . esc_html__( 'Einbinden', 'b83-wetter' ) . '</h2>';
		echo '<p>' . esc_html__( 'Per Shortcode (auch im Elementor-Widget „Shortcode“, in Kopf- und Fußzeilen-Vorlagen) oder mit dem Elementor-Widget „b83 Wetter“.', 'b83-wetter' ) . '</p>';

		$examples = array(
			'[b83_wetter ansicht="klein"]'                                  => __( 'Einzeilig, z. B. neben der Navigation', 'b83-wetter' ),
			'[b83_wetter ansicht="klein" farbe="#ffffff" schrift="14"]'     => __( 'Einzeilig, weiße Schrift für eine dunkle Kopfzeile', 'b83-wetter' ),
			'[b83_wetter ansicht="klein" inhalt="minmax"]'                  => __( 'Mit Höchst- und Tiefstwert von heute', 'b83-wetter' ),
			'[b83_wetter]'                                                  => __( 'Kompakt (Standard) – Kopfzeile, Sidebar', 'b83-wetter' ),
			'[b83_wetter ansicht="kompakt" tage="0"]'                       => __( 'Kompakt, nur aktuelles Wetter', 'b83-wetter' ),
			'[b83_wetter ansicht="ausfuehrlich"]'                           => __( 'Ausführlich – Wetterseite', 'b83-wetter' ),
			'[b83_wetter ansicht="ausfuehrlich" tage="5" iconset="linie"]'  => __( 'Ausführlich mit 5 Tagen und Linien-Icons', 'b83-wetter' ),
		);
		echo '<table class="widefat striped"><tbody>';
		foreach ( $examples as $code => $label ) {
			echo '<tr><td class="b83-wetter-help__code"><code>' . esc_html( $code ) . '</code> <button type="button" class="button button-small b83-wetter-copy" data-copy="' . esc_attr( $code ) . '">' . esc_html__( 'Kopieren', 'b83-wetter' ) . '</button></td><td>' . esc_html( $label ) . '</td></tr>';
		}
		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Attribute', 'b83-wetter' ) . '</h2>';
		$attrs = array(
			array( 'ansicht', __( 'Darstellung', 'b83-wetter' ), 'klein, kompakt, ausfuehrlich' ),
			array( 'farbe', __( 'Textfarbe', 'b83-wetter' ), '#ffffff, rgba(…), inherit' ),
			array( 'hintergrund', __( 'Hintergrund', 'b83-wetter' ), '#0b3954, transparent' ),
			array( 'schrift', __( 'Schriftgröße in px', 'b83-wetter' ), '10–40' ),
			array( 'temperatur', __( 'Schriftgröße der Temperatur in px (kompakt, ausführlich)', 'b83-wetter' ), '14–120' ),
			array( 'icongroesse', __( 'Icon-Größe in px', 'b83-wetter' ), '12–200' ),
			array( 'abstand', __( 'Innenabstand in px', 'b83-wetter' ), '0–80' ),
			array( 'tage', __( 'Anzahl Tage (kompakt: Folgetage, ausführlich: inkl. heute)', 'b83-wetter' ), '0–5 / 1–7' ),
			array( 'inhalt', __( 'Inhalt der kleinen Darstellung', 'b83-wetter' ), 'temp, temp_cond, minmax, place' ),
			array( 'iconset', __( 'Icon-Set', 'b83-wetter' ), implode( ', ', array_keys( Icons::sets() ) ) ),
			array( 'akzent', __( 'Akzentfarbe', 'b83-wetter' ), '#f2a31b' ),
			array( 'titel', __( 'Ortsname überschreiben', 'b83-wetter' ), __( 'Text', 'b83-wetter' ) ),
			array( 'link', __( 'Link (klein, kompakt)', 'b83-wetter' ), __( 'URL, Seiten-ID oder none', 'b83-wetter' ) ),
			array( 'class', __( 'Eigene CSS-Klasse', 'b83-wetter' ), __( 'Text', 'b83-wetter' ) ),
		);
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Attribut', 'b83-wetter' ) . '</th><th>' . esc_html__( 'Bedeutung', 'b83-wetter' ) . '</th><th>' . esc_html__( 'Werte', 'b83-wetter' ) . '</th></tr></thead><tbody>';
		foreach ( $attrs as $a ) {
			echo '<tr><td><code>' . esc_html( $a[0] ) . '</code></td><td>' . esc_html( $a[1] ) . '</td><td>' . esc_html( $a[2] ) . '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<p class="description">' . esc_html__( 'Fehlt ein Attribut, gilt der Wert aus dem Design-Reiter.', 'b83-wetter' ) . '</p>';

		echo '<h2>' . esc_html__( 'Icon-Sets', 'b83-wetter' ) . '</h2>';
		echo '<div class="b83-wetter-iconsets">';
		foreach ( Icons::sets() as $set => $label ) {
			echo '<div class="b83-wetter-iconset b83w-set-' . esc_attr( $set ) . '" style="--b83w-accent:#f2a31b"><strong>' . esc_html( $label ) . '</strong><div class="b83-wetter-iconset__grid">';
			foreach ( array_keys( Conditions::all() ) as $cond ) {
				echo Icons::svg( $cond, false, Conditions::label( $cond ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG aus Icons.
			}
			echo Icons::svg( 'clear', true, Conditions::label( 'clear', true ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo Icons::svg( 'partly', true, Conditions::label( 'partly', true ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '</div></div>';
		}
		echo '</div>';

		echo '<h2>' . esc_html__( 'Datenquellen und Lizenz', 'b83-wetter' ) . '</h2>';
		echo '<p>' . esc_html__( 'Die Daten stammen von GeoSphere Austria und MET Norway und stehen unter der Lizenz CC BY 4.0. Die Quellenangabe ist Pflicht: Kompakt und Ausführlich zeigen sie direkt an, die kleine Darstellung mit einem ©-Zeichen – oder, wenn eine Wetterseite eingestellt ist, über den Link dorthin.', 'b83-wetter' ) . '</p>';
		echo '</div>';
	}

	/* ---------------------------------------------------------------------
	 * Formular-Hilfen
	 * ------------------------------------------------------------------- */

	private function text_field( string $name, string $id, string $label, string $value, string $placeholder, int $max ): void {
		echo '<div class="b83-wetter-field"><label class="b83-wetter-label" for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
		printf(
			'<input type="text" class="regular-text" id="%1$s" name="%2$s" value="%3$s" placeholder="%4$s" maxlength="%5$d">',
			esc_attr( $id ),
			esc_attr( $name ),
			esc_attr( $value ),
			esc_attr( $placeholder ),
			(int) $max
		);
		echo '</div>';
	}

	private function checkbox( string $name, string $value, string $label ): void {
		echo '<label class="b83-wetter-check"><input type="hidden" name="' . esc_attr( $name ) . '" value="0">';
		echo '<input type="checkbox" name="' . esc_attr( $name ) . '" value="1"' . checked( '1', $value, false ) . '> ' . esc_html( $label ) . '</label>';
	}

	/* ---------------------------------------------------------------------
	 * Aktionen
	 * ------------------------------------------------------------------- */

	private function guard( string $nonce_action ): void {
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'b83-wetter' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( $nonce_action );
	}

	/** @param array<string,string> $args Parameter. */
	private function redirect( array $args ): never {
		wp_safe_redirect( $this->url( $args ) );
		exit;
	}

	public function handle_save_settings(): void {
		$this->guard( 'b83_wetter_save_settings' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- wird in Sanitizer::settings geprüft.
		$input   = isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array();
		$allowed = array( 'location_name', 'lat', 'lng', 'altitude', 'source', 'fallback', 'interval', 'link_page', 'purge_cache', 'delete_on_uninstall' );
		$current = $this->repository->get_settings();

		$this->repository->save_settings( array_merge( $current, array_intersect_key( (array) $input, array_flip( $allowed ) ) ) );

		if ( null === $this->repository->location() ) {
			$this->redirect( array( 'tab' => 'settings', 'notice' => 'saved_noloc' ) );
		}
		$ok = $this->refresher->refresh();
		$this->redirect( array( 'tab' => 'settings', 'notice' => $ok ? 'saved' : 'saved_nodata' ) );
	}

	public function handle_save_design(): void {
		$this->guard( 'b83_wetter_save_design' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- wird in Sanitizer::design geprüft.
		$design             = isset( $_POST['design'] ) && is_array( $_POST['design'] ) ? wp_unslash( $_POST['design'] ) : array();
		$settings           = $this->repository->get_settings();
		$settings['design'] = (array) $design;
		$this->repository->save_settings( $settings );

		$this->redirect( array( 'tab' => 'design', 'notice' => 'design' ) );
	}

	public function handle_refresh(): void {
		$this->guard( 'b83_wetter_refresh' );
		delete_transient( 'b83_wetter_lock' );
		$ok = $this->refresher->refresh();
		$this->redirect( array( 'tab' => 'status', 'notice' => $ok ? 'refreshed' : 'refresh_error' ) );
	}

	public function ajax_preview(): void {
		check_ajax_referer( 'b83_wetter_ajax' );
		if ( ! current_user_can( self::capability() ) ) {
			wp_send_json_error( null, 403 );
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- wird in Sanitizer::design geprüft.
		$raw    = isset( $_POST['design'] ) && is_array( $_POST['design'] ) ? wp_unslash( $_POST['design'] ) : array();
		$design = Sanitizer::design( (array) $raw, false );
		wp_send_json_success( array( 'html' => $this->preview_html( $design ) ) );
	}

	/** Ort suchen über Nominatim (OpenStreetMap) – nur im Backend, über den Server. */
	public function ajax_geocode(): void {
		check_ajax_referer( 'b83_wetter_ajax' );
		if ( ! current_user_can( self::capability() ) ) {
			wp_send_json_error( null, 403 );
		}
		$query = Sanitizer::text( wp_unslash( $_POST['q'] ?? '' ), 150 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitizer::text.
		if ( '' === $query ) {
			wp_send_json_error();
		}
		// Nominatim-Richtlinie: höchstens eine Anfrage pro Sekunde.
		if ( get_transient( 'b83_wetter_geo_lock' ) ) {
			sleep( 1 );
		}
		set_transient( 'b83_wetter_geo_lock', 1, 2 );

		$url = add_query_arg(
			array(
				'format'          => 'jsonv2',
				'limit'           => 1,
				'q'               => rawurlencode( $query ),
				'accept-language' => 'de',
			),
			'https://nominatim.openstreetmap.org/search'
		);
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout' => 8,
				'headers' => array(
					'User-Agent' => \B83\Wetter\Data\Http::user_agent(),
					'Referer'    => home_url( '/' ),
				),
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			wp_send_json_error();
		}
		$body  = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$first = is_array( $body ) ? ( $body[0] ?? null ) : null;
		if ( ! is_array( $first ) ) {
			wp_send_json_error();
		}
		$lat = Sanitizer::coordinate( $first['lat'] ?? '', 90 );
		$lng = Sanitizer::coordinate( $first['lon'] ?? '', 180 );
		if ( '' === $lat || '' === $lng ) {
			wp_send_json_error();
		}
		$name = Sanitizer::text( (string) ( $first['name'] ?? '' ), 80 );
		if ( '' === $name ) {
			$name = Sanitizer::text( strtok( (string) ( $first['display_name'] ?? '' ), ',' ) ?: '', 80 );
		}
		wp_send_json_success(
			array(
				'lat'     => $lat,
				'lng'     => $lng,
				'name'    => $name,
				'display' => Sanitizer::text( (string) ( $first['display_name'] ?? '' ), 200 ),
			)
		);
	}
}
