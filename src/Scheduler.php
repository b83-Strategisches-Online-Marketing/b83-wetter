<?php
/**
 * Regelmäßige Aktualisierung über WP-Cron.
 *
 * Besucher lösen nie selbst eine Abfrage beim Wetterdienst aus. Sind die
 * Daten veraltet (z. B. weil WP-Cron länger nicht lief), wird nur eine
 * einmalige Aktualisierung im Hintergrund eingeplant.
 *
 * @package B83\Wetter
 */

declare(strict_types=1);

namespace B83\Wetter;

use B83\Wetter\Data\Refresher;

defined( 'ABSPATH' ) || exit;

final class Scheduler {

	public const HOOK       = 'b83_wetter_refresh';
	public const HOOK_ASYNC = 'b83_wetter_refresh_async';
	public const SCHEDULE   = 'b83_wetter_interval';

	public function __construct( private Repository $repository, private Refresher $refresher ) {}

	public function register(): void {
		add_filter( 'cron_schedules', array( $this, 'add_schedule' ) ); // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval -- Intervall ist einstellbar (mind. 30 Minuten).
		add_action( self::HOOK, array( $this, 'run' ) );
		add_action( self::HOOK_ASYNC, array( $this, 'run' ) );
		add_action( 'init', array( $this, 'ensure_scheduled' ) );
		add_action( 'b83_wetter_settings_saved', array( $this, 'reschedule' ) );
	}

	/**
	 * @param array<string,array<string,mixed>> $schedules Zeitpläne.
	 * @return array<string,array<string,mixed>>
	 */
	public function add_schedule( array $schedules ): array {
		$minutes                     = (int) $this->repository->get_settings()['interval'];
		$schedules[ self::SCHEDULE ] = array(
			'interval' => max( 30, $minutes ) * MINUTE_IN_SECONDS,
			/* translators: %d: Minuten */
			'display'  => sprintf( __( 'b83 Wetter: alle %d Minuten', 'b83-wetter' ), $minutes ),
		);
		return $schedules;
	}

	public function run(): void {
		$this->refresher->refresh();
	}

	public function ensure_scheduled(): void {
		if ( null === $this->repository->location() ) {
			return;
		}
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			// Frische Daten: erst nach Ablauf des Intervalls wieder abfragen.
			$delay = $this->refresher->is_stale() ? MINUTE_IN_SECONDS : max( 30, (int) $this->repository->get_settings()['interval'] ) * MINUTE_IN_SECONDS;
			wp_schedule_event( time() + $delay, self::SCHEDULE, self::HOOK );
		}
	}

	public function reschedule(): void {
		wp_clear_scheduled_hook( self::HOOK );
		$this->ensure_scheduled();
	}

	/** Veraltete Daten: einmalige Aktualisierung im Hintergrund einplanen (kein Warten für Besucher). */
	public function maybe_refresh_in_background(): void {
		if ( null === $this->repository->location() || ! $this->refresher->is_stale() ) {
			return;
		}
		if ( ! wp_next_scheduled( self::HOOK_ASYNC ) && ! get_transient( 'b83_wetter_async_planned' ) ) {
			set_transient( 'b83_wetter_async_planned', 1, 5 * MINUTE_IN_SECONDS );
			wp_schedule_single_event( time(), self::HOOK_ASYNC );
		}
	}

	public static function on_deactivation(): void {
		wp_clear_scheduled_hook( self::HOOK );
		wp_clear_scheduled_hook( self::HOOK_ASYNC );
	}
}
