# Changelog

## 1.2.1 – 2026-11-01

### Behoben
- b83-Übersicht: Plugin-Icons erschienen als defekte Bilder
- Icons von Pro-Versionen fehlten im b83-Menü (b83-Menü Version 3)

## 1.2.0 – 2026-11-01

### Neu
- b83-Menü (Version 2): Übersicht und Menüpunkte zeigen das Icon jedes b83-Plugins

## 1.1.0 – 2026-11-01

### Neu
- Eigenes Icon und Banner im b83-Stil: erscheinen in der Update-Liste und unter „Details ansehen“
- „Details ansehen“ zeigt jetzt eine Beschreibung des Plugins

## 1.0.0 – 2026-10-21

- Erste Version
- Wettervorhersage aus offenen Daten: GeoSphere Austria (Hauptquelle) und MET Norway
- Fallback auf die zweite Quelle bei Ausfall; Tage über die Reichweite von GeoSphere hinaus werden von MET Norway ergänzt
- Drei Darstellungen: klein (einzeilig, z. B. neben der Navigation), kompakt (Kopfzeile, Sidebar), ausführlich (Wetterseite)
- Vier Icon-Sets (farbig, Linie, Duoton, flach) als eigene Inline-SVGs
- Design im Backend: Text-, Neben-, Hintergrund-, Rahmen- und Akzentfarbe, Schrift- und Icon-Größen je Darstellung, Live-Vorschau hell/dunkel
- Shortcode `[b83_wetter]` mit kurzen Attributen (farbe, hintergrund, schrift …) und Elementor-Widget „b83 Wetter“
- Abruf nur über den Server per WP-Cron, keine Besucher-IPs an Dritte; veraltete Widgets aus dem Seiten-Cache laden aktuelle Werte über die eigene Website nach
- Quellenangabe laut CC BY 4.0, Status-Seite mit Kontrolltabelle, Ortssuche, Updates über GitHub-Releases
