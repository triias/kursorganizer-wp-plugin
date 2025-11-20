<?php
/*
Plugin Name: KursOrganizer X iFrame
Plugin URI: https://kursorganizer.com
Description: Fügt einen Shortcode hinzu, um das WebModul des KO auf der Wordpressseite per shortcode integriert.
Version: 1.0.4
Author: KursOrganizer GmbH
Author URI: https://kursorganizer.com
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: kursorganizer-wp-plugin
Domain Path: /languages
*/

// Sicherheit: Direktzugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

define('KURSORGANIZER_VERSION', '1.0.4');
define('KURSORGANIZER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('KURSORGANIZER_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load updater class
require_once KURSORGANIZER_PLUGIN_DIR . 'includes/class-plugin-updater.php';

// Initialize the updater
function kursorganizer_init_updater()
{
    if (!class_exists('KursOrganizer_Plugin_Updater')) {
        return;
    }

    // Retrieve stored GitHub access token
    $options = get_option('kursorganizer_settings');
    $access_token = isset($options['github_token']) ? $options['github_token'] : '';

    // Configure the updater
    $plugin_file = __FILE__;
    $updater = new KursOrganizer_Plugin_Updater([
        'slug' => $plugin_file,
        'proper_folder_name' => 'kursorganizer-wp-plugin',
        'api_url' => 'https://api.github.com/repos/triias/kursorganizer-wp-plugin',
        'raw_url' => 'https://raw.github.com/triias/kursorganizer-wp-plugin/master',
        'github_url' => 'https://github.com/triias/kursorganizer-wp-plugin',
        'zip_url' => 'https://github.com/triias/kursorganizer-wp-plugin/archive/master.zip',
        'sslverify' => true,
        'access_token' => $access_token,
    ]);
}

// Move this to run later when admin functions are available
add_action('admin_init', 'kursorganizer_init_updater');

// Add settings link on plugin page
function kursorganizer_plugin_action_links($links)
{
    $settings_link = '<a href="admin.php?page=kursorganizer-settings">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'kursorganizer_plugin_action_links');

// Add admin menu
function kursorganizer_add_admin_menu()
{
    add_menu_page(
        'KursOrganizer X Settings',     // Page title
        'KursOrganizer X',              // Menu title
        'manage_options',               // Capability
        'kursorganizer-settings',       // Menu slug
        'kursorganizer_settings_page',  // Function
        'dashicons-calendar-alt',       // Icon
        30                              // Position
    );
}
add_action('admin_menu', 'kursorganizer_add_admin_menu');

// Register settings
function kursorganizer_settings_init()
{
    register_setting(
        'kursorganizer_settings',           // Option group
        'kursorganizer_settings',           // Option name
        'kursorganizer_sanitize_settings'   // Sanitize callback
    );

    add_settings_section(
        'kursorganizer_main_section',
        'KursOrganizer Konfiguration',
        'kursorganizer_section_callback',
        'kursorganizer-settings'
    );

    add_settings_field(
        'main_app_url',
        'KursOrganizer Web-App URL',
        'kursorganizer_url_field_callback',
        'kursorganizer-settings',
        'kursorganizer_main_section'
    );

    // Add debug mode field
    add_settings_field(
        'debug_mode',
        'Debug Modus',
        'kursorganizer_debug_mode_callback',
        'kursorganizer-settings',
        'kursorganizer_main_section'
    );

    add_settings_section(
        'kursorganizer_security_section',
        'Sicherheit',
        'kursorganizer_security_section_callback',
        'kursorganizer-settings'
    );

    add_settings_field(
        'github_token',
        'GitHub Access Token',
        'kursorganizer_token_field_callback',
        'kursorganizer-settings',
        'kursorganizer_security_section'
    );

    add_settings_section(
        'kursorganizer_css_section',
        'CSS-Anpassungen',
        'kursorganizer_css_section_callback',
        'kursorganizer-settings'
    );
    add_settings_field(
        'custom_css_url',
        'CSS-Datei URL',
        'kursorganizer_css_url_field_callback',
        'kursorganizer-settings',
        'kursorganizer_css_section'
    );
}
add_action('admin_init', 'kursorganizer_settings_init');

// Sanitize settings
function kursorganizer_sanitize_settings($input)
{
    $new_input = array();
    if (isset($input['main_app_url'])) {
        $new_input['main_app_url'] = esc_url_raw(trailingslashit($input['main_app_url']));
    }
    $new_input['debug_mode'] = isset($input['debug_mode']);

    // Validate and save GitHub token
    if (isset($input['github_token'])) {
        $new_input['github_token'] = kursorganizer_validate_token($input['github_token']);
    } else {
        // Keep existing token if not changed
        $options = get_option('kursorganizer_settings');
        $new_input['github_token'] = isset($options['github_token']) ? $options['github_token'] : '';
    }

    // Validate and save CSS URL
    if (isset($input['custom_css_url'])) {
        $new_input['custom_css_url'] = kursorganizer_validate_css_url($input['custom_css_url']);
    } else {
        $options = get_option('kursorganizer_settings');
        $new_input['custom_css_url'] = isset($options['custom_css_url']) ? $options['custom_css_url'] : '';
    }

    return $new_input;
}

// Section callback
function kursorganizer_section_callback()
{
    echo '<p>Bitte geben Sie hier die URL Ihrer KursOrganizer Web-App ein.</p>';
}

// URL field callback
function kursorganizer_url_field_callback()
{
    $options = get_option('kursorganizer_settings');
    $value = isset($options['main_app_url']) ? $options['main_app_url'] : '';
?>
    <input type='url' name='kursorganizer_settings[main_app_url]' value='<?php echo esc_attr($value); ?>'
        class="regular-text" placeholder="https://app.ihrefirma.kursorganizer.com/build/" required>
    <p class="description">
        Beispiel: https://app.ihrefirma.kursorganizer.com/build/
    </p>
<?php
}

// Debug mode field callback
function kursorganizer_debug_mode_callback()
{
    $options = get_option('kursorganizer_settings');
    $debug_mode = isset($options['debug_mode']) ? $options['debug_mode'] : false;
?>
    <label>
        <input type='checkbox' name='kursorganizer_settings[debug_mode]' <?php checked($debug_mode, true); ?>>
        Debug-Informationen anzeigen
    </label>
    <p class="description">
        Zeigt technische Informationen unter dem iFrame an (nur für Entwicklung)
    </p>
<?php
}

// Security section callback
function kursorganizer_security_section_callback()
{
    echo '<p>Sicherheitseinstellungen für Plugin-Updates</p>';
}

// Token field callback
function kursorganizer_token_field_callback()
{
    $options = get_option('kursorganizer_settings');
    $token = isset($options['github_token']) ? $options['github_token'] : '';
?>
    <input type='password' name='kursorganizer_settings[github_token]' value='<?php echo esc_attr($token); ?>'
        class="regular-text">
    <p class="description">
        GitHub Personal Access Token für automatische Updates
    </p>
<?php
}

// Token validation
function kursorganizer_validate_token($token)
{
    if (empty($token)) {
        return '';
    }

    $response = wp_remote_get('https://api.github.com/user', array(
        'headers' => array(
            'Authorization' => "token $token",
            'Accept' => 'application/vnd.github.v3+json',
        )
    ));

    if (is_wp_error($response)) {
        add_settings_error(
            'kursorganizer_messages',
            'token_error',
            'GitHub Token konnte nicht validiert werden.'
        );
        return '';
    }

    return $token;
}

// CSS section callback
function kursorganizer_css_section_callback()
{
    echo '<p>Passen Sie das Aussehen des KursOrganizer iFrames an. Geben Sie die URL zu einer externen CSS-Datei ein.</p>';
}

// CSS URL field callback
function kursorganizer_css_url_field_callback()
{
    $options = get_option('kursorganizer_settings');
    $value = isset($options['custom_css_url']) ? $options['custom_css_url'] : '';
?>
    <input type='url' name='kursorganizer_settings[custom_css_url]' value='<?php echo esc_attr($value); ?>'
        class="regular-text" placeholder="https://example.com/custom.css">
    <p class="description">
        Geben Sie hier die vollständige URL zu einer externen CSS-Datei ein.<br>
        Beispiel: <code>https://www.fitimwasser.de/wp-content/themes/theme-name/custom-kursorganizer.css</code><br>
        <strong>Hinweis:</strong> Die CSS-Datei muss öffentlich zugänglich sein und CORS-Header erlauben.
    </p>
<?php
}

// Validate CSS URL
function kursorganizer_validate_css_url($url)
{
    if (empty($url)) {
        return '';
    }
    // Validate URL format
    $url = esc_url_raw($url);

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        add_settings_error(
            'kursorganizer_messages',
            'css_url_error',
            'Die CSS-URL ist ungültig.'
        );
        return '';
    }
    // Check if URL points to a CSS file (optional check)
    $path = parse_url($url, PHP_URL_PATH);
    if ($path && !preg_match('/\.css$/i', $path)) {
        add_settings_error(
            'kursorganizer_messages',
            'css_url_warning',
            'Die URL scheint nicht auf eine CSS-Datei zu verweisen.',
            'warning'
        );
    }
    return $url;
}

// Settings page
function kursorganizer_settings_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }
?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <?php
        if (isset($_GET['settings-updated'])) {
            add_settings_error(
                'kursorganizer_messages',
                'kursorganizer_message',
                'Einstellungen gespeichert',
                'updated'
            );
        }
        settings_errors('kursorganizer_messages');
        ?>

        <!-- URL Configuration Form -->
        <div class="card" style="max-width: 800px; margin-bottom: 20px;">
            <form action="options.php" method="post">
                <?php
                settings_fields('kursorganizer_settings');
                do_settings_sections('kursorganizer-settings');
                submit_button('Speichern');
                ?>
            </form>
        </div>

        <!-- Shortcode Examples -->
        <div class="card" style="max-width: 800px;">
            <h2>Shortcode Beispiele</h2>

            <h3>Allgemeine Kurssuche</h3>
            <p>Zeigt alle verfügbaren Kurse mit Filtermenü an:</p>
            <code>[kursorganizer_iframe]</code>

            <h3>Kurssuche nach Stadt</h3>
            <p>Zeigt alle Kurse in einer bestimmten Stadt an:</p>
            <code>[kursorganizer_iframe city="Berlin"]</code>

            <h3>Bestimmter Kursleiter</h3>
            <p>Zeigt alle Kurse eines bestimmten Kursleiters an:</p>
            <code>[kursorganizer_iframe instructorid="trainer-id"]</code>

            <h3>Bestimmter Kurstyp</h3>
            <p>Zeigt einen spezifischen Kurstyp an:</p>
            <code>[kursorganizer_iframe coursetypeid="kurstyp-id"]</code>

            <h3>Mehrere Kurstypen</h3>
            <p>Zeigt mehrere spezifische Kurstypen an (IDs kommagetrennt):</p>
            <code>[kursorganizer_iframe coursetypeids="id1,id2,id3"]</code>

            <h3>Standort-spezifische Kurse</h3>
            <p>Zeigt Kurse an einem bestimmten Standort:</p>
            <code>[kursorganizer_iframe locationid="standort-id"]</code>

            <h3>Kurse an bestimmten Tagen</h3>
            <p>Filtert Kurse nach bestimmten Tagen:</p>
            <code>[kursorganizer_iframe dayfilter="1,2,3"]</code>
            <p class="description">Tage: 1=Montag, 2=Dienstag, etc.</p>

            <h3>Kurskategorie</h3>
            <p>Zeigt Kurse einer bestimmten Kategorie:</p>
            <code>[kursorganizer_iframe coursecategoryid="kategorie-id"]</code>

            <h3>Ohne Filtermenü</h3>
            <p>Zeigt Kurse ohne das Filtermenü an:</p>
            <code>[kursorganizer_iframe showfiltermenu="false"]</code>

            <h3>Komplexes Beispiel</h3>
            <p>Kombination mehrerer Parameter:</p>
            <code>[kursorganizer_iframe city="Berlin" coursetypeids="92f06d07-ca58-4575-892d-0c75d9afc5e5" showfiltermenu="false"]</code>
            <p class="description">Zeigt spezifische Kurse (z.B. Pingu-Schwimmkurs) in Berlin ohne Filtermenü</p>

            <hr>

            <h3>Hinweise zur Verwendung</h3>
            <ul>
                <li>Alle Parameter sind optional</li>
                <li>Parameter können beliebig kombiniert werden</li>
                <li>IDs können Sie aus Ihrem KursOrganizer Backend entnehmen</li>
                <li>Lassen Sie Parameter weg, wenn keine Einschränkung gewünscht ist</li>
            </ul>
        </div>

        <!-- CSS-Anpassungen Info -->
        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2>CSS-Anpassungen</h2>
            <p>Sie können das Aussehen des KursOrganizer iFrames über die Einstellungen anpassen.</p>

            <h3>CSS-Beispiele</h3>

            <h4>Schriftarten anpassen:</h4>
            <pre><code>body {
    font-family: 'Arial', 'Helvetica Neue', sans-serif !important;
    font-size: 16px;
}
/* Alle Elemente mit Schriftart versehen */
* {
    font-family: 'Arial', 'Helvetica Neue', sans-serif !important;
}</code></pre>
            <p class="description">Verwenden Sie <code>!important</code>, um sicherzustellen, dass die Schriftart auch auf alle Ant Design Komponenten angewendet wird.</p>

            <h4>Button-Farben ändern:</h4>
            <pre><code>.ant-btn-primary {
    background-color: #ff0000;
    border-color: #ff0000;
}</code></pre>

            <h4>Karten-Styling:</h4>
            <pre><code>.ant-card {
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}</code></pre>

            <h4>Tabelle anpassen:</h4>
            <pre><code>.ant-table {
    font-size: 14px;
}
.ant-table-thead > tr > th {
    background-color: #f0f0f0;
}</code></pre>

            <hr>

            <h3>Wichtige Hinweise</h3>
            <ul>
                <li><strong>CSS-Spezifität:</strong> Verwenden Sie ausreichend spezifische Selektoren (z.B. <code>.ant-btn-primary</code> statt nur <code>button</code>)</li>
                <li><strong>Ant Design:</strong> Die App verwendet Ant Design. Sie können alle Ant Design Komponenten-Klassen stylen</li>
                <li><strong>Externe CSS-Dateien:</strong> Müssen öffentlich zugänglich sein und CORS-Header erlauben</li>
            </ul>
        </div>
    </div>
