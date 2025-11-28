<?php
/**
 * CSS-Endpoint mit CORS-Header für externe CSS-Datei
 * 
 * Diese Datei serviert die CSS-Datei mit den notwendigen CORS-Headern,
 * damit Chrome's Private Network Access Policy umgangen wird.
 * 
 * URL: /wp-content/plugins/kursorganizer-wp-plugin/assets/css/external-css-example.php
 */

// Sicherheit: Direktzugriff verhindern (nur wenn nicht über WordPress geladen)
if (!defined('ABSPATH')) {
    // Erlaube direkten Zugriff nur für CSS-Dateien
    $css_file = __DIR__ . '/external-css-example.css';
    if (!file_exists($css_file)) {
        http_response_code(404);
        exit;
    }
    
    // CORS-Header setzen (WICHTIG für Chrome Private Network Access)
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Allow-Private-Network: true'); // WICHTIG für Chrome!
    header('Access-Control-Max-Age: 86400');
    
    // Content-Type für CSS
    header('Content-Type: text/css; charset=utf-8');
    
    // Cache-Control für bessere Performance
    header('Cache-Control: public, max-age=31536000');
    
    // OPTIONS-Request behandeln (CORS Preflight)
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
    
    // CSS-Datei ausgeben
    readfile($css_file);
    exit;
}

// Falls über WordPress geladen wird, Header trotzdem setzen
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Private-Network: true');
header('Access-Control-Max-Age: 86400');
header('Content-Type: text/css; charset=utf-8');
header('Cache-Control: public, max-age=31536000');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$css_file = __DIR__ . '/external-css-example.css';
if (file_exists($css_file)) {
    readfile($css_file);
}


