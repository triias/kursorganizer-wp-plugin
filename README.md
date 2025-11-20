# KursOrganizer X iFrame Integration
Contributors: KursOrganizer GmbH
Tags: iframe, courses, organization
Requires at least: 5.0
Tested up to: 8.0
Stable tag: 1.0
License: GPL2

Integration des KursOrganizer WebModuls in WordPress Seiten.

## Description
Fügt einen Shortcode hinzu, um das WebModul des KursOrganizer auf der Wordpressseite zu integrieren.

## Shortcode Verwendung
Der Shortcode [kursorganizer_iframe] kann mit folgenden Parametern verwendet werden:

* city - Stadt/Ort Filter
* instructorid - Filtert nach einem bestimmten Kursleiter
* coursetypeid - Filtert nach einem bestimmten Kurstyp
* coursetypeids - Filtert nach mehreren Kurstypen (kommagetrennt)
* locationid - Filtert nach einem bestimmten Standort
* dayfilter - Filtert nach bestimmten Tagen
* coursecategoryid - Filtert nach einer bestimmten Kurskategorie
* showfiltermenu - Zeigt/versteckt das Filtermenü (true/false, Standard: true)

Beispiel:
[kursorganizer_iframe city="Berlin" coursetypeid="123" showfiltermenu="false"]

## CSS-Anpassungen

Das Plugin unterstützt benutzerdefinierte CSS-Anpassungen für den Inhalt des iFrames. Sie können CSS auf zwei Arten hinzufügen:

### 1. Direktes CSS-Eingeben

Geben Sie CSS-Code direkt in das Textfeld "Benutzerdefiniertes CSS" in den Plugin-Einstellungen ein. Das CSS wird automatisch base64-kodiert und als URL-Parameter an die App übergeben.

**Beispiel CSS:**

```css
.ant-btn-primary {
    background-color: #ff0000;
    border-color: #ff0000;
}
.ant-card {
    border-radius: 10px;
}
```

### 2. Externe CSS-Datei

Geben Sie die vollständige URL zu einer externen CSS-Datei im Feld "CSS-Datei URL" ein. Die CSS-Datei muss öffentlich zugänglich sein und CORS-Header erlauben.

**Beispiel URL:**

```
https://www.fitimwasser.de/wp-content/themes/theme-name/custom-kursorganizer.css
```

### Wichtige Hinweise

* **Priorität:** Wenn sowohl CSS-URL als auch CSS-Text angegeben sind, hat die CSS-URL Priorität
* **CSS-Spezifität:** Verwenden Sie ausreichend spezifische Selektoren, um die Standard-Styles zu überschreiben
* **Ant Design Klassen:** Die App verwendet Ant Design. Sie können Ant Design Komponenten-Klassen direkt stylen (z.B. `.ant-btn-primary`, `.ant-card`, `.ant-table`)
* **Sicherheit:** Das CSS wird sanitized, um XSS-Angriffe zu verhindern
* **Performance:** Große CSS-Dateien können die Ladezeit beeinträchtigen

### CSS-Beispiele

**Schriftarten anpassen:**

```css
body {
    font-family: 'Arial', 'Helvetica Neue', sans-serif !important;
    font-size: 16px;
}
/* Alle Elemente mit Schriftart versehen */
* {
    font-family: 'Arial', 'Helvetica Neue', sans-serif !important;
}
```

**Hinweis:** Verwenden Sie `!important`, um sicherzustellen, dass die Schriftart auch auf alle Ant Design Komponenten angewendet wird.

**Farben anpassen:**

```css
.ant-btn-primary {
    background-color: #your-color;
    border-color: #your-color;
}
```

**Abstände anpassen:**

```css
.ant-card {
    margin-bottom: 20px;
    padding: 15px;
}
```

## Installation in WordPress:
- Download the latest release ZIP from this GitHub repository

### In WordPress admin panel:

- Go to Plugins → Add New → Upload Plugin
- Choose the downloaded ZIP file
- Click "Install Now" and then "Activate"

### Configure the plugin:

- Go to "KursOrganizer X" in the WordPress admin menu
- Enter your KursOrganizer Web-App URL
- Add your GitHub token for automatic updates (optional)
- Save settings
- Use the shortcode in your pages/posts:

## Changelog
= 1.0 =
* Initial release
