<?php
/**
 * Ausgabe der drei Darstellungen: klein (einzeilig), kompakt, ausführlich.
 *
 * Das HTML wird auf dem Server erzeugt (seitencache-tauglich, kein JavaScript
 * nötig). Farben und Größen stehen als CSS-Variablen am Widget und lassen sich
 * im Backend, per Shortcode-Attribut oder im Theme-CSS überschreiben.
 *
 * @package B83\Wetter
 */

declare(strict_types=1);

namespace B83\Wetter\Frontend;

use B83\Wetter\Conditions;
use B83\Wetter\Data\Forecast;
use B83\Wetter\Design;
use B83\Wetter\Icons;
use B83\Wetter\Repository;
use B83\Wetter\Sanitizer;
use B83\Wetter\Scheduler;

defined( 'ABSPATH' ) || exit;

final class Renderer {

	private ?Scheduler $scheduler = null;

	public function __construct( private Repository $repository ) {}

	public function set_scheduler( Scheduler $scheduler ): void {
		$this->scheduler = $scheduler;
	}

	/**
	 * Widget ausgeben.
	 *
	 * @param string              $view      klein | kompakt | ausfuehrlich.
	 * @param array<string,mixed> $overrides Geprüfte Design-Überschreibungen ('' = Backend-Wert).
	 * @param array<string,mixed> $opts      class, link ('' = Einstellung, 'none' = kein Link, URL), title,
	 *                                       preview (Backend/Editor), design (vollständiges Design statt Backend-Werten),
	 *                                       forecast (Beispieldaten für die Vorschau).
	 */
	public function render( string $view, array $overrides = array(), array $opts = array() ): string {
		$view     = Sanitizer::view( $view );
		$base     = isset( $opts['design'] ) && is_array( $opts['design'] ) ? $opts['design'] : $this->repository->design();
		$design   = Design::merge( $base, $overrides );
		$settings = $this->repository->get_settings();
		$location = $this->repository->location();
		$forecast = isset( $opts['forecast'] ) && $opts['forecast'] instanceof Forecast ? $opts['forecast'] : $this->repository->forecast();
		$demo     = isset( $opts['forecast'] );
		$preview  = ! empty( $opts['preview'] );

		/**
		 * Design-Werte vor der Ausgabe.
		 *
		 * @param array<string,mixed> $design Werte.
		 * @param string              $view   Darstellung.
		 */
		$design = (array) apply_filters( 'b83_wetter_render_design', $design, $view );

		if ( null !== $this->scheduler && ! $preview ) {
			$this->scheduler->maybe_refresh_in_background();
		}

		if ( ( null === $location && ! $demo ) || $forecast->is_empty() || null === $forecast->current() ) {
			if ( ! current_user_can( 'edit_posts' ) && ! $preview ) {
				return '';
			}
			$msg = null === $location
				? __( 'b83 Wetter: Bitte zuerst unter b83 → Wetter einen Standort eintragen.', 'b83-wetter' )
				: __( 'b83 Wetter: Noch keine aktuellen Wetterdaten – bitte unter b83 → Wetter → Status „Jetzt aktualisieren“ wählen.', 'b83-wetter' );
			return '<div class="b83-wetter-notice" role="note">' . esc_html( $msg ) . '</div>';
		}

		$title = isset( $opts['title'] ) && '' !== (string) $opts['title'] ? (string) $opts['title'] : (string) ( $location['name'] ?? '' );
		$link  = $this->link( $opts['link'] ?? '', $settings );

		$ctx = array(
			'view'     => $view,
			'design'   => $design,
			'forecast' => $forecast,
			'title'    => $title,
			'link'     => $link,
		);

		$inner = match ( $view ) {
			'klein'        => $this->small( $ctx ),
			'ausfuehrlich' => $this->detail( $ctx ),
			default        => $this->compact( $ctx ),
		};

		$classes = array(
			'b83-wetter',
			'b83-wetter--' . $view,
			'b83w-set-' . sanitize_html_class( (string) $design['icon_set'] ),
		);
		if ( ! empty( $opts['class'] ) ) {
			foreach ( preg_split( '/\s+/', (string) $opts['class'] ) as $c ) {
				$c = sanitize_html_class( $c );
				if ( '' !== $c ) {
					$classes[] = $c;
				}
			}
		}

		// Zum Nachladen veralteter Widgets aus dem Seiten-Cache.
		$args = array_filter(
			array_merge(
				$overrides,
				array(
					'link'  => is_string( $opts['link'] ?? null ) ? (string) $opts['link'] : '',
					'title' => isset( $opts['title'] ) ? (string) $opts['title'] : '',
					'class' => isset( $opts['class'] ) ? (string) $opts['class'] : '',
				)
			),
			static fn( $v ): bool => '' !== $v && null !== $v
		);
		$max_age = (int) $settings['interval'] * MINUTE_IN_SECONDS + 10 * MINUTE_IN_SECONDS;

		$tag   = 'klein' === $view ? ( '' !== $link ? 'a' : 'span' ) : 'div';
		$attrs = array(
			'class'           => implode( ' ', $classes ),
			'style'           => $this->style_vars( $view, $design ),
			'data-b83w-view'  => $view,
			'data-b83w-stand' => (string) $forecast->fetched(),
			'data-b83w-age'   => (string) $max_age,
		);
		if ( ! empty( $args ) ) {
			$attrs['data-b83w-args'] = (string) wp_json_encode( $args );
		}
		if ( 'a' === $tag ) {
			$attrs['href'] = $link;
		}
		if ( 'klein' === $view ) {
			$current        = $forecast->current();
			$attrs['title'] = sprintf(
				/* translators: 1: Ort, 2: Wetterlage, 3: Temperatur */
				__( 'Wetter %1$s: %2$s, %3$s', 'b83-wetter' ),
				$title,
				Conditions::label( (string) $current['cond'], (bool) $current['night'] ),
				$this->temp( (float) $current['temp'] )
			);
		}

		$html = '<' . $tag;
		foreach ( $attrs as $name => $value ) {
			$html .= ' ' . $name . '="' . ( 'href' === $name ? esc_url( $value ) : esc_attr( $value ) ) . '"';
		}
		$html .= '>' . $inner . '</' . $tag . '>';

		if ( ! $preview ) {
			Assets::enqueue();
		}

		/**
		 * HTML eines Wetter-Widgets.
		 *
		 * @param string $html HTML.
		 * @param string $view Darstellung.
		 */
		return (string) apply_filters( 'b83_wetter_html', $html, $view );
	}

