=== KursOrganizer X iFrame ===
Contributors: KursOrganizer GmbH
Tags: iframe, courses, organization
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0
License: GPL2

Integration des KursOrganizer WebModuls in WordPress Seiten.

== Description ==
Fügt einen Shortcode hinzu, um das WebModul des KursOrganizer auf der Wordpressseite zu integrieren.

=== Shortcode Verwendung ===
Der Shortcode [seesternchen_iframe] kann mit folgenden Parametern verwendet werden:

* city - Stadt/Ort Filter
* instructorid - Filtert nach einem bestimmten Kursleiter
* coursetypeid - Filtert nach einem bestimmten Kurstyp
* coursetypeids - Filtert nach mehreren Kurstypen (kommagetrennt)
* locationid - Filtert nach einem bestimmten Standort
* dayfilter - Filtert nach bestimmten Tagen
* coursecategoryid - Filtert nach einer bestimmten Kurskategorie
* showfiltermenu - Zeigt/versteckt das Filtermenü (true/false, Standard: true)

Beispiel:
[seesternchen_iframe city="Berlin" coursetypeid="123" showfiltermenu="false"]

== Installation ==
1. Upload the plugin files to the `/wp-content/plugins/kursorganizer-iframe` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure the plugin settings under Settings -> KursOrganizer X

== Changelog ==
= 1.0 =
* Initial release
