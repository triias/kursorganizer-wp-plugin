=== KursOrganizer X iFrame ===
Contributors: KursOrganizer GmbH
Tags: iframe, courses, kursorganizer, integration
Requires at least: 5.0
Tested up to: 6.9
Stable tag: 1.2.2
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Integrieren Sie Ihre KursOrganizer Web-App nahtlos in Ihre WordPress-Seite per Shortcode.

== Beschreibung ==

Das KursOrganizer X Plugin ermöglicht es Ihnen, Ihre KursOrganizer Web-App (Kurssuche, Buchungssystem) direkt in Ihre WordPress-Seiten einzubinden.

**Wichtige Informationen für die Installation:**

Für die Einrichtung des Plugins benötigen Sie folgende Informationen von KursOrganizer:

1. **KursOrganizer Web-App URL**
   Die URL Ihrer persönlichen KursOrganizer Web-App
   Format: https://app.ihrefirma.kursorganizer.com/build/

2. **KursOrganizer Organization ID**
   Ihre eindeutige Organisation-ID (UUID-Format)
   Beispiel: 123e4567-e89b-12d3-a456-426614174000

**Falls Ihnen diese Werte nicht bekannt sind, wenden Sie sich bitte an:**
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
      (z.B. https://app.ihrefirma.kursorganizer.com/build/)
   
   b) Geben Sie Ihre **KursOrganizer Organization ID** ein
      (Diese ID erhalten Sie von KursOrganizer oder support@kursorganizer.com)
   
   c) Klicken Sie auf **"Verbindung testen"** um zu prüfen, ob URL und Organization ID zusammenpassen
   
   d) Wenn der Test erfolgreich ist, klicken Sie auf **"Speichern"**

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

**Alle Kurse anzeigen:**
[kursorganizer_iframe]

**Kurse einer bestimmten Stadt:**
[kursorganizer_iframe city="Berlin"]

**Kurse eines bestimmten Standorts:**
[kursorganizer_iframe locationid="standort-id"]

**Bestimmte Kurstypen:**
[kursorganizer_iframe coursetypeids="id1,id2,id3"]

Weitere Beispiele und Optionen finden Sie im Tab "Anleitungen" im Plugin.

== Wichtige Hinweise ==

* **Sicherheit**: URL und Organization ID müssen übereinstimmen, sonst funktioniert das Plugin nicht
* **Erste Einrichtung**: Beim ersten Öffnen des Plugins werden Sie Schritt für Schritt durch die Konfiguration geführt
* **Test-Funktion**: Nutzen Sie den "Verbindung testen" Button, um Ihre Eingaben zu überprüfen
* **Support**: Bei Fragen oder Problemen wenden Sie sich an support@kursorganizer.com

== Changelog ==

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