	/* ---------------------------------------------------------------------
	 * Darstellungen
	 * ------------------------------------------------------------------- */

	/** @param array<string,mixed> $ctx Kontext. */
	private function small( array $ctx ): string {
		/** @var Forecast $forecast */
		$forecast = $ctx['forecast'];
		$now      = $forecast->current();
		$design   = $ctx['design'];
		$content  = (string) $design['small_content'];
		$night    = (bool) $now['night'];
		$label    = Conditions::label( (string) $now['cond'], $night );

		$out = '';
		if ( 'place' === $content && '' !== $ctx['title'] ) {
			$out .= '<span class="b83-wetter__place">' . esc_html( $ctx['title'] ) . '</span>';
		}
		$out .= Icons::svg( (string) $now['cond'], $night );
		$out .= '<span class="b83-wetter__temp">' . esc_html( $this->temp( (float) $now['temp'] ) ) . '</span>';

		if ( 'temp_cond' === $content ) {
			$out .= '<span class="b83-wetter__cond">' . esc_html( $label ) . '</span>';
		} elseif ( 'minmax' === $content ) {
			$today = $forecast->days( 1 )[0] ?? null;
			if ( null !== $today ) {
				$out .= '<span class="b83-wetter__minmax b83-wetter__muted">'
					. '<span class="b83-wetter__sr">' . esc_html__( 'Höchstwert', 'b83-wetter' ) . ' </span>' . esc_html( $this->temp( (float) $today['max'] ) )
					. ' / '
					. '<span class="b83-wetter__sr">' . esc_html__( 'Tiefstwert', 'b83-wetter' ) . ' </span>' . esc_html( $this->temp( (float) $today['min'] ) )
					. '</span>';
			}
		}
		if ( 'temp_cond' !== $content ) {
			$out .= '<span class="b83-wetter__sr">' . esc_html( $label ) . '</span>';
		}

		// Quellenangabe: mit Link zur Wetterseite steht sie dort, sonst als kleines Zeichen.
		if ( '' === $ctx['link'] ) {
			$out .= $this->credit_mini( $forecast );
		}
		return $out;
	}

