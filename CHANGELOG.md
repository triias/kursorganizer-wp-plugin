# Changelog

## [1.2.5] - 2026-05-07
### Fixed
- Fehler „Aktualisierung fehlgeschlagen: Es wurde kein Plugin angegeben" beim Klick auf den „Jetzt aktualisieren"-Button im Plugin-Information-Popup behoben. Der Updater fuellt das WP-Update-Transient jetzt mit den Pflichtfeldern `plugin` (Plugin-Datei-Pfad) und `slug` (Verzeichnisname) statt nur `slug` (Pfad), damit WordPress' AJAX-Update-Endpoint die richtige Plugin-Datei findet.
- `plugin_popup()` akzeptiert jetzt sowohl den Verzeichnis-Slug als auch den vollen Plugin-Pfad als `args->slug`, damit Updates aus aelteren Cache-States robust durchlaufen.

## [1.2.4] - 2026-05-07
### Fixed
- PHP-Notices `Undefined property: stdClass::$description` im Plugin-Information-Popup behoben (GitHub-Releases-API liefert `body`, nicht `description`).
- Update-Popup zeigt jetzt die echte Plugin-Beschreibung aus dem Plugin-Header statt einer leeren Section.
- Changelog-Tab im Update-Popup zeigt jetzt die Release-Notes aus dem GitHub-Release statt einer 404-Antwort.

### Added
- Plugin-Icon (KursOrganizer-Logo auf Markenfarbe) in Update-Liste und Plugin-Information-Popup. Wird via `assets/icons/icon.svg` ausgeliefert.
- Plugin-Header ergänzt um `Requires at least`, `Requires PHP` und `Tested up to`, damit WordPress Kompatibilität korrekt anzeigt.
- Beschreibungstext im Plugin-Header sprachlich korrigiert.

## [1.2.3] - 2026-05-07
### Fixed
- iFrame wird auf WordPress-Seiten mit boldthemes-basierten Themes (z. B. Industrial, Construction, Architect) nicht mehr fälschlicherweise auf 16:9-Seitenverhältnis gestaucht. Das `boldthemes_video_resize()` aus dem Theme-Framework hat den iFrameResizer bei jedem `window.resize`-Event überschrieben — sichtbar besonders auf iOS beim Scrollen (URL-Leiste klappt ein/aus) und am Desktop beim Ändern der Fensterbreite.

### Added
- Offizielle boldthemes-Opt-out-Klasse `bt_skip_resize` wird automatisch an das generierte iFrame angehängt. Auf Themes ohne diesen Mechanismus ist die Klasse wirkungslos.
- Neuer WordPress-Filter `kursorganizer_iframe_classes` zum Anpassen der iFrame-Klassenliste (z. B. zum Entfernen von `bt_skip_resize` oder Hinzufügen weiterer Theme-Compat-Klassen).

## [1.2.2] - 2025-12-10
### Changed
- Visuelle Anpassungen für die Einstellungsseite

## [1.2.1] - 2025-12-10
### Fixed
- JavaScript stellt sicher, dass der Button korrekt aktiviert wird, wenn URL und Organization ID gesetzt sind

## [1.2.0] - 2025-12-10
### Added
- Organization ID Validierung zur Sicherstellung, dass nur die korrekte Schwimmschule eingebunden wird
- Initial Setup Willkommensnachricht und Schritt-für-Schritt Anleitung beim ersten Öffnen des Plugins
- Test Connection AJAX-basierter Button zum Testen der Verbindung zwischen URL und Organization ID ohne Speichern
- URL-Automatisches Hinzufügen von `/build` bei kursorganizer.com Domains, falls es fehlt
- "Initiales Setup" Abschnitt im Anleitungen-Tab mit detaillierter Schritt-für-Schritt Anleitung

### Changed
- Plugin-Funktionalität wird blockiert, bis URL und Organization ID korrekt konfiguriert und validiert sind
- Verbesserte, benutzerfreundliche Fehlermeldungen statt technischer API-Fehlermeldungen
- Fehlerhafte Felder werden rot markiert und automatisch wieder normal dargestellt bei Eingabe
- URLs müssen auf `/build` enden (automatische Korrektur für kursorganizer.com Domains)
- Felder werden erst nach erfolgreicher Konfiguration freigeschaltet

### Fixed
- Unendliche Rekursion bei der Validierung wurde behoben
- Fehlermeldungen zeigen keine internen API-Details mehr an
- URL-Feld wird korrekt als fehlerhaft markiert, wenn die URL ungültig ist

### Removed
- GitHub Access Token Feld temporär ausgeblendet (optional für öffentliche Repositories)

## [1.1.0] - 2025-11-27
### Added
- Changelog-Tab zur Einstellungsseite hinzugefügt
- Versionsnummer wird nun im Header der Einstellungsseite angezeigt
- Admin-CSS-Datei für anpassbare Breite der Einstellungsseite
- Neuer Tab "Anleitungen" mit Shortcode-Beispielen und CSS-Anpassungsanleitung
- Automatische Markdown-zu-HTML-Konvertierung für Changelog-Anzeige

### Changed
- Einstellungsseite umstrukturiert: Shortcode-Beispiele und CSS-Anpassungen in eigenen Tab verschoben
- Einstellungen-Tab fokussiert sich jetzt nur noch auf die Plugin-Konfiguration
- Maximale Breite der Einstellungsseite von 800px auf 1200px erhöht (anpassbar über CSS)
- Verbesserte Navigation durch klarere Tab-Struktur

### Fixed
- N/A

### Removed
- N/A

## [1.0.5] - 2025-11-18
### Added
- CSS-Anpassungen über externe CSS-Datei-URL hinzugefügt
- Neue Settings-Section "CSS-Anpassungen" für die Konfiguration externer CSS-Dateien
- Validierung und Sanitization für CSS-URLs

### Changed
- CSS-Anpassungen erfolgen ausschließlich über externe CSS-Datei-URLs
- Vereinfachte CSS-Konfiguration: Direktes CSS-Eingeben wurde entfernt

### Removed
- Direktes CSS-Eingabefeld aus den Einstellungen entfernt
- Base64-Kodierung für CSS-Text entfernt (nur noch URL-basierte CSS-Dateien)

## [1.0.4] - 2025-11-04
### Fixed
- Mehrfache Verwendung des Shortcodes auf einer Seite funktioniert jetzt korrekt
- iFrame-Resizing funktioniert nun für alle iFrames, nicht nur für den ersten
- Eindeutige IDs für jeden iFrame zur Vermeidung von HTML-Validierungsfehlern

### Changed
- iFrameResizer verwendet jetzt CSS-Klassen statt IDs für bessere Mehrfachnutzung
- Debug-Callbacks verwenden nun eindeutige IDs pro iFrame-Instanz

## [1.0.0] - 2024-11-27
### Added
- Initial release of the KursOrganizer X iFrame plugin.
- Added shortcode `[kursorganizer_iframe]` to embed the KursOrganizer web module in WordPress pages.
- Implemented settings page for configuring the plugin.
- Added support for filtering courses by city, instructor, course type, location, day, and category.
- Included debug mode for displaying technical information under the iFrame.

### Fixed
- N/A

### Changed
- N/A

### Removed
- N/A