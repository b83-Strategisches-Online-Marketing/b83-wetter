# b83 Wetter – Projektregeln für Claude Code

> Diese Datei liest Claude Code bei jedem Start. Sie gilt für alle im Team.
> Persönliche Notizen gehören in `CLAUDE.local.md` (wird nicht eingecheckt).

## Über dieses Plugin

- **Name:** b83 Wetter
- **Slug / Text-Domain:** `b83-wetter`
- **Präfix für Funktionen, Optionen, Hooks:** `b83_wetter_` (Namespace: `B83\Wetter`)
- **Repository:** github.com/b83-Strategisches-Online-Marketing/b83-wetter (öffentlich)
- **Vertrieb:** intern, Updates über GitHub-Releases (öffentliches Repository)
- **Im Einsatz auf:** diversen Kundensites
- **Zweck in einem Satz:** Wettervorhersage aus offenen Daten (GeoSphere Austria, MET Norway) mit drei Darstellungen – klein, kompakt und ausführlich – per Shortcode oder Elementor-Widget.

## b83-Standards (gelten für alle b83-Plugins)

### Plugin-Header
```
Author:            Wolfgang Grabs-Schrempf / b83
Author URI:        https://b83.at
Requires PHP:      8.1
Requires at least: 6.0
Text Domain:       b83-wetter
License:           GPL-2.0-or-later
```
Kontakt in Doku und Support-Texten: office@b83.at

### Technik
- PHP 8.1 oder neuer. Typdeklarationen verwenden, wo sinnvoll.
- WordPress Coding Standards (WPCS). Kein schließendes `?>` am Dateiende.
- Jede PHP-Datei beginnt mit `defined( 'ABSPATH' ) || exit;`
- Neue Plugins: Klassen mit Namespace (`B83\PluginName`) und Autoloader. Dann **keine** `class_exists`-Guards.
- Klassen-Guards (`if ( ! class_exists( '...' ) )`) nur bei älteren Plugins ohne Namespace.
- `uninstall.php` beginnt mit `defined( 'WP_UNINSTALL_PLUGIN' ) || exit;` (nicht ABSPATH).
- Einstellungen über die Settings API, Optionen gesammelt in einem Array unter `b83_wetter_settings`.
- **Admin-Menü:** Einstellungen hängen unter dem gemeinsamen Menü „b83“ (Slug `b83`). Wenn das Menü noch nicht existiert, legt das Plugin es selbst an. Kein eigenes Top-Level-Menü.
- Muss ohne Elementor und ohne SEOPress funktionieren. Integrationen nur, wenn das jeweilige Plugin aktiv ist.
- Assets nur dort laden, wo sie gebraucht werden (nicht global im Frontend).

### Struktur
- Eine Zuständigkeit → eine Datei reicht.
- Zwei bis drei Bereiche → `includes/` mit einer Klasse pro Bereich.
- Vier oder mehr → tiefere Ordnerstruktur mit Autoloader.
- Assets in `assets/css`, `assets/js`, `assets/img`. Sprachdateien in `languages/`.

### Sicherheit (nicht verhandelbar)
- Jede Formular- und AJAX-Aktion: Nonce prüfen **und** `current_user_can()`.
- Eingaben mit `sanitize_*` bereinigen, Ausgaben mit `esc_html`, `esc_attr`, `esc_url`, `wp_kses_post` escapen.
- Datenbank nur über `$wpdb->prepare()`.
- AJAX: vor `wp_send_json_*()` den Output-Buffer leeren (`ob_clean()` falls aktiv), sonst kaputtes JSON.
- Keine Secrets im Code. Tokens kommen aus `wp-config.php` (z. B. `B83_GITHUB_TOKEN`).

### Updates und Lizenz
- Öffentliche Repos: Updates direkt über GitHub-Releases.
- Private Repos: Update-Abfrage mit `B83_GITHUB_TOKEN` aus der `wp-config.php` (Fine-grained Token, nur Lesen).
- Freemius-Plugins: SDK im Ordner `freemius/`, nicht verändern. Freemius-Secrets nie in öffentliche Repos.

### Sprache und Texte
- Code-Kommentare und Bezeichner auf Englisch.
- Oberfläche übersetzbar (`__()`, `esc_html__()` usw.). Verkaufs-Plugins: Englisch als Quellsprache, Deutsch als Übersetzung.
- Beschreibungen, readme.txt, Doku und Marketingtexte ohne typische KI-Schreibmuster formulieren (Humanizer-Regeln): keine Floskeln, keine Übertreibungen, keine Gedankenstrich-Ketten.

## Arbeitsweise

- Nur die Dateien lesen, die für die Aufgabe nötig sind. `freemius/`, `vendor/` und Build-Ordner nie durchsuchen.
- Vor größeren Änderungen kurz den Plan nennen, dann umsetzen.
- Nach jeder Änderung: `php -l` auf geänderte Dateien.
- Bestehende Shortcodes, Optionen und Hooks bleiben abwärtskompatibel, außer es ist ausdrücklich anders entschieden.
- Bei Reviews trennen: **kritisch** (Sicherheit, Datenverlust, Fatal Errors) vs. **sollte** vs. **wäre schön**.
- Versionsnummer nur beim Release erhöhen (siehe `/release`).
- Commit-Nachrichten auf Deutsch, kurz, im Präsens („Fügt Ticker-Tempo hinzu“).

## Entscheidungen in diesem Plugin

> Hier festhalten, was entschieden wurde, damit niemand es neu diskutiert.

> Wo eine Entscheidung unten von den b83-Standards oben abweicht, gilt für dieses Plugin die Entscheidung.

### Abweichungen von den b83-Standards (bewusst so, nichts umbenennen)