	/** @param array<string,mixed> $ctx Kontext. */
	private function compact( array $ctx ): string {
		/** @var Forecast $forecast */
		$forecast = $ctx['forecast'];
		$now      = $forecast->current();
		$design   = $ctx['design'];
		$night    = (bool) $now['night'];
		$count    = (int) $design['compact_days'];

		$head  = '<div class="b83-wetter__now">';
		$head .= Icons::svg( (string) $now['cond'], $night );
		$head .= '<div class="b83-wetter__now-text">';
		$head .= '<div class="b83-wetter__temp">' . esc_html( $this->temp( (float) $now['temp'] ) ) . '</div>';
		$head .= '<div class="b83-wetter__cond">' . esc_html( Conditions::label( (string) $now['cond'], $night ) ) . '</div>';
		if ( '1' === $design['show_location'] && '' !== $ctx['title'] ) {
			$head .= '<div class="b83-wetter__place b83-wetter__muted">' . esc_html( $ctx['title'] ) . '</div>';
		}
		$head .= '</div></div>';

		$days_html = '';
		if ( $count > 0 ) {
			// Ab morgen; heute steckt in den aktuellen Werten.
			$days = array_slice( $forecast->days( $count + 1 ), 1, $count );
			if ( ! empty( $days ) ) {
				$days_html = '<ul class="b83-wetter__days" style="--b83w-cols:' . (int) count( $days ) . '">';
				foreach ( $days as $day ) {
					$days_html .= '<li class="b83-wetter__day">'
						. '<span class="b83-wetter__dayname">' . esc_html( $this->day_name( (string) $day['date'], true ) ) . '</span>'
						. Icons::svg( (string) $day['cond'], false, Conditions::label( (string) $day['cond'] ) )
						. '<span class="b83-wetter__range">'
						. '<span class="b83-wetter__max"><span class="b83-wetter__sr">' . esc_html__( 'Höchstwert', 'b83-wetter' ) . ' </span>' . esc_html( $this->temp( (float) $day['max'] ) ) . '</span> '
						. '<span class="b83-wetter__min b83-wetter__muted"><span class="b83-wetter__sr">' . esc_html__( 'Tiefstwert', 'b83-wetter' ) . ' </span>' . esc_html( $this->temp( (float) $day['min'] ) ) . '</span>'
						. '</span></li>';
				}
				$days_html .= '</ul>';
			}
		}

		$body = $head . $days_html . $this->credit( $forecast, false );
		if ( '' !== $ctx['link'] ) {
			$body .= '<a class="b83-wetter__more" href="' . esc_url( $ctx['link'] ) . '">' . esc_html__( 'Zur Wettervorhersage', 'b83-wetter' ) . '</a>';
		}
		return $body;
	}

