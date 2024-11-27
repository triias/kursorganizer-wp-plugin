<?php
define('KURSORGANIZER_VERSION', '1.0.0');
define('KURSORGANIZER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('KURSORGANIZER_PLUGIN_URL', plugin_dir_url(__FILE__));

// Initialisiere den Updater
function init_kursorganizer_updater()
{
    require_once KURSORGANIZER_PLUGIN_DIR . 'includes/class-plugin-updater.php';
    new KursOrganizer_Plugin_Updater(__FILE__);
}
add_action('init', 'init_kursorganizer_updater');

// Add this at the top of your kursorganizer-wp-plugin.php file
require_once plugin_dir_path(__FILE__) . 'includes/class-plugin-updater.php';

// Initialize the updater
function kursorganizer_init_updater()
{
    // Load the updater class
    if (!class_exists('KursOrganizer_Plugin_Updater')) {
        return;
    }

    // Retrieve stored GitHub access token
    $options = get_option('kursorganizer_settings');
    $access_token = isset($options['github_token']) ? $options['github_token'] : '';

    // Configure the updater
    $updater = new KursOrganizer_Plugin_Updater(array(
        'slug' => plugin_basename(__FILE__), // Plugin Slug
        'proper_folder_name' => 'kursorganizer-wp-plugin', // Plugin folder name
        'api_url' => 'https://api.github.com/repos/[YOUR-USERNAME]/[REPO-NAME]', // GitHub API URL
        'raw_url' => 'https://raw.github.com/[YOUR-USERNAME]/[REPO-NAME]/master', // GitHub raw URL
        'github_url' => 'https://github.com/[YOUR-USERNAME]/[REPO-NAME]', // GitHub repository URL
        'zip_url' => 'https://github.com/[YOUR-USERNAME]/[REPO-NAME]/archive/master.zip', // ZIP download URL
        'sslverify' => true,
        'access_token' => $access_token,
    ));
}
add_action('init', 'kursorganizer_init_updater');

// Add GitHub settings to your existing settings page
function kursorganizer_add_github_settings()
{
    add_settings_field(
        'github_token',
        'GitHub Access Token',
        'kursorganizer_github_token_callback',
        'kursorganizer-settings',
        'kursorganizer_main_section'
    );
}
add_action('admin_init', 'kursorganizer_add_github_settings');

// GitHub token field callback
function kursorganizer_github_token_callback()
{
    $options = get_option('kursorganizer_settings');
    $token = isset($options['github_token']) ? $options['github_token'] : '';
?>
    <input type='password' name='kursorganizer_settings[github_token]' value='<?php echo esc_attr($token); ?>'
        class="regular-text">
    <p class="description">
        Enter your GitHub personal access token for private repository access
    </p>
<?php
}

// <?php
/*
Plugin Name: KursOrganizer X iFrame
Plugin URI: https://kursorganizer.com
Description: Fügt einen Shortcode hinzu, um das WebModul des KO auf der Wordpressseite per shortcode integriert.
Version: 1.0
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
            <h2>URL Konfiguration</h2>
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

    // Get debug mode setting
    $debug_mode = isset($options['debug_mode']) ? $options['debug_mode'] : false;

    // HTML für das iFrame
    $iframe_html = '<iframe id="kursorganizer_iframe" frameborder="0" style="width: 1px; min-width: 100%;" src="' . $iframe_src . '"></iframe>';

    // Optional: Platzhalter für Callback-Informationen (nur im Debug-Modus)
    $callback_html = $debug_mode ? '<p id="kursorganizer_callback"></p>' : '';

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
                    $('p#kursorganizer_callback').html(
                        '<b>Frame ID:</b> ' + messageData.iframe.id +
                        ' <b>Height:</b> ' + messageData.height +
                        ' <b>Width:</b> ' + messageData.width +
                        ' <b>Event type:</b> ' + messageData.type
                    );" : "") . "
                },
                onMessage: function (messageData) {" .
        ($debug_mode ? "
                    $('p#kursorganizer_callback').html(
                        '<b>Frame ID:</b> ' + messageData.iframe.id +
                        ' <b>Message:</b> ' + messageData.message
                    );" : "") . "
                    alert(messageData.message);
                },
                onClosed: function (id) {" .
        ($debug_mode ? "
                    $('p#kursorganizer_callback').html(
                        '<b>IFrame (</b>' + id + '<b>) removed from page.</b>'
                    );" : "") . "
                },
            }, '#kursorganizer_iframe');
        });
    ";

    wp_add_inline_script('iframe-resizer', $inline_script);
}
add_action('wp_enqueue_scripts', 'kursorganizer_enqueue_scripts');
