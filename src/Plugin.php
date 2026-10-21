<?php
/**
 * Einstiegspunkt: verdrahtet alle Komponenten.
 *
 * @package B83\Wetter
 */

declare(strict_types=1);

namespace B83\Wetter;

use B83\Wetter\Admin\AdminPage;
use B83\Wetter\Data\Refresher;
use B83\Wetter\Frontend\Assets;
use B83\Wetter\Frontend\Renderer;
use B83\Wetter\Frontend\Rest;
use B83\Wetter\Frontend\Shortcode;
use B83\Wetter\Integrations\CachePurge;
use B83\Wetter\Integrations\Elementor;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	private static ?Plugin $instance = null;

	private Repository $repository;
	private Refresher $refresher;
	private Scheduler $scheduler;
	private Renderer $renderer;
	private bool $booted = false;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->repository = new Repository();
		$this->refresher  = new Refresher( $this->repository );
		$this->scheduler  = new Scheduler( $this->repository, $this->refresher );
		$this->renderer   = new Renderer( $this->repository );
		$this->renderer->set_scheduler( $this->scheduler );
	}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'admin_init', array( Migration::class, 'maybe_run' ) );

		$pages = new CachePurge( $this->repository );
		$pages->register();

		$this->scheduler->register();
		( new Updater() )->register();
		( new Assets( $pages ) )->register();
		( new Shortcode( $this->renderer ) )->register();
		( new Rest( $this->renderer ) )->register();
		( new Elementor( $this->renderer ) )->register();

		if ( is_admin() ) {
			( new AdminPage( $this->repository, $this->renderer, $this->refresher ) )->register();
		}

		/**
		 * Nach dem Laden aller Komponenten. Erweiterungen können sich hier einhängen.
		 *
		 * @param Plugin $plugin Instanz.
		 */
		do_action( 'b83_wetter_loaded', $this );
	}

	public function load_textdomain(): void {
		load_plugin_textdomain( 'b83-wetter', false, dirname( plugin_basename( B83_WETTER_FILE ) ) . '/languages' );
	}

	public function repository(): Repository {
		return $this->repository;
	}

	public function renderer(): Renderer {
		return $this->renderer;
	}

	public function refresher(): Refresher {
		return $this->refresher;
	}
}