	/** @param array<string,mixed> $ctx Kontext. */
	private function detail( array $ctx ): string {
		/** @var Forecast $forecast */
		$forecast = $ctx['forecast'];
		$now      = $forecast->current();
		$design   = $ctx['design'];
		$night    = (bool) $now['night'];
		$days     = $forecast->days( (int) $design['detail_days'] );
		$today    = $days[0] ?? null;

		$out = '';
		if ( '1' === $design['show_location'] && '' !== $ctx['title'] ) {
			$out .= '<div class="b83-wetter__head"><div class="b83-wetter__title">' . esc_html( $ctx['title'] ) . '</div>'
				. '<div class="b83-wetter__muted b83-wetter__date">' . esc_html( wp_date( 'l, j. F', time() ) ) . '</div></div>';
		}

		$out .= '<div class="b83-wetter__now">';
		$out .= Icons::svg( (string) $now['cond'], $night );
		$out .= '<div class="b83-wetter__now-text">';
		$out .= '<div class="b83-wetter__temp">' . esc_html( $this->temp( (float) $now['temp'] ) ) . '</div>';
		$out .= '<div class="b83-wetter__cond">' . esc_html( Conditions::label( (string) $now['cond'], $night ) ) . '</div>';
		if ( null !== $today ) {
			$out .= '<div class="b83-wetter__muted b83-wetter__today">'
				. esc_html(
					sprintf(
						/* translators: 1: Höchstwert, 2: Tiefstwert */
						__( 'Heute %1$s / %2$s', 'b83-wetter' ),
						$this->temp( (float) $today['max'] ),
						$this->temp( (float) $today['min'] )
					)
				) . '</div>';
		}
		$out .= '</div>';

		$facts = array();
		if ( isset( $now['wind'] ) && is_numeric( $now['wind'] ) ) {
			$wind = $this->number( (float) $now['wind'] ) . ' km/h';
			if ( isset( $now['dir'] ) && is_numeric( $now['dir'] ) && (float) $now['wind'] >= 2 ) {
				/* translators: %s: Himmelsrichtung, z. B. NW */
				$wind .= ' ' . sprintf( __( 'aus %s', 'b83-wetter' ), $this->direction( (int) $now['dir'] ) );
			}
			$facts[ __( 'Wind', 'b83-wetter' ) ] = $wind;
		}
		if ( isset( $now['gust'] ) && is_numeric( $now['gust'] ) && (float) $now['gust'] >= 20 ) {
			$facts[ __( 'Böen', 'b83-wetter' ) ] = $this->number( (float) $now['gust'] ) . ' km/h';
		}
		if ( isset( $now['humidity'] ) && is_numeric( $now['humidity'] ) ) {
			$facts[ __( 'Luftfeuchte', 'b83-wetter' ) ] = (int) $now['humidity'] . ' %';
		}
		if ( null !== $today ) {
			$facts[ __( 'Niederschlag heute', 'b83-wetter' ) ] = $this->precip( (float) $today['precip'] );
		}
		if ( isset( $now['snowline'] ) && is_numeric( $now['snowline'] ) && (int) $now['snowline'] > 0 && (int) $now['snowline'] < 4000 ) {
			$facts[ __( 'Schneefallgrenze', 'b83-wetter' ) ] = number_format_i18n( (int) round( (int) $now['snowline'] / 50 ) * 50 ) . ' m';
		}
		if ( ! empty( $facts ) ) {
			$out .= '<dl class="b83-wetter__facts">';
			foreach ( $facts as $label => $value ) {
				$out .= '<div><dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd></div>';
			}
			$out .= '</dl>';
		}
		$out .= '</div>';

		if ( ! empty( $days ) ) {
			$out .= '<ul class="b83-wetter__days">';
			foreach ( $days as $i => $day ) {
				$label = Conditions::label( (string) $day['cond'] );
				$out  .= '<li class="b83-wetter__day' . ( 0 === $i ? ' is-today' : '' ) . '">'
					. '<span class="b83-wetter__dayname">' . esc_html( $this->day_name( (string) $day['date'], false ) ) . '</span>'
					. '<span class="b83-wetter__muted b83-wetter__daydate">' . esc_html( $this->day_date( (string) $day['date'] ) ) . '</span>'
					. Icons::svg( (string) $day['cond'], false )
					. '<span class="b83-wetter__cond">' . esc_html( $label ) . '</span>'
					. '<span class="b83-wetter__range">'
					. '<span class="b83-wetter__max"><span class="b83-wetter__sr">' . esc_html__( 'Höchstwert', 'b83-wetter' ) . ' </span>' . esc_html( $this->temp( (float) $day['max'] ) ) . '</span>'
					. '<span class="b83-wetter__min b83-wetter__muted"><span class="b83-wetter__sr">' . esc_html__( 'Tiefstwert', 'b83-wetter' ) . ' </span>' . esc_html( $this->temp( (float) $day['min'] ) ) . '</span>'
					. '</span>'
					. '<span class="b83-wetter__muted b83-wetter__extra">'
					. '<span title="' . esc_attr__( 'Niederschlag', 'b83-wetter' ) . '">' . self::mini_icon( 'drop' ) . esc_html( $this->precip( (float) $day['precip'] ) ) . '</span>'
					. '<span title="' . esc_attr__( 'Wind', 'b83-wetter' ) . '">' . self::mini_icon( 'wind' ) . esc_html( $this->number( (float) $day['wind'] ) . ' km/h' ) . '</span>'
					. '</span>'
					. '</li>';
			}
			$out .= '</ul>';
		}

		$out .= $this->credit( $forecast, true );
		return $out;
	}