<?php
}

/**
 * Shortcode-Funktion für KursOrganizer iFrame
 *
 * @param array $atts Shortcode-Attribute
 * @return string HTML-Ausgabe des iFrames
 */
function kursOrganizer_iframe_shortcode($atts)
{
    // Attribute mit Standardwerten definieren
    $atts = shortcode_atts(
        array(
            'city' => '',
            'instructorid' => '',
            'coursetypeid' => '',
            'coursetypeids' => '',
            'locationid' => '',
            'dayfilter' => '',
            'coursecategoryid' => '',
            'showfiltermenu' => 'true',
        ),
        $atts,
        'kursorganizer_iframe'
    );

    // Get configured main app URL
    $options = get_option('kursorganizer_settings');
    $mainAppUrl = isset($options['main_app_url']) ? $options['main_app_url'] : 'https://app.demo-schwimmschule.kursorganizer.com/build/';

    // CSS-Parameter aus Settings lesen
    $custom_css_url = isset($options['custom_css_url']) ? trim($options['custom_css_url']) : '';

    // Aktuelle URL der Elternseite
    $parentUrl = urlencode(get_permalink());

    // Erstellen der iFrame-URL mit den Parametern
    $iframe_src = esc_url($mainAppUrl) . "?parentUrl=" . $parentUrl .
        "&city=" . urlencode($atts['city']) .
        "&instructorId=" . urlencode($atts['instructorid']) .
        "&courseTypeId=" . urlencode($atts['coursetypeid']) .
        "&courseTypeIds=" . urlencode($atts['coursetypeids']) .
        "&locationId=" . urlencode($atts['locationid']) .
        "&dayFilter=" . urlencode($atts['dayfilter']) .
        "&courseCategoryId=" . urlencode($atts['coursecategoryid']) .
        "&showFilterMenu=" . urlencode($atts['showfiltermenu']);

    // CSS-Parameter hinzufügen
    if (!empty($custom_css_url)) {
        $iframe_src .= "&customCssUrl=" . urlencode($custom_css_url);
    }

    // Get debug mode setting
    $debug_mode = isset($options['debug_mode']) ? $options['debug_mode'] : false;

    // Eindeutige ID für jeden iFrame generieren
    static $iframe_counter = 0;
    $iframe_counter++;
    $unique_id = 'kursorganizer-iframe-' . $iframe_counter;

    // HTML für das iFrame mit eindeutiger ID und gemeinsamer CSS-Klasse
    $iframe_html = '<iframe id="' . $unique_id . '" class="kursorganizer-iframe" frameborder="0" style="width: 1px; min-width: 100%;" src="' . $iframe_src . '"></iframe>';

    // Optional: Platzhalter für Callback-Informationen (nur im Debug-Modus)
    $callback_html = $debug_mode ? '<p id="kursorganizer-callback-' . $iframe_counter . '"></p>' : '';

    return $iframe_html . $callback_html;
}
add_shortcode('kursorganizer_iframe', 'kursOrganizer_iframe_shortcode');

/**
 * Skripte und Styles einbinden
 */
function kursorganizer_enqueue_scripts()
{
    // Get settings
    $options = get_option('kursorganizer_settings');
    $debug_mode = isset($options['debug_mode']) ? $options['debug_mode'] : false;

    // jQuery von WordPress verwenden
    wp_enqueue_script('jquery');

    $iframe_resizer_url = isset($options['iframe_resizer_url']) ?
        $options['iframe_resizer_url'] :
        'https://app.demo-schwimmschule.kursorganizer.com/build/js/iframeResizer.min.js';

    // iframeResizer Skript einbinden
    wp_enqueue_script(
        'iframe-resizer',
        $iframe_resizer_url,
        array('jquery'),
        null,
        true
    );

    // Inline-Skript für iframeResizer initialisieren
    $inline_script = "
        jQuery(document).ready(function($) {
            iFrameResize({
                enablePublicMethods: false,
                onResized: function (messageData) {" .
        ($debug_mode ? "
                    var callbackId = messageData.iframe.id.replace('iframe', 'callback');
                    $('#' + callbackId).html(
                        '<b>Frame ID:</b> ' + messageData.iframe.id +
                        ' <b>Height:</b> ' + messageData.height +
                        ' <b>Width:</b> ' + messageData.width +
                        ' <b>Event type:</b> ' + messageData.type
                    );" : "") . "
                },
                onMessage: function (messageData) {" .
        ($debug_mode ? "
                    var callbackId = messageData.iframe.id.replace('iframe', 'callback');
                    $('#' + callbackId).html(
                        '<b>Frame ID:</b> ' + messageData.iframe.id +
                        ' <b>Message:</b> ' + messageData.message
                    );" : "") . "
                    alert(messageData.message);
                },
                onClosed: function (id) {" .
        ($debug_mode ? "
                    var callbackId = id.replace('iframe', 'callback');
                    $('#' + callbackId).html(
                        '<b>IFrame (</b>' + id + '<b>) removed from page.</b>'
                    );" : "") . "
                },
            }, '.kursorganizer-iframe');
        });
    ";

    wp_add_inline_script('iframe-resizer', $inline_script);
}
add_action('wp_enqueue_scripts', 'kursorganizer_enqueue_scripts');
