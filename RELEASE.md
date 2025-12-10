# Release-Anleitung für KursOrganizer WordPress Plugin

## Automatisches Release über GitHub Actions

Das Plugin verwendet GitHub Actions, um automatisch Releases zu erstellen, wenn ein neuer Tag gepusht wird.

### Schritt-für-Schritt Anleitung:

1. **Versionsnummer aktualisieren**
   - In `kursorganizer-wp-plugin.php` die Versionsnummer aktualisieren:
     - Plugin Header: `Version: 1.2.0`
     - Konstante: `define('KURSORGANIZER_VERSION', '1.2.0');`

2. **Changelog aktualisieren**
   - In `CHANGELOG.md` den neuen Eintrag für Version 1.2.0 hinzufügen
   - Datum auf aktuelles Datum setzen

3. **Änderungen committen und pushen**
   ```bash
   git add .
   git commit -m "Update to version 1.2.0"
   git push origin main
   ```

4. **Git Tag erstellen und pushen**
   ```bash
   git tag -a v1.2.0 -m "Release version 1.2.0"
   git push origin v1.2.0
   ```

5. **GitHub Actions erstellt automatisch das Release**
   - Gehe zu: https://github.com/triias/kursorganizer-wp-plugin/actions
   - Der Workflow wird automatisch ausgelöst
   - Ein neues Release wird erstellt mit:
     - Tag: `v1.2.0`
     - Release Name: `Release v1.2.0`
     - Release Notes: Inhalt aus `CHANGELOG.md`

## Manuelles Release erstellen

Falls der automatische Workflow nicht funktioniert:

1. Gehe zu: https://github.com/triias/kursorganizer-wp-plugin/releases/new
2. Wähle "Choose a tag" und erstelle einen neuen Tag: `v1.2.0`
3. Release Title: `Version 1.2.0`
4. Beschreibung: Kopiere den entsprechenden Abschnitt aus `CHANGELOG.md`
5. Klicke auf "Publish release"

## Wie Kunden Updates erhalten

### Automatische Updates (empfohlen)

Das Plugin prüft automatisch auf Updates über GitHub Releases:

1. **Automatische Prüfung**: WordPress prüft regelmäßig auf Updates
2. **Benachrichtigung**: Wenn ein Update verfügbar ist, erscheint eine Benachrichtigung im WordPress Admin
3. **Update-Installation**: 
   - Gehe zu: WordPress Admin → Plugins
   - Klicke auf "Jetzt aktualisieren" beim KursOrganizer Plugin
   - Oder: WordPress Admin → Dashboard → Updates

### Manuelle Installation

Falls automatische Updates nicht funktionieren:

1. Lade das Release-ZIP von GitHub herunter:
   - Gehe zu: https://github.com/triias/kursorganizer-wp-plugin/releases
   - Lade die neueste Version herunter (z.B. `Source code (zip)`)

2. Installiere das Update:
   - Gehe zu: WordPress Admin → Plugins
   - Deaktiviere das alte Plugin
   - Lösche das alte Plugin (Einstellungen bleiben erhalten)
   - Installiere das neue Plugin über "Plugin hochladen"
   - Aktiviere das Plugin

## Wichtige Hinweise

- **Tag-Format**: Tags müssen mit `v` beginnen (z.B. `v1.2.0`)
- **Versionsnummer**: Die Versionsnummer im Plugin muss mit dem Tag übereinstimmen (ohne `v`)
- **Changelog**: Der Changelog wird automatisch in die Release-Beschreibung eingefügt
- **Backup**: Vor jedem Update sollte ein Backup erstellt werden

## Troubleshooting

### Updates werden nicht angezeigt

1. Prüfe, ob ein GitHub Release existiert: https://github.com/triias/kursorganizer-wp-plugin/releases
2. Prüfe, ob der Tag korrekt ist (muss mit `v` beginnen)
3. Prüfe, ob die Versionsnummer im Plugin höher ist als die aktuelle Version
4. Versuche, den WordPress Cache zu leeren
5. Prüfe die WordPress Debug-Logs auf Fehler

### GitHub Token (optional)

Für private Repositories oder höhere Rate Limits kann ein GitHub Personal Access Token verwendet werden:
- Gehe zu: WordPress Admin → KursOrganizer X → Einstellungen
- Trage das Token im Feld "GitHub Access Token" ein (aktuell ausgeblendet)