	/* ---------------------------------------------------------------------
	 * Quellenangabe (Pflicht laut Lizenz CC BY 4.0)
	 * ------------------------------------------------------------------- */

	private function credit( Forecast $forecast, bool $long ): string {
		$links = array();
		foreach ( $forecast->sources() as $key ) {
			$source = $this->repository->source( $key );
			if ( null !== $source ) {
				$links[] = '<a href="' . esc_url( $source->url() ) . '" target="_blank" rel="noopener">' . esc_html( $source->label() ) . '</a>';
			}
		}
		if ( empty( $links ) ) {
			return '';
		}
		$license = '<a href="https://creativecommons.org/licenses/by/4.0/deed.de" target="_blank" rel="noopener license">CC BY 4.0</a>';

		$text = sprintf(
			/* translators: 1: Quellen, 2: Lizenz */
			esc_html__( 'Wetterdaten: %1$s (%2$s), aufbereitet', 'b83-wetter' ),
			implode( ', ', $links ),
			$license
		);
		if ( $long && $forecast->fetched() > 0 ) {
			/* translators: %s: Uhrzeit */
			$text .= ' · ' . esc_html( sprintf( __( 'Stand %s Uhr', 'b83-wetter' ), wp_date( 'H:i', $forecast->fetched() ) ) );
		}
		return '<p class="b83-wetter__source b83-wetter__muted">' . $text . '</p>';
	}

	private function credit_mini( Forecast $forecast ): string {
		$names = array();
		$first = null;
		foreach ( $forecast->sources() as $key ) {
			$source = $this->repository->source( $key );
			if ( null !== $source ) {
				$names[] = $source->label();
				$first   = $first ?? $source;
			}
		}
		if ( null === $first ) {
			return '';
		}
		/* translators: %s: Quellen */
		$title = sprintf( __( 'Wetterdaten: %s (CC BY 4.0), aufbereitet', 'b83-wetter' ), implode( ', ', $names ) );
		return '<a class="b83-wetter__credit b83-wetter__muted" href="' . esc_url( $first->url() ) . '" target="_blank" rel="noopener" title="' . esc_attr( $title ) . '" aria-label="' . esc_attr( $title ) . '">&copy;</a>';
	}

	/* ---------------------------------------------------------------------
	 * Hilfen
	 * ------------------------------------------------------------------- */

	/**
	 * @param mixed               $value    Link-Angabe aus Shortcode/Widget.
	 * @param array<string,mixed> $settings Einstellungen.
	 */
	private function link( mixed $value, array $settings ): string {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( in_array( strtolower( $value ), array( 'none', '0', 'nein', 'no' ), true ) ) {
			return '';
		}
		if ( '' !== $value ) {
			if ( ctype_digit( $value ) ) {
				return (string) get_permalink( (int) $value );
			}
			return esc_url_raw( $value, array( 'http', 'https' ) );
		}
		$page = (int) $settings['link_page'];
		if ( $page > 0 && 'publish' === get_post_status( $page ) ) {
			// Nicht auf die Wetterseite selbst verlinken.
			if ( (int) get_queried_object_id() === $page ) {
				return '';
			}
			return (string) get_permalink( $page );
		}
		return '';
	}

