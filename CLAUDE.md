# b83 Wetter – Projektregeln für Claude Code

> Diese Datei liest Claude Code bei jedem Start. Sie gilt für alle im Team.
> Persönliche Notizen gehören in `CLAUDE.local.md` (wird nicht eingecheckt).

## Über dieses Plugin

- **Name:** b83 Wetter
- **Slug / Text-Domain:** `b83-wetter`
- **Präfix für Funktionen, Optionen, Hooks:** `b83_wetter_` (Namespace: `B83\Wetter`)
- **Repository:** github.com/b83-Strategisches-Online-Marketing/b83-wetter (öffentlich)
- **Vertrieb:** intern, Updates über GitHub-Releases (öffentliches Repository)
- **Im Einsatz auf:** mehrere Kundenseiten
- **Zweck in einem Satz:** Wettervorhersage aus offenen Daten (GeoSphere Austria, MET Norway) mit drei Darstellungen – klein, kompakt und ausführlich – per Shortcode oder Elementor-Widget.

## b83-Standards (gelten für alle b83-Plugins)

### Plugin-Header
```
Author:            b83 – Wolfgang Grabs-Schrempf
Author URI:        https://b83.at
Plugin URI:        https://b83.at
Requires PHP:      8.1
Requires at least: 6.2
Text Domain:       b83-wetter
License:           GPL-2.0-or-later
Update URI:        https://github.com/b83-Strategisches-Online-Marketing/b83-wetter   (nicht bei Freemius-Plugins)
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
- Eigene Actions und Filter mit Präfix an sinnvollen Stellen anbieten und im README dokumentieren.
- CSS/JS als eigene Dateien, kein Inline-Code außer kleiner Konfiguration (`wp_localize_script`).

### Struktur
- Eine Zuständigkeit → eine Datei reicht.
- Zwei bis drei Bereiche → `includes/` mit einer Klasse pro Bereich.
- Vier oder mehr → tiefere Ordnerstruktur mit Autoloader.
- Assets in `assets/css`, `assets/js`, `assets/img`. Sprachdateien in `languages/`.

**So sind die bestehenden b83-Plugins aufgebaut** (bei neuen Plugins übernehmen):
- Hauptdatei nur Header, Konstanten (`B83_<KÜRZEL>_VERSION`, `_FILE`, `_DIR`, `_URL`), PSR-4-Autoloader auf `src/` und Bootstrap.
- Logik in `src/`, Backend in `src/Admin/`, Ausgabe in `src/Frontend/`, Fremd-Plugins in `src/Integrations/`, Datenbank-Version als Option und Migrationen in `src/Migration.php`.
- `src/Admin/B83Menu.php` ist eine Kopie der gemeinsamen Menüklasse (nur Namespace und Text Domain angepasst), dazu `assets/img/b83-logo.svg`, `assets/img/b83-menu-icon.svg`, `assets/css/b83-menu.css`. Eigene Seite mit `add_submenu_page( B83Menu::SLUG, … )`, Link über den Filter `b83_admin_plugin_links`. Wird die Klasse geändert, `MENU_VERSION` erhöhen und in alle Plugins übernehmen: Die neueste Version auf einer Website baut das Menü.
- Eigene `Updater`-Klasse (Filter `update_plugins_github.com`). `.github/workflows/release.yml` baut das Release automatisch, sobald eine neue Version auf `main` liegt (Freemius-Plugins: Upload zu Freemius).
- Im Repo: `README.md`, `CHANGELOG.md`, `SECURITY.md` (Meldeweg für Lücken), `.github/dependabot.yml`.
- `uninstall.php` löscht Daten nur, wenn das in den Einstellungen aktiviert ist.
- Keine Daten im Plugin-Ordner speichern, er wird bei Updates ersetzt.

### Sicherheit (nicht verhandelbar)
- Jede Formular- und AJAX-Aktion: Nonce prüfen **und** `current_user_can()`.
- Eingaben: `wp_unslash`, dann zentral validieren (Whitelist statt Blacklist), Längen begrenzen, mit `sanitize_*` bereinigen.
- Ausgaben spät escapen mit `esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`.
- Datenbank nur über `$wpdb->prepare()`. REST-Routen immer mit `permission_callback`.
- Uploads: `is_uploaded_file`, Größe und Format prüfen.
- Hinweise per festem Schlüssel in der URL, nie Freitext aus der URL ausgeben.
- AJAX: vor `wp_send_json_*()` den Output-Buffer leeren (`ob_clean()` falls aktiv), sonst kaputtes JSON.
- Keine Secrets im Code. Tokens kommen aus `wp-config.php` (z. B. `B83_GITHUB_TOKEN`).

### Updates und Lizenz
- Öffentliche Repos: Updates direkt über GitHub-Releases.
- Private Repos: Update-Abfrage mit `B83_GITHUB_TOKEN` aus der `wp-config.php` (Fine-grained Token, nur Lesen).
- Das Release-Asset heißt immer `<slug>.zip`, ohne Versionsnummer. Download-Link für andere: `https://github.com/b83-Strategisches-Online-Marketing/<slug>/releases/latest/download/<slug>.zip`. Nie „Code → Download ZIP“ weitergeben: Das erzeugt einen Ordner `<slug>-main`, und Updates greifen danach nicht mehr.
- Freemius-Plugins: SDK im Ordner `freemius/` (bzw. `vendor/freemius/`), nicht verändern. Freemius-Secrets nie in öffentliche Repos.
  - Pro-Code nur in eigenen `if ( …_fs()->can_use_premium_code__premium_only() ) { … }`-Blöcken, nie mit anderen Bedingungen kombiniert oder einer Variablen zugewiesen. Sonst erzeugt Freemius die Gratisversion nicht sauber. Gratis-Code verwendet keine Klassen aus `src/Pro/`.
  - Neue Versionen bleiben bei Freemius auf „pending“. Wolfgang gibt jede Version selbst frei. Nach jedem Release daran erinnern.
  - Plugin Check nur mit der Gratisversion ausführen. Die Pro-Version liegt im Ordner `<slug>-premium` und erzeugt falsche Meldungen (Text Domain, Update URI, Name).
  - Die Kundenseiten von b83 laufen mit einer eigenen unbegrenzten Pro-Lizenz.

### Sprache und Texte
- **Interne Plugins:** Oberfläche und Code-Kommentare auf Deutsch. Englisch kommt bei Bedarf über `languages/`.
- **Verkaufs-Plugins:** Oberfläche (Quellsprache) und Code-Kommentare auf Englisch, Deutsch über `languages/` (de_DE, de_AT, de_CH). Wird ein internes Plugin zum Verkaufs-Plugin, vorher mit `/uebersetzen` umstellen.
- Bezeichner (Klassen, Funktionen, Variablen) immer englisch.
- Oberfläche immer übersetzbar (`__()`, `esc_html__()` usw.).
- Beschreibungen, readme.txt, Doku und Marketingtexte ohne typische KI-Schreibmuster formulieren (Humanizer-Regeln): keine Floskeln, keine Übertreibungen, keine Gedankenstrich-Ketten.
- Kein „DSGVO-konform“ in Beschreibungen oder Slogans. Ob eine Seite rechtskonform ist, hängt von ihrer Konfiguration ab.

## Erfahrungen aus allen b83-Plugins

- **Theme- und Elementor-CSS überschreibt Plugin-Stile.** Elementor-Kit-Regeln (`.elementor-kit-N a { color: … }`) und Theme-Linkfarben haben Buttons in mehreren Plugins umgefärbt. Deshalb: Selektoren unter eigener ID scopen oder Klassen verdoppeln (`.b83-x.b83-x`), Farben über CSS-Variablen setzen und bei den eigentlichen Farbeigenschaften `!important` verwenden. Alle Zustände (hover, focus, visited) abdecken.
- **b83-Orange:** `#d96f21` auf Weiß erfüllt WCAG AA nicht. Für Text und Buttons `#b85a12` verwenden.
- **Seiten-Cache:** WP Rocket läuft auf vielen Kundenseiten. Zeitabhängige Anzeigen (Zeitfenster, Öffnungszeiten, veraltete Daten) zusätzlich im Browser prüfen, nicht nur beim Rendern. Nach dem Speichern den Cache der betroffenen Seiten leeren. Bei WP Rocket die nötigen Ausnahmen (z. B. „JavaScript verzögern“, LazyLoad) per Filter setzen.
- **Externe Dienste:** Nur serverseitig abrufen und zwischenspeichern, damit keine Besucher-IP beim Dienst landet. Bibliotheken, Icons und Schriften lokal mitliefern, kein CDN und keine Google Fonts.
- **B83Menu:** Pro-Versionen von Freemius liegen in einem anderen Ordner (`<slug>-premium`). Plugins deshalb nie am Ordnernamen erkennen. Fehlt das b83-Menü oder ist es doppelt, ist die Ursache meist die White-Label-Funktion von Astra Pro mit demselben Slug `b83` (z. B. swstb.at). Das ist kein Fehler im Plugin, dort die Astra-Einstellung ändern.
- **Elementor speichert Pfade mit Backslashes** (`plugin\/assets\/…`). Beim Suchen und Ersetzen in der Datenbank nur nach dem Dateinamen suchen.
- **Tests:** Elementor ließ sich in früheren Testumgebungen meist nicht installieren. Immer offen sagen, was nur simuliert getestet wurde und was nicht in echtem WordPress lief.