- **Ordner `src/` statt `includes/`:** PSR-4-Autoloader in der Hauptdatei, Unterordner `Admin/`, `Data/`, `Frontend/`, `Integrations/`.
- **Requires at least: 6.2** (Header) statt 6.0.
- **Author-Zeile:** `b83 – Wolfgang Grabs-Schrempf` statt `Wolfgang Grabs-Schrempf / b83`.
- **Sprache:** Quellsprache der Oberfläche ist Deutsch, Englisch (en_GB, en_US) als Übersetzung; Kommentare auf Deutsch. Das gilt trotz „Verkaufs-Plugins: Englisch als Quellsprache“, weil das Plugin nicht verkauft wird.
- **Optionen und Bezeichner bleiben unverändert:**
  - Option `b83_wetter_settings`, dazu `b83_wetter_data`, `b83_wetter_status`, `b83_wetter_db_version`
  - Shortcode `[b83_wetter]`, REST-Namespace `b83-wetter/v1`, Admin-Slug `b83-wetter`
  - Cron-Hooks `b83_wetter_refresh` und `b83_wetter_refresh_async`
- **REST-Route:** `permission_callback` ist `__return_true`, weil sie öffentliche Wetterdaten liefert.
- **`class_exists`:** nur für `\Elementor\...` (optionale Integration), keine Guards für eigene Klassen.

### Bisherige Projektregeln (aus dem früheren CLAUDE.md übernommen)

Dieses Repository gehört zu den WordPress-Plugins von b83 – Strategisches Online Marketing (https://b83.at, Wolfgang Grabs-Schrempf, office@b83.at). Jede Änderung folgt diesem Standard.

#### Kennzeichnung
- Slug, Ordner, Hauptdatei, Text Domain und Repository: `b83-<name>` (Kleinbuchstaben)
- Header: `Author: b83 – Wolfgang Grabs-Schrempf`, `Author URI: https://b83.at`, `Plugin URI: https://b83.at`, `License: GPL-2.0-or-later`, `Requires PHP: 8.1`, `Requires at least: 6.2`, `Update URI:` GitHub-URL des Repositorys
- Namespace `B83\<Name>`, Präfix für Optionen, Hooks, Capabilities, Nonces: `b83_<name>_` bzw. kurz `b83_<kürzel>_`

#### Struktur
- Hauptdatei nur Header, Konstanten, PSR-4-Autoloader (`src/`), Bootstrap
- Logik in Klassen unter `src/`, Backend in `src/Admin/`, Ausgabe in `src/Frontend/`
- CSS/JS als eigene Dateien unter `assets/`, kein Inline-Code außer kleiner Konfiguration (wp_localize_script)
- `uninstall.php` vorhanden; Daten nur löschen, wenn der Nutzer das aktiviert hat
- `SECURITY.md` (Meldeweg für Lücken) und `.github/dependabot.yml` (Actions aktuell halten) in jedem Repository
- `README.md` und `CHANGELOG.md` pflegen, Version in Header, Konstante und Changelog gleich halten

#### Backend-Menü
- Alle Einstellungen liegen im gemeinsamen Hauptmenü „b83“: Klasse `src/Admin/B83Menu.php` unverändert aus dem Schneebericht übernehmen (nur Namespace und Text Domain anpassen), dazu `assets/img/b83-logo.svg`, `assets/img/b83-menu-icon.svg`, `assets/css/b83-menu.css`
- Eigene Seite mit `add_submenu_page( B83Menu::SLUG, … )` einhängen, Link über Filter `b83_admin_plugin_links` melden
- Bei Änderungen an B83Menu `MENU_VERSION` erhöhen – die neueste Version auf einer Website baut das Menü

#### Sicherheit (Pflicht)
- `defined( 'ABSPATH' ) || exit;` in jeder PHP-Datei
- Jede schreibende Aktion: Capability prüfen **und** Nonce prüfen (`check_admin_referer` / `check_ajax_referer`)
- Eingaben: `wp_unslash`, dann zentral validieren (Whitelist statt Blacklist), Längen begrenzen
- Ausgaben: immer spät escapen (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`)
- SQL nur über `$wpdb->prepare()`; REST-Routen immer mit `permission_callback`
- Uploads: `is_uploaded_file`, Größen- und Formatprüfung
- Keine Daten im Plugin-Ordner speichern (wird bei Updates ersetzt), keine Secrets im Code
- Hinweise per festem Schlüssel in der URL, nie Freitext aus der URL ausgeben

#### Erweiterbarkeit
- Eigene Actions/Filter mit Präfix an sinnvollen Stellen, im README dokumentieren
- Datenbank-Version als Option, Migrationen in `Migration`
- Bestehende Shortcodes, CSS-Klassen und Endpunkte bei Umbauten kompatibel halten

#### Sprache
- Benutzeroberfläche Deutsch, alle Texte über `__()` / `esc_html__()` mit Text Domain übersetzbar
- Kommentare auf Deutsch

#### Updates und Releases
- `Update URI: https://github.com/b83-Strategisches-Online-Marketing/<repo>` im Header
- Eigene `Updater`-Klasse (Filter `update_plugins_github.com`), Release-Asset heißt `<slug>.zip`
- Private Repos: Token nur als Konstante `B83_GITHUB_TOKEN` in der wp-config.php, nie im Code
- `.github/workflows/release.yml` erstellt das Release automatisch, sobald eine neue Version auf `main` liegt
- Bei Cache-relevanten Daten: Cache der betroffenen Seiten nach dem Speichern leeren

#### Commits
- Aussagekräftige Commit-Nachrichten auf Deutsch
- Version im Header, in der Versionskonstante und im CHANGELOG gleichzeitig erhöhen (Semantic Versioning: Fehlerbehebung = x.y.Z, neue Funktion = x.Y.0, inkompatibel = X.0.0)
