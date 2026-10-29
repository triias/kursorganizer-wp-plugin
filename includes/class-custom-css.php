<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Eigenes CSS für das Web Modul (Einstellung `custom_css`), wirksam im iFrame und im Embed.
 *
 * Kunden schreiben das CSS im iFrame-Stil (`.ant-…`, `#content`). Im iFrame liefert das Plugin
 * es unter einer eigenen Route als `customCssUrl` aus, im Embed übersetzt es die Selektoren
 * (`.ant-` → `.koe-`, `#content` → `#ko-content`) und gibt sie als Inline-Style auf der Seite aus.
 * Reine Funktionen ohne WordPress-Zustand, damit sie in PHPUnit laufen.
 */
class KursOrganizer_Custom_CSS
{
    public const MAX_LENGTH = 10000;

    public const ROUTE_NAMESPACE = 'kursorganizer/v1';
    // Ohne Endung .css: viele nginx-Konfigurationen fangen *.css als statische Datei ab (404 vor PHP).
    public const ROUTE = '/custom-css';

    /**
     * Eingabe säubern: keine Tags, kein `<` (verhindert `</style>` auf der Seite), Zeilenenden vereinheitlicht.
     * Länger als MAX_LENGTH liefert null, der Aufrufer behält dann den alten Wert.
     *
     * @param mixed $input Rohwert aus dem Formular.
     * @return string|null
     */
    public static function sanitize($input)
    {
        $css = str_replace(array("\r\n", "\r"), "\n", (string) $input);
        $css = wp_strip_all_tags($css);
        $css = str_ireplace('</style', '', $css);
        $css = str_replace('<', '', $css);
        $css = trim($css);
        if (strlen($css) > self::MAX_LENGTH) {
            return null;
        }
        return $css;
    }

    /**
     * iFrame-Selektoren in die des Embed-Modus übersetzen (ADR 0019 in ko-X-main: antd-Präfix `koe`,
     * Inhaltscontainer `#ko-content`). `#footer` heißt in beiden Modi gleich.
     *
     * Zwei Textersetzungen statt eines CSS-Parsers: `.ant-`-Klassen und `#content` kommen auf einer
     * Kundenseite außerhalb des Moduls nicht vor, deshalb reicht das ohne Wurzelpräfix.
     */
    public static function translate_for_embed($css)
    {
        $css = str_replace('.ant-', '.koe-', (string) $css);
        return preg_replace('/#content(?![A-Za-z0-9_-])/', '#ko-content', $css);
    }

    /**
     * Inhalt der Route: optional die alte CSS-Datei-URL per @import voran, dann das eigene CSS.
     * @import muss in CSS vor allen Regeln stehen.
     */
    public static function stylesheet($css, $import_url = '')
    {
        $import_url = esc_url_raw((string) $import_url);
        $body = (string) $css;
        if ($import_url !== '') {
            $body = '@import url("' . str_replace('"', '%22', $import_url) . '");' . "\n" . $body;
        }
        return $body;
    }

    /**
     * Versionsparameter für Cache-Header: ändert sich mit dem Inhalt, sonst hält der Browser des
     * iFrames das alte CSS bis zu einem Jahr fest.
     */
    public static function version($css, $import_url = '')
    {
        return substr(md5(self::stylesheet($css, $import_url)), 0, 12);
    }
}
