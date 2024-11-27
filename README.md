# KursOrganizer X iFrame Integration
Contributors: KursOrganizer GmbH
Tags: iframe, courses, organization
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0
License: GPL2

Integration des KursOrganizer WebModuls in WordPress Seiten.

## Description
Fügt einen Shortcode hinzu, um das WebModul des KursOrganizer auf der Wordpressseite zu integrieren.

## Shortcode Verwendung
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

## Installation in WordPress:
Download the latest release ZIP from this GitHub repository

In WordPress admin panel:

Go to Plugins → Add New → Upload Plugin
Choose the downloaded ZIP file
Click "Install Now" and then "Activate"
Configure the plugin:

Go to "KursOrganizer X" in the WordPress admin menu
Enter your KursOrganizer Web-App URL
Add your GitHub token for automatic updates (optional)
Save settings
Use the shortcode in your pages/posts:

## Changelog
= 1.0 =
* Initial release
