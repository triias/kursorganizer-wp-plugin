=== KursOrganizer X iFrame ===
Contributors: KursOrganizer GmbH
Tags: iframe, courses, kursorganizer, integration
Requires at least: 5.0
Tested up to: 7.0
Stable tag: 1.6.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Integrieren Sie Ihre KursOrganizer Web-App nahtlos in Ihre WordPress-Seite per Shortcode.

== Beschreibung ==

Das KursOrganizer X Plugin ermöglicht es Ihnen, Ihre KursOrganizer Web-App (Kurssuche, Buchungssystem) direkt in Ihre WordPress-Seiten einzubinden.

**Wichtige Informationen für die Installation:**

Für die Einrichtung des Plugins benötigen Sie nur einen Wert von KursOrganizer:

**KursOrganizer Web-App URL**
   Die Adresse Ihrer persönlichen KursOrganizer Web-App
   Beispiel: app.ihrefirma.kursorganizer.com

**Falls Ihnen die Adresse nicht bekannt ist, wenden Sie sich bitte an:**
support@kursorganizer.com

== Installation ==

**1. Plugin installieren**

   a) Melden Sie sich in Ihrem WordPress-Admin-Bereich an
   b) Gehen Sie zu: Plugins → Plugin hochladen
   c) Klicken Sie auf "Datei auswählen" und wählen Sie die ZIP-Datei aus
   d) Klicken Sie auf "Jetzt installieren"
   e) Klicken Sie auf "Plugin aktivieren"

**2. Plugin konfigurieren**

Nach der Aktivierung werden Sie zum KursOrganizer X Einstellungsbereich weitergeleitet:

   a) Geben Sie Ihre **KursOrganizer Web-App URL** ein
      (z.B. app.ihrefirma.kursorganizer.com; https:// und /build/ ergänzt das Plugin selbst)

   b) Klicken Sie auf **"Speichern"**

   c) Unter dem Feld steht danach **"Verbunden mit: …"** mit dem Namen Ihrer Schwimmschule

**3. Shortcode verwenden**

Nach erfolgreicher Konfiguration können Sie den Shortcode verwenden:

   a) Erstellen oder bearbeiten Sie eine WordPress-Seite oder einen Beitrag
   b) Fügen Sie den Shortcode ein: [kursorganizer_iframe]
   c) Veröffentlichen Sie die Seite

Alternativ können Sie den **Shortcode Generator** im Plugin verwenden:
   - Gehen Sie zu: KursOrganizer X → Shortcode Generator
   - Wählen Sie Filter (z.B. Standort, Kurstyp) aus
   - Klicken Sie auf "Shortcode generieren"
   - Kopieren Sie den generierten Shortcode

== Häufig verwendete Shortcodes ==

**Standardansicht des Web-Moduls anzeigen:**
[kursorganizer_iframe]

**Kurse und Interessentenlisten anzeigen:**
[kursorganizer_iframe listtype="all"]

**Nur Interessentenlisten anzeigen:**
[kursorganizer_iframe listtype="interest"]

**Nur Kurse anzeigen:**
[kursorganizer_iframe listtype="courses"]

**Kurse einer bestimmten Stadt:**
[kursorganizer_iframe city="Berlin"]

**Kurse eines bestimmten Standorts:**
[kursorganizer_iframe locationid="standort-id"]

**Bestimmte Kurstypen:**
[kursorganizer_iframe coursetypeids="id1,id2,id3"]

Weitere Beispiele und Optionen finden Sie im Tab "Anleitungen" im Plugin.

== Wichtige Hinweise ==

* **Verbindung**: Die Web-App URL bestimmt, welche Schwimmschule eingebunden wird. Nach dem Speichern zeigt das Plugin deren Namen an
* **Support**: Bei Fragen oder Problemen wenden Sie sich an support@kursorganizer.com
* **Eigenes CSS**: Unter „CSS-Anpassungen“ können Sie eigenes CSS für das Web Modul hinterlegen. Schreiben Sie es im iFrame-Stil (`.ant-…`, `#content`, `#footer`); es wirkt im iFrame und im Embed ohne iFrame, die Selektoren werden für den Embed automatisch übersetzt

== Changelog ==

