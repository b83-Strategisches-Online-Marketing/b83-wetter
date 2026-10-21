<?php
/**
 * Installation und Datenbank-Versionen.
 *
 * @package B83\Wetter
 */

declare(strict_types=1);

namespace B83\Wetter;

defined( 'ABSPATH' ) || exit;

final class Migration {

	public const DB_VERSION        = 1;
	public const OPTION_DB_VERSION = 'b83_wetter_db_version';

	public static function on_activation(): void {
		self::run();
	}

	/** Läuft bei jedem Seitenaufruf im Backend, tut aber nur bei Versionswechsel etwas. */
	public static function maybe_run(): void {
		if ( (int) get_option( self::OPTION_DB_VERSION, 0 ) < self::DB_VERSION ) {
			self::run();
		}
	}

	private static function run(): void {
		if ( false === get_option( Repository::OPTION_SETTINGS, false ) ) {
			add_option( Repository::OPTION_SETTINGS, Sanitizer::settings( array() ), '', false );
		}

		/*
		 * Künftige Migrationen hier nach Version ergänzen, z. B.:
		 * if ( $from < 2 ) { … }
		 */

		update_option( self::OPTION_DB_VERSION, self::DB_VERSION, true );
	}
}