## Arbeitsweise

- Nur die Dateien lesen, die für die Aufgabe nötig sind. `freemius/`, `vendor/` und Build-Ordner nie durchsuchen.
- Vor größeren Änderungen kurz den Plan nennen, dann umsetzen.
- Sagt Wolfgang „nur meine Frage“ oder „noch nichts ändern“, wird zuerst besprochen und nichts umgesetzt.
- Nach jeder Änderung: `php -l` auf geänderte Dateien.
- Bestehende Shortcodes, Optionen und Hooks bleiben abwärtskompatibel, außer es ist ausdrücklich anders entschieden.
- Bei Reviews trennen: **kritisch** (Sicherheit, Datenverlust, Fatal Errors) vs. **sollte** vs. **wäre schön**.
- Versionsnummer nur beim Release erhöhen (siehe `/release`). Version in Header, Versionskonstante, `CHANGELOG.md` und `readme.txt` gleich halten.
- Ein erfolgreich gebautes Release erledigt keine offenen Folgeaufgaben (wordpress.org-Assets, Freemius-Freigabe, Einstellungen auf Kundenseiten). Diese getrennt nachverfolgen.
- Dependabot-PRs nur nach ausdrücklichem OK mergen.
- Commit-Nachrichten auf Deutsch, kurz, im Präsens („Fügt Ticker-Tempo hinzu“).

## Offene Ideen für alle Plugins

- Slogan „Made in Austria“ als Eintrag in der Meta-Zeile der Plugin-Liste (`plugin_row_meta`), zentral über B83Menu mit neuer `MENU_VERSION`. Wortlaut noch offen.
- Icons und Banner aus `.wordpress-org/` nach der Freigabe eines Plugins per SVN in `/assets` auf wordpress.org hochladen (braucht Wolfgangs SVN-Zugang).

## Entscheidungen in diesem Plugin

> Hier festhalten, was entschieden wurde, damit niemand es neu diskutiert.
> Wo eine Entscheidung von den b83-Standards oben abweicht, gilt für dieses Plugin die Entscheidung.

### Abweichungen von den b83-Standards (bewusst so, nichts umbenennen)
- **Optionen und Bezeichner bleiben unverändert:**
  - Option `b83_wetter_settings`, dazu `b83_wetter_data`, `b83_wetter_status`, `b83_wetter_db_version`
  - Shortcode `[b83_wetter]`, REST-Namespace `b83-wetter/v1`, Admin-Slug `b83-wetter`
  - Cron-Hooks `b83_wetter_refresh` und `b83_wetter_refresh_async`
- **REST-Route:** `permission_callback` ist `__return_true`, weil sie öffentliche Wetterdaten liefert.
- **`class_exists`:** nur für `\Elementor\...` (optionale Integration), keine Guards für eigene Klassen.
- **Verkaufskandidat:** Bis dahin Oberfläche und Kommentare Deutsch, Englisch (en_US, en_GB) über `languages/`. Vor einem Verkauf auf Englisch umstellen. Einschränkung für den Verkauf: GeoSphere deckt nur Österreich und den Alpenraum ab.

### Entscheidungen
- Datenquellen: GeoSphere Austria als Hauptquelle, MET Norway als Fallback und für Tage nach rund 2,5 Tagen, beide CC BY 4.0 mit Pflicht zur Quellenangabe. Open-Meteo verworfen, weil der Gratis-Tarif nicht kommerziell genutzt werden darf.
- Bewusst nur ein fester Standort. Die Stundenansicht kommt später.
- Drei Darstellungen nach Wolfgangs Wunsch: klein (einzeilig neben der Navigation), kompakt (Kopfzeile, Sidebar), ausführlich (Wetterseite), jeweils frei in Farben und Schriftgrößen.
- Mehrsprachigkeit: WPML übersetzt keine Plugin-Texte. Deshalb liegen en_US und en_GB bei, das Widget folgt der Sprache der Seite (auch beim Nachladen über REST). Der Ortsname lässt sich in WPML → String-Übersetzung übersetzen. Nach einem Update den Seiten-Cache leeren.

### Bekannte Probleme
- Auf karkogel.abtenau-info.at wird oft MET Norway verwendet, obwohl GeoSphere als Hauptquelle eingestellt ist. Die Ursache ist nicht geklärt. Erster Blick: b83 → Wetter → Status.
- Das Elementor-Widget wurde nie mit echtem Elementor getestet.