	/**
	 * CSS-Variablen für die Darstellung.
	 *
	 * @param array<string,mixed> $d Design.
	 */
	private function style_vars( string $view, array $d ): string {
		$p    = Design::VIEWS[ $view ];
		$vars = array();

		$color = Sanitizer::color( $d[ $p . '_color' ] ?? '' ) ?: Sanitizer::color( $d['color'] );
		$bg    = Sanitizer::color( $d[ $p . '_bg' ] ?? '' ) ?: Sanitizer::color( $d['bg'] );
		$muted = Sanitizer::color( $d['muted'] );

		if ( '' !== $color ) {
			$vars['--b83w-color'] = $color;
		}
		if ( '' !== $bg ) {
			$vars['--b83w-bg'] = $bg;
		}
		if ( '' !== $muted ) {
			$vars['--b83w-muted']         = $muted;
			$vars['--b83w-muted-opacity'] = '1';
		}
		$accent = Sanitizer::color( $d['accent'] );
		if ( '' !== $accent ) {
			$vars['--b83w-accent'] = $accent;
		}
		$vars['--b83w-radius'] = (int) $d['radius'] . 'px';
		if ( (int) $d['border_width'] > 0 ) {
			$border               = Sanitizer::color( $d['border_color'] ) ?: 'currentColor';
			$vars['--b83w-border'] = (int) $d['border_width'] . 'px solid ' . $border;
		}
		$vars['--b83w-weight'] = (string) (int) $d['font_weight'];
		$vars['--b83w-fs']     = (int) $d[ $p . '_font_size' ] . 'px';
		$vars['--b83w-icon']   = (int) $d[ $p . '_icon_size' ] . 'px';
		$vars['--b83w-pad']    = (int) $d[ $p . '_padding' ] . 'px';
		if ( isset( $d[ $p . '_temp_size' ] ) ) {
			$vars['--b83w-temp'] = (int) $d[ $p . '_temp_size' ] . 'px';
		}
		if ( 'ausfuehrlich' === $view ) {
			$vars['--b83w-day-icon'] = (int) $d['detail_day_icon'] . 'px';
			$card                    = Sanitizer::color( $d['detail_card_bg'] );
			if ( '' !== $card ) {
				$vars['--b83w-card'] = $card;
			}
		}

		$css = '';
		foreach ( $vars as $name => $value ) {
			$css .= $name . ':' . $value . ';';
		}
		return $css;
	}

	public function temp( float $value ): string {
		$rounded = (int) round( $value );
		// Echtes Minuszeichen, keine „-0°“.
		return ( $rounded < 0 ? "\u{2212}" : '' ) . number_format_i18n( abs( $rounded ) ) . '°';
	}

	private function number( float $value ): string {
		return number_format_i18n( (int) round( $value ) );
	}

	private function precip( float $mm ): string {
		if ( $mm < 0.1 ) {
			return '0 mm';
		}
		return number_format_i18n( $mm, $mm < 10 ? 1 : 0 ) . ' mm';
	}

	private function direction( int $deg ): string {
		$dirs = array( 'N', 'NO', 'O', 'SO', 'S', 'SW', 'W', 'NW' );
		return $dirs[ (int) round( ( ( $deg % 360 ) + 360 ) % 360 / 45 ) % 8 ];
	}

	private function day_name( string $date, bool $short ): string {
		$tz       = wp_timezone();
		$today    = ( new \DateTimeImmutable( 'now', $tz ) )->format( 'Y-m-d' );
		$tomorrow = ( new \DateTimeImmutable( 'tomorrow', $tz ) )->format( 'Y-m-d' );
		if ( $date === $today ) {
			return __( 'Heute', 'b83-wetter' );
		}
		if ( $date === $tomorrow && ! $short ) {
			return __( 'Morgen', 'b83-wetter' );
		}
		$ts = ( new \DateTimeImmutable( $date . ' 12:00', $tz ) )->getTimestamp();
		return $short ? rtrim( (string) wp_date( 'D', $ts ), '.' ) : (string) wp_date( 'l', $ts );
	}

	private function day_date( string $date ): string {
		$ts = ( new \DateTimeImmutable( $date . ' 12:00', wp_timezone() ) )->getTimestamp();
		return (string) wp_date( 'j.n.', $ts );
	}

	/** Kleine Symbole für Niederschlag und Wind (einfarbig). */
	public static function mini_icon( string $type ): string {
		$path = 'drop' === $type
			? '<path d="M8 1.8C6.2 4.6 3.8 7.3 3.8 9.9a4.2 4.2 0 0 0 8.4 0c0-2.6-2.4-5.3-4.2-8.1z"/>'
			: '<path d="M1.5 6h8.2a2.2 2.2 0 1 0-2.1-2.9M1.5 9.5h11a2.2 2.2 0 1 1-2.1 2.9M1.5 13h5" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>';
		return '<svg class="b83-wetter__mini" viewBox="0 0 16 16" aria-hidden="true" focusable="false" fill="currentColor">' . $path . '</svg>';
	}
}