= 1.6.0 =
* Einrichtung mit nur noch einem Wert: Die Organization ID und „Verbindung testen“ entfallen, die Web-App URL genügt (auch ohne `https://` und `/build/`); nach dem Speichern zeigt das Plugin den Namen der verbundenen Schwimmschule

= 1.5.0 =
* Embed ohne iFrame mehrfach auf einer Seite: jeder Shortcode im Embed-Modus baut ein eigenes Web Modul ein (zum Beispiel drei Standorte untereinander); mit älterer Web-App bleibt es beim ersten Embed plus iFrames

= 1.4.0 =
* Neues Feld „Eigenes CSS für das Web Modul“, wirkt im iFrame (Route `/wp-json/kursorganizer/v1/custom-css`) und im Embed ohne iFrame (übersetzte Selektoren als Inline-Style)

= 1.2.9 =
* Versionsdetails-Popup für historische Installationsordner wie `kursorganizer-wp-plugin-main` repariert
* Popup verwendet bei temporären GitHub-Fehlern die bereits zwischengespeicherten WordPress-Update-Daten

= 1.2.8 =
* Auswahl zwischen Kursen und Interessentenlisten per `listtype`-Shortcode-Attribut
* Angebotsauswahl im Shortcode-Generator ergänzt
* Ohne `listtype` gilt weiterhin die Mandanteneinstellung des Web-Moduls

= 1.2.7 =
* Zu spaete Updater-Registrierung fuer Admin- und Cron-Pruefungen behoben
* Alte GitHub-Tokens werden fuer das oeffentliche Release-Repository nicht mehr verwendet
* Updates aus historischen `kursorganizer-wp-plugin-main`-Installationen abgesichert
* Installations-Hook auf KursOrganizer-Updates begrenzt

= 1.2.6 =
* Sicherheitsfix für Shortcode-Parameter und iFrame-Ausgabe
* Organization-ID-Prüfung aus öffentlichen Seitenaufrufen entfernt und als tägliche Hintergrundprüfung umgesetzt
* API-Logging reduziert und iFrame-Resizer nur noch bei tatsächlicher Shortcode-Nutzung geladen
* Bereinigtes Plugin-ZIP als GitHub-Release-Asset

= 1.2.3 =
* Fix: iFrame wird auf WordPress-Seiten mit boldthemes-basierten Themes (Industrial, Construction, Architect) nicht mehr fälschlicherweise auf 16:9-Seitenverhältnis gestaucht (sichtbar besonders auf iOS beim Scrollen und am Desktop beim Resizen)
* Offizielle boldthemes-Opt-out-Klasse `bt_skip_resize` wird automatisch an das iFrame angehängt; auf anderen Themes wirkungslos
* Neuer WordPress-Filter `kursorganizer_iframe_classes` zum Anpassen der iFrame-Klassenliste

= 1.2.2 =
* Visuelle Anpassungen für die Einstellungsseite
* Tab-Navigation hat jetzt die gleiche Breite wie die Content-Panels

= 1.2.1 =
* JavaScript stellt sicher, dass der Speichern-Button korrekt aktiviert wird

= 1.2.0 =
* Sicherheitsvalidierung mit Organization ID
* Initial Setup Flow mit Willkommensnachricht
* Test Connection Button
* Automatisches Hinzufügen von /build bei URLs
* Verbesserte Fehlerbehandlung

= 1.1.0 =
* Changelog-Tab zur Einstellungsseite hinzugefügt
* Neuer Tab "Anleitungen" mit Shortcode-Beispielen
* Verbesserte Navigation

= 1.0.5 =
* CSS-Anpassungen über externe CSS-Datei-URL
* Verbesserte CSS-Konfiguration

= 1.0.4 =
* Mehrfache Verwendung des Shortcodes auf einer Seite funktioniert korrekt
* iFrame-Resizing verbessert

= 1.0.0 =
* Erste Veröffentlichung

== Support ==

Bei Fragen oder Problemen kontaktieren Sie uns:
* E-Mail: support@kursorganizer.com
* Website: https://kursorganizer.com

== Screenshots ==

Das Plugin bietet:
* Einfache Einrichtung mit Schritt-für-Schritt Anleitung
* Shortcode Generator für individuelle Konfigurationen
* Detaillierte Anleitungen und Beispiele
* Test-Funktion zur Validierung der Einstellungen
