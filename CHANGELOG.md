# Changelog

## [1.0.5] - 2025-01-XX
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