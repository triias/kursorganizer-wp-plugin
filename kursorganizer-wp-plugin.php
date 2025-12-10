<?php
/*
Plugin Name: KursOrganizer X iFrame
Plugin URI: https://kursorganizer.com
Description: Fügt einen Shortcode hinzu, um das WebModul des KO auf der Wordpressseite per shortcode integriert.
Version: 1.2.1
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

define('KURSORGANIZER_VERSION', '1.2.1');
define('KURSORGANIZER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('KURSORGANIZER_PLUGIN_URL', plugin_dir_url(__FILE__));

// Cache-Busting: Use file modification time for better cache invalidation
define('KURSORGANIZER_CACHE_VERSION', KURSORGANIZER_VERSION . '.' . filemtime(__FILE__));

// Load updater class
require_once KURSORGANIZER_PLUGIN_DIR . 'includes/class-plugin-updater.php';

// Load API helper class
require_once KURSORGANIZER_PLUGIN_DIR . 'includes/class-kursorganizer-api.php';

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

    add_settings_field(
        'ko_organization_id',
        'KursOrganizer Organization ID',
        'kursorganizer_org_id_field_callback',
        'kursorganizer-settings',
        'kursorganizer_main_section'
    );

    add_settings_section(
        'kursorganizer_debug_section',
        'Debug-Einstellungen',
        'kursorganizer_debug_section_callback',
        'kursorganizer-settings'
    );

    // Add debug mode field
    add_settings_field(
        'debug_mode',
        'Debug Modus',
        'kursorganizer_debug_mode_callback',
        'kursorganizer-settings',
        'kursorganizer_debug_section'
    );

    // GitHub Token field temporarily hidden
    // add_settings_section(
    //     'kursorganizer_security_section',
    //     'Sicherheit',
    //     'kursorganizer_security_section_callback',
    //     'kursorganizer-settings'
    // );

    // add_settings_field(
    //     'github_token',
    //     'GitHub Access Token',
    //     'kursorganizer_token_field_callback',
    //     'kursorganizer-settings',
    //     'kursorganizer_security_section'
    // );

    add_settings_section(
        'kursorganizer_css_section',
        'CSS-Anpassungen',
        'kursorganizer_css_section_callback',
        'kursorganizer-settings'
    );
    add_settings_field(
        'use_example_css',
        'Beispiel-CSS aktivieren',
        'kursorganizer_example_css_field_callback',
        'kursorganizer-settings',
        'kursorganizer_css_section'
    );
    add_settings_field(
        'custom_css_url',
        'CSS-Datei URL',
        'kursorganizer_css_url_field_callback',
        'kursorganizer-settings',
        'kursorganizer_css_section'
    );
    add_settings_field(
        'max_width',
        'Maximale Breite',
        'kursorganizer_max_width_field_callback',
        'kursorganizer-settings',
        'kursorganizer_css_section'
    );
}
add_action('admin_init', 'kursorganizer_settings_init');

// Sanitize settings
function kursorganizer_sanitize_settings($input)
{
    // Lade bestehende Optionen, um sie zu erhalten
    $existing_options = get_option('kursorganizer_settings', array());

    // Starte mit den bestehenden Optionen
    $new_input = $existing_options;

    // Überschreibe nur die geänderten Werte
    if (isset($input['main_app_url'])) {
        $url = esc_url_raw(trim($input['main_app_url']));

        // Ensure URL ends with /build or /build/
        $parsed = parse_url($url);
        if ($parsed && isset($parsed['host'])) {
            // Check if URL is a kursorganizer.com domain
            if (strpos($parsed['host'], 'kursorganizer.com') !== false) {
                $path = isset($parsed['path']) ? rtrim($parsed['path'], '/') : '';

                // If path doesn't end with /build, add it
                if ($path !== '/build') {
                    // Remove trailing slash if present, then add /build
                    $url = rtrim($url, '/') . '/build';
                }

                // Add trailing slash
                $url = trailingslashit($url);
            } else {
                // For non-kursorganizer.com domains, require /build
                $path = isset($parsed['path']) ? rtrim($parsed['path'], '/') : '';
                if ($path !== '/build') {
                    add_settings_error(
                        'kursorganizer_messages',
                        'url_missing_build',
                        'Die URL muss auf "/build" enden. Beispiel: https://app.ihrefirma.kursorganizer.com/build/',
                        'error'
                    );
                    // Return existing options to prevent saving
                    return $existing_options;
                }
                $url = trailingslashit($url);
            }
        }

        $new_input['main_app_url'] = $url;
    }

    // Validate and save Organization ID
    if (isset($input['ko_organization_id'])) {
        $new_input['ko_organization_id'] = sanitize_text_field(trim($input['ko_organization_id']));
    }
    // Wenn nicht gesetzt, behalte den bestehenden Wert (bereits in $new_input)

    // Checkboxen: Wenn nicht gesetzt, ist der Wert false
    // Stelle sicher, dass sie immer als Boolean gespeichert werden
    $new_input['debug_mode'] = !empty($input['debug_mode']);
    $new_input['use_example_css'] = !empty($input['use_example_css']);

    // GitHub Token validation temporarily disabled
    // if (isset($input['github_token'])) {
    //     $new_input['github_token'] = kursorganizer_validate_token($input['github_token']);
    // }
    // Wenn nicht gesetzt, behalte den bestehenden Wert (bereits in $new_input)

    // Validate and save CSS URL
    if (isset($input['custom_css_url'])) {
        $new_input['custom_css_url'] = kursorganizer_validate_css_url($input['custom_css_url']);
    }
    // Wenn nicht gesetzt, behalte den bestehenden Wert (bereits in $new_input)

    // Validate and save max width
    if (isset($input['max_width'])) {
        $max_width = sanitize_text_field($input['max_width']);
        // Wenn leer, setze auf Default 1200px
        if (empty($max_width)) {
            $max_width = '1200px';
        }
        $new_input['max_width'] = $max_width;
    }

    // Validate Organization ID if both URL and ID are provided
    $main_app_url = isset($new_input['main_app_url']) ? $new_input['main_app_url'] : '';
    $ko_organization_id = isset($new_input['ko_organization_id']) ? $new_input['ko_organization_id'] : '';

    if (!empty($main_app_url) && !empty($ko_organization_id)) {
        $validation_result = kursorganizer_validate_organization_id($main_app_url, $ko_organization_id);
        // If validation failed, don't save the settings - return existing options
        if ($validation_result === false) {
            return $existing_options;
        }
    }

    return $new_input;
}

// Check if initial configuration is complete
function kursorganizer_is_configured()
{
    $options = get_option('kursorganizer_settings', array());
    $main_app_url = isset($options['main_app_url']) ? trim($options['main_app_url']) : '';
    $ko_organization_id = isset($options['ko_organization_id']) ? trim($options['ko_organization_id']) : '';

    // Both values must be set
    if (empty($main_app_url) || empty($ko_organization_id)) {
        return false;
    }

    // If both values are set, consider it configured
    // Validation errors are checked separately when saving, not here
    return true;
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

    // Check if there's a validation error for this field
    $has_error = false;
    $settings_errors = get_settings_errors('kursorganizer_messages');
    foreach ($settings_errors as $error) {
        if ($error['code'] === 'org_id_api_error' || $error['code'] === 'org_id_validation_error') {
            $has_error = true;
            break;
        }
    }

    // Add error class and style if validation failed
    $field_class = 'regular-text';
    $field_style = '';
    if ($has_error) {
        $field_class .= ' error';
        $field_style = 'border-color: #d63638; box-shadow: 0 0 0 1px #d63638;';
    }
?>
    <input type='url' name='kursorganizer_settings[main_app_url]' value='<?php echo esc_attr($value); ?>' id="main_app_url"
        class="<?php echo esc_attr($field_class); ?>" style="<?php echo esc_attr($field_style); ?>"
        placeholder="https://app.ihrefirma.kursorganizer.com/build/" required>
    <p class="description">
        Geben Sie hier die vollständige URL Ihrer KursOrganizer Web-App ein. Die URL muss auf <code>/build</code> enden.<br>
        Beispiel: <code>https://app.ihrefirma.kursorganizer.com/build/</code><br>
        <strong>Hinweis:</strong> Bei kursorganizer.com Domains wird <code>/build</code> automatisch hinzugefügt, falls es
        fehlt.
    </p>
<?php
}

// Organization ID field callback
function kursorganizer_org_id_field_callback()
{
    $options = get_option('kursorganizer_settings');
    $value = isset($options['ko_organization_id']) ? $options['ko_organization_id'] : '';

    // Check if there's a validation error for this field
    $has_error = false;
    $settings_errors = get_settings_errors('kursorganizer_messages');
    foreach ($settings_errors as $error) {
        if ($error['code'] === 'org_id_mismatch' || $error['code'] === 'org_id_api_error' || $error['code'] === 'org_id_validation_error') {
            $has_error = true;
            break;
        }
    }

    // Try to get organization ID from API if URL is set but ID is not
    $auto_fill_id = '';
    $main_app_url = isset($options['main_app_url']) ? $options['main_app_url'] : '';
    if (!empty($main_app_url) && empty($value)) {
        $api_id = KursOrganizer_API::get_organization_id();
        if (!is_wp_error($api_id)) {
            $auto_fill_id = $api_id;
        }
    }

    // Add error class and style if validation failed
    $field_class = 'regular-text';
    $field_style = '';
    if ($has_error) {
        $field_class .= ' error';
        $field_style = 'border-color: #d63638; box-shadow: 0 0 0 1px #d63638;';
    }
?>
    <input type='text' name='kursorganizer_settings[ko_organization_id]' value='<?php echo esc_attr($value); ?>'
        id="ko_organization_id" class="<?php echo esc_attr($field_class); ?>" style="<?php echo esc_attr($field_style); ?>"
        placeholder="z.B. 123e4567-e89b-12d3-a456-426614174000">
    <button type="button" id="test-org-id-btn" class="button" style="margin-left: 10px;">
        Verbindung testen
    </button>
    <span id="test-org-id-result" style="margin-left: 10px;"></span>
    <?php if (!empty($auto_fill_id)): ?>
        <button type="button" id="auto-fill-org-id" class="button" style="margin-left: 10px;">
            Automatisch ausfüllen
        </button>
        <script>
            jQuery(document).ready(function($) {
                $('#auto-fill-org-id').on('click', function() {
                    $('input[name="kursorganizer_settings[ko_organization_id]"]').val(
                        '<?php echo esc_js($auto_fill_id); ?>');
                });
            });
        </script>
    <?php endif; ?>
    <p class="description">
        Geben Sie hier die KursOrganizer Organization ID ein. Diese dient zur Sicherheitskontrolle und wird mit der
        automatisch aus der URL ermittelten ID verglichen.<br>
        <strong>Hinweis:</strong> Die ID wird beim Speichern automatisch validiert. Bei Nichtübereinstimmung wird eine
        Warnung angezeigt.<br>
        Verwenden Sie den Button "Verbindung testen", um die Übereinstimmung von URL und Organization ID zu überprüfen.
    </p>
<?php
}

/**
 * Auto-detect KursOrganizer Web-App URL from WordPress domain
 * 
 * @return string Detected Web-App URL
 */
function kursorganizer_auto_detect_app_url()
{
    // Get current WordPress site URL
    $site_url = get_site_url();
    $parsed = parse_url($site_url);

    if (!$parsed || !isset($parsed['host'])) {
        return '';
    }

    $host = $parsed['host'];

    // Remove www. prefix if present
    $host = preg_replace('/^www\./', '', $host);

    // For local development (.local domains), use localhost:8081
    if (strpos($host, '.local') !== false || strpos($host, 'localhost') !== false) {
        return 'http://localhost:8081';
    }

    // Extract subdomain or domain name
    // Examples:
    // - www.schwimmschule-xyz.de → schwimmschule-xyz
    // - schwimmschule-xyz.de → schwimmschule-xyz
    // - app.stage.dev-schule.kursorganizer.com → app.stage.dev-schule.kursorganizer.com (already correct)

    // If it's already a kursorganizer.com domain, use it as-is
    if (strpos($host, 'kursorganizer.com') !== false) {
        // Check if it's already an app.* URL
        if (strpos($host, 'app.') === 0) {
            return 'https://' . $host . '/build/';
        }
        // Otherwise, assume it's a subdomain like stage.dev-schule.kursorganizer.com
        return 'https://app.' . $host . '/build/';
    }

    // Extract the main domain name (remove TLD)
    // For example: schwimmschule-xyz.de → schwimmschule-xyz
    $parts = explode('.', $host);
    if (count($parts) >= 2) {
        $domain_name = $parts[count($parts) - 2]; // Second-to-last part
        // Build KursOrganizer App URL
        return 'https://app.' . $domain_name . '.kursorganizer.com/build/';
    }

    // Fallback: use host as-is
    return 'https://app.' . $host . '.kursorganizer.com/build/';
}

// Debug section callback
function kursorganizer_debug_section_callback()
{
    echo '<p>Debug-Einstellungen für die Entwicklung</p>';
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

// CSS section callback
function kursorganizer_css_section_callback()
{
    echo '<p>Passen Sie das Aussehen des KursOrganizer iFrames an. Geben Sie die URL zu einer externen CSS-Datei ein.</p>';
}

// Example CSS field callback
function kursorganizer_example_css_field_callback()
{
    $options = get_option('kursorganizer_settings');
    // Prüfe sowohl Boolean true als auch String "1" für Checkbox-Werte
    $use_example_css = isset($options['use_example_css']) && ($options['use_example_css'] === true || $options['use_example_css'] === '1' || $options['use_example_css'] === 1);
    // Verwende PHP-Endpoint für bessere CORS-Unterstützung (Chrome Private Network Access)
    $example_css_url = KURSORGANIZER_PLUGIN_URL . 'assets/css/external-css-example.php';
?>
    <label>
        <input type='checkbox' name='kursorganizer_settings[use_example_css]' <?php checked($use_example_css, true); ?>>
        Beispiel-CSS-Datei für Testzwecke aktivieren
    </label>
    <p class="description">
        Aktiviert die mitgelieferte Beispiel-CSS-Datei. Diese Option hat Priorität über die manuelle CSS-URL.<br>
        <a href="<?php echo esc_url($example_css_url); ?>" download="external-css-example.css"
            target="_blank">Beispiel-CSS-Datei herunterladen</a>
    </p>
<?php
}

// CSS URL field callback
function kursorganizer_css_url_field_callback()
{
    $options = get_option('kursorganizer_settings');
    $value = isset($options['custom_css_url']) ? $options['custom_css_url'] : '';
    // Prüfe sowohl Boolean true als auch String "1" für Checkbox-Werte
    $use_example_css = isset($options['use_example_css']) && ($options['use_example_css'] === true || $options['use_example_css'] === '1' || $options['use_example_css'] === 1);
?>
    <input type='url' name='kursorganizer_settings[custom_css_url]' value='<?php echo esc_attr($value); ?>'
        class="regular-text" placeholder="https://example.com/custom.css" <?php echo $use_example_css ? 'disabled' : ''; ?>>
    <p class="description">
        Geben Sie hier die vollständige URL zu einer externen CSS-Datei ein.<br>
        Beispiel: <code>https://www.fitimwasser.de/wp-content/themes/theme-name/custom-kursorganizer.css</code><br>
        <strong>Hinweis:</strong> Die CSS-Datei muss öffentlich zugänglich sein und CORS-Header erlauben.<br>
        <?php if ($use_example_css): ?>
            <strong style="color: #d63638;">Diese Option ist deaktiviert, da die Beispiel-CSS aktiviert ist.</strong>
        <?php endif; ?>
    </p>
<?php
}

// Max width field callback
function kursorganizer_max_width_field_callback()
{
    $options = get_option('kursorganizer_settings');
    $value = isset($options['max_width']) ? $options['max_width'] : '1200px';
?>
    <input type='text' name='kursorganizer_settings[max_width]' value='<?php echo esc_attr($value); ?>' class="regular-text"
        placeholder="1200px">
    <p class="description">
        Steuert die maximale Breite des Inhalts (Parameter <code>maxWidth</code>).<br>
        Standardwert: <code>1200px</code>. Lassen Sie das Feld leer, um auf den Standardwert zurückzusetzen.<br>
        Unterstützte Einheiten: px, %, em, rem, vh, vw. Wird keine Einheit angegeben, wird automatisch px verwendet.
    </p>
<?php
}

// Format max width value - adds px unit if none provided
function kursorganizer_format_max_width($value)
{
    // Entferne Leerzeichen
    $value = trim($value);

    // Wenn leer, Standardwert zurückgeben
    if (empty($value)) {
        return '1200px';
    }

    // Unterstützte Einheiten
    $units = array('px', '%', 'em', 'rem', 'vh', 'vw');

    // Prüfe ob bereits eine Einheit vorhanden ist (case-insensitive, am Ende des Strings)
    $has_unit = false;
    $value_lower = strtolower($value);
    foreach ($units as $unit) {
        // Prüfe ob die Einheit am Ende des Strings steht
        if (substr($value_lower, -strlen($unit)) === $unit) {
            $has_unit = true;
            break;
        }
    }

    // Wenn keine Einheit vorhanden, füge px hinzu
    if (!$has_unit) {
        // Prüfe ob es eine Zahl ist (kann auch Dezimalzahlen enthalten)
        if (is_numeric($value)) {
            $value = $value . 'px';
        } else {
            // Falls nicht numerisch, Standardwert zurückgeben
            return '1200px';
        }
    }

    return $value;
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

// Static flag to prevent recursion during validation
static $kursorganizer_validating = false;

// Validate Organization ID
// Returns true if valid, false if invalid, null if validation skipped
function kursorganizer_validate_organization_id($input_url, $input_org_id)
{
    global $kursorganizer_validating;

    // Skip validation if either value is empty
    if (empty($input_url) || empty($input_org_id)) {
        return null;
    }

    // Prevent recursion
    if ($kursorganizer_validating) {
        return null;
    }

    $kursorganizer_validating = true;

    try {
        // Parse URL to get origin for API call
        $parsed = parse_url($input_url);
        if (!$parsed || !isset($parsed['scheme']) || !isset($parsed['host'])) {
            $kursorganizer_validating = false;
            return;
        }

        // Validate and fix URL to ensure it ends with /build
        if (strpos($parsed['host'], 'kursorganizer.com') !== false) {
            $path = isset($parsed['path']) ? rtrim($parsed['path'], '/') : '';

            // If path doesn't end with /build, add it
            if ($path !== '/build') {
                $input_url = rtrim($input_url, '/') . '/build';
                // Re-parse after modification
                $parsed = parse_url($input_url);
            }

            // Add trailing slash
            $input_url = trailingslashit($input_url);
        } else {
            // For non-kursorganizer.com domains, require /build
            $path = isset($parsed['path']) ? rtrim($parsed['path'], '/') : '';
            if ($path !== '/build') {
                add_settings_error(
                    'kursorganizer_messages',
                    'url_missing_build',
                    'Die URL muss auf "/build" enden. Beispiel: https://app.ihrefirma.kursorganizer.com/build/',
                    'error'
                );
                $kursorganizer_validating = false;
                return false;
            }
            $input_url = trailingslashit($input_url);
        }

        $origin = $parsed['scheme'] . '://' . $parsed['host'];
        if (isset($parsed['port'])) {
            $origin .= ':' . $parsed['port'];
        }

        // Determine API URL based on the input URL
        $api_url = 'https://api.kursorganizer.com/graphql';
        if (strpos($input_url, 'localhost') !== false || strpos($input_url, '127.0.0.1') !== false || strpos($input_url, '.local') !== false) {
            $parsed_api = parse_url($input_url);
            $port = isset($parsed_api['port']) ? $parsed_api['port'] : '3000';
            $scheme = isset($parsed_api['scheme']) ? $parsed_api['scheme'] : 'http';
            $host = isset($parsed_api['host']) ? $parsed_api['host'] : 'localhost';
            $api_url = $scheme . '://' . $host . ':' . $port . '/graphql';
        } elseif (strpos($input_url, '.stage.') !== false) {
            $api_url = 'https://api.stage.kursorganizer.com/graphql';
        }

        // Query GetCompany to get organization ID directly
        $query = 'query GetCompany {
            companyPublic {
                name
                host
                koOrganization {
                    id
                }
            }
        }';

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Origin' => $origin,
            'x-application-type' => 'end-user-app'
        ];

        $body = json_encode([
            'query' => $query,
            'operationName' => 'GetCompany',
            'variables' => []
        ]);

        $response = wp_remote_post($api_url, [
            'headers' => $headers,
            'body' => $body,
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            // API error - block saving
            add_settings_error(
                'kursorganizer_messages',
                'org_id_api_error',
                sprintf(
                    'Die Organization ID konnte nicht automatisch überprüft werden: %s. Bitte stellen Sie sicher, dass die URL korrekt ist. Die Einstellungen wurden nicht gespeichert.',
                    $response->get_error_message()
                ),
                'error'
            );
            $kursorganizer_validating = false;
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);

        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['data']['companyPublic']['koOrganization']['id'])) {
            // API error - block saving
            add_settings_error(
                'kursorganizer_messages',
                'org_id_api_error',
                'Die Organization ID konnte nicht automatisch überprüft werden. Bitte stellen Sie sicher, dass die URL korrekt ist. Die Einstellungen wurden nicht gespeichert.',
                'error'
            );
            $kursorganizer_validating = false;
            return false;
        }

        $api_org_id = $data['data']['companyPublic']['koOrganization']['id'];

        // Compare IDs (case-insensitive)
        $input_org_id_clean = strtolower(trim($input_org_id));
        $api_org_id_clean = strtolower(trim($api_org_id));

        if ($input_org_id_clean !== $api_org_id_clean) {
            // IDs don't match - block saving
            // Don't show the correct ID for security reasons
            add_settings_error(
                'kursorganizer_messages',
                'org_id_mismatch',
                'FEHLER: Die eingegebene Organization ID stimmt nicht mit der automatisch aus der URL ermittelten ID überein. Bitte überprüfen Sie Ihre Eingabe und stellen Sie sicher, dass die URL und die Organization ID zur gleichen Schwimmschule gehören. Die Einstellungen wurden nicht gespeichert.',
                'error'
            );
            $kursorganizer_validating = false;
            return false;
        }

        // Validation successful
        $kursorganizer_validating = false;
        return true;
    } catch (Exception $e) {
        $kursorganizer_validating = false;
        add_settings_error(
            'kursorganizer_messages',
            'org_id_validation_error',
            'Ein unerwarteter Fehler ist bei der Validierung aufgetreten. Die Einstellungen wurden nicht gespeichert.',
            'error'
        );
        return false;
    }
}

// Settings page
function kursorganizer_settings_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    // Get active tab
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'settings';

    // Check if plugin is configured
    $is_configured = kursorganizer_is_configured();

    // Redirect to settings tab if trying to access generator without configuration
    if ($active_tab === 'generator' && !$is_configured) {
        $active_tab = 'settings';
    }
?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?> <span
                style="font-size: 0.6em; font-weight: normal; color: #666;">Version
                <?php echo esc_html(KURSORGANIZER_VERSION); ?></span></h1>

        <!-- Tabs Navigation -->
        <h2 class="nav-tab-wrapper">
            <a href="?page=kursorganizer-settings&tab=settings"
                class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                Einstellungen
            </a>
            <a href="?page=kursorganizer-settings&tab=generator"
                class="nav-tab <?php echo $active_tab === 'generator' ? 'nav-tab-active' : ''; ?> <?php echo !$is_configured ? 'nav-tab-disabled' : ''; ?>"
                <?php echo !$is_configured ? 'style="opacity: 0.5; cursor: not-allowed;" onclick="return false;"' : ''; ?>>
                Shortcode
                Generator<?php echo !$is_configured ? ' <span style="font-size: 0.8em;">(gesperrt)</span>' : ''; ?>
            </a>
            <a href="?page=kursorganizer-settings&tab=anleitungen"
                class="nav-tab <?php echo $active_tab === 'anleitungen' ? 'nav-tab-active' : ''; ?>">
                Anleitungen
            </a>
            <a href="?page=kursorganizer-settings&tab=changelog"
                class="nav-tab <?php echo $active_tab === 'changelog' ? 'nav-tab-active' : ''; ?>">
                Changelog
            </a>
        </h2>

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

        // Display the appropriate tab content
        if ($active_tab === 'generator') {
            kursorganizer_generator_tab_content();
        } elseif ($active_tab === 'anleitungen') {
            kursorganizer_anleitungen_tab_content();
        } elseif ($active_tab === 'changelog') {
            kursorganizer_changelog_tab_content();
        } else {
            kursorganizer_settings_tab_content();
        }
        ?>
    </div>
<?php
}

// Settings tab content
function kursorganizer_settings_tab_content()
{
    $is_configured = kursorganizer_is_configured();

    // Check if there's a validation error
    $has_validation_error = false;
    $settings_errors = get_settings_errors('kursorganizer_messages');
    foreach ($settings_errors as $error) {
        if ($error['code'] === 'org_id_mismatch' || $error['code'] === 'org_id_api_error' || $error['code'] === 'org_id_validation_error' || $error['code'] === 'url_missing_build') {
            $has_validation_error = true;
            break;
        }
    }

    // Only disable save button if plugin is NOT configured
    // If plugin is already configured, allow saving other settings (like debug mode, CSS, etc.)
    $disable_save_button = !$is_configured;

    $options = get_option('kursorganizer_settings', array());
    $main_app_url = isset($options['main_app_url']) ? trim($options['main_app_url']) : '';
    $ko_organization_id = isset($options['ko_organization_id']) ? trim($options['ko_organization_id']) : '';
    $is_first_time = empty($main_app_url) && empty($ko_organization_id);
?>

    <!-- Initial Configuration Notice -->
    <?php if ($is_first_time): ?>
        <div class="card" style="max-width: 800px; margin-bottom: 20px; border-left: 4px solid #2271b1;">
            <h2 style="margin-top: 0;">Willkommen bei KursOrganizer X!</h2>
            <p>Bevor Sie das Plugin verwenden können, müssen Sie zunächst die folgenden beiden Werte konfigurieren:</p>
            <ol>
                <li><strong>KursOrganizer Web-App URL:</strong> Die URL Ihrer KursOrganizer Web-App</li>
                <li><strong>KursOrganizer Organization ID:</strong> Die Organization ID Ihrer Schwimmschule</li>
            </ol>
            <p><strong>Nach der erfolgreichen Konfiguration werden alle weiteren Einstellungen freigeschaltet.</strong></p>
            <p style="margin-top: 15px; padding: 10px; background: #e7f3ff; border-left: 3px solid #2271b1;">
                <strong>Hinweis:</strong> Falls Ihnen diese Werte nicht bekannt sind, wenden Sie sich bitte an <a
                    href="mailto:support@kursorganizer.com">support@kursorganizer.com</a>.
            </p>
        </div>
    <?php elseif ($has_validation_error): ?>
        <div class="card" style="max-width: 800px; margin-bottom: 20px; border-left: 4px solid #d63638;">
            <h2 style="margin-top: 0; color: #d63638;">⚠️ Konfigurationsfehler</h2>
            <p><strong>Die URL und die Organization ID stimmen nicht überein.</strong></p>
            <p>Bitte korrigieren Sie die Einstellungen, bevor Sie fortfahren können. Alle anderen Funktionen sind gesperrt, bis
                die Konfiguration korrekt ist.</p>
        </div>
    <?php endif; ?>

    <!-- URL Configuration Form -->
    <div class="card" style="max-width: 800px; margin-bottom: 20px;">
        <form action="options.php" method="post" id="kursorganizer-settings-form">
            <?php
            settings_fields('kursorganizer_settings');

            // Render sections manually to control visibility
            global $wp_settings_sections, $wp_settings_fields;

            $page = 'kursorganizer-settings';

            if (isset($wp_settings_sections[$page])) {
                foreach ((array) $wp_settings_sections[$page] as $section) {
                    // Skip sections that should be hidden when not configured
                    if (!$is_configured && $section['id'] !== 'kursorganizer_main_section') {
                        continue;
                    }

                    // Skip security section (temporarily disabled)
                    if ($section['id'] === 'kursorganizer_security_section') {
                        continue;
                    }

                    if ($section['title']) {
                        echo "<h2>{$section['title']}</h2>\n";
                    }

                    if ($section['callback']) {
                        call_user_func($section['callback'], $section);
                    }

                    if (!isset($wp_settings_fields) || !isset($wp_settings_fields[$page]) || !isset($wp_settings_fields[$page][$section['id']])) {
                        continue;
                    }

                    echo '<table class="form-table" role="presentation">';
                    do_settings_fields($page, $section['id']);
                    echo '</table>';
                }
            }
            ?>
            <?php if (!$is_configured): ?>
                <div style="padding: 15px; background: #f0f0f1; border-left: 4px solid #2271b1; margin-top: 20px;">
                    <p style="margin: 0;"><strong>Weitere Einstellungen werden nach erfolgreicher Konfiguration
                            freigeschaltet.</strong></p>
                </div>
            <?php endif; ?>
            <p class="submit">
                <?php submit_button('Speichern', 'primary', 'submit', false, array('id' => 'kursorganizer-submit-btn', 'disabled' => $disable_save_button)); ?>
            </p>
        </form>
    </div>
    <?php if ($has_validation_error && !$is_configured): ?>
        <script>
            jQuery(document).ready(function($) {
                // Only disable fields if plugin is not configured AND there's a validation error
                // Disable all form fields except URL and Organization ID
                $('#kursorganizer-settings-form input:not([name*="main_app_url"]):not([name*="ko_organization_id"]), #kursorganizer-settings-form select, #kursorganizer-settings-form textarea')
                    .prop('disabled', true).css('opacity', '0.6');

                // Re-enable fields when URL or Organization ID changes
                $('input[name="kursorganizer_settings[main_app_url]"], input[name="kursorganizer_settings[ko_organization_id]"]')
                    .on('input change', function() {
                        // Re-enable all fields temporarily
                        $('#kursorganizer-settings-form input, #kursorganizer-settings-form select, #kursorganizer-settings-form textarea')
                            .prop('disabled', false).css('opacity', '1');
                        $('#kursorganizer-submit-btn').prop('disabled', false);
                    });
            });
        </script>
    <?php endif; ?>

    <script>
        jQuery(document).ready(function($) {
            // Ensure save button is enabled if plugin is configured
            var urlValue = $('input[name="kursorganizer_settings[main_app_url]"]').val().trim();
            var orgIdValue = $('#ko_organization_id').val().trim();
            var isConfigured = urlValue.length > 0 && orgIdValue.length > 0;

            if (isConfigured) {
                // Enable save button if plugin is configured
                $('#kursorganizer-submit-btn').prop('disabled', false);
            }
        });
    </script>
<?php
}

// Anleitungen tab content
function kursorganizer_anleitungen_tab_content()
{
?>
    <!-- Initial Setup Guide -->
    <div class="card" style="max-width: 1200px; margin-bottom: 20px;">
        <h2>Initiales Setup</h2>
        <p>Bevor Sie das Plugin verwenden können, müssen Sie zunächst die folgenden beiden Werte konfigurieren:</p>

        <h3>Schritt 1: KursOrganizer Web-App URL eingeben</h3>
        <ol>
            <li>Gehen Sie zum Tab <strong>"Einstellungen"</strong></li>
            <li>Geben Sie im Feld <strong>"KursOrganizer Web-App URL"</strong> die vollständige URL Ihrer KursOrganizer
                Web-App ein</li>
            <li>Die URL sollte folgendem Format entsprechen: <code>https://app.ihrefirma.kursorganizer.com/build/</code>
            </li>
            <li>Beispiel: <code>https://app.schwimmschule-xyz.kursorganizer.com/build/</code></li>
        </ol>

        <h3>Schritt 2: KursOrganizer Organization ID eingeben</h3>
        <ol>
            <li>Geben Sie im Feld <strong>"KursOrganizer Organization ID"</strong> die Organization ID Ihrer Schwimmschule
                ein</li>
            <li>Die Organization ID ist eine eindeutige Kennung (UUID-Format, z.B.
                <code>123e4567-e89b-12d3-a456-426614174000</code>)
            </li>
            <li>Sie erhalten diese ID von Ihrem KursOrganizer Administrator oder aus dem KursOrganizer Backend</li>
        </ol>

        <h3>Schritt 3: Verbindung testen</h3>
        <ol>
            <li>Klicken Sie auf den Button <strong>"Verbindung testen"</strong> neben dem Organization ID Feld</li>
            <li>Das Plugin prüft automatisch, ob die URL und die Organization ID zusammenpassen</li>
            <li>Bei erfolgreicher Validierung sehen Sie eine grüne Erfolgsmeldung</li>
            <li>Bei Fehlern wird das entsprechende Feld rot markiert und eine Fehlermeldung angezeigt</li>
        </ol>

        <h3>Schritt 4: Einstellungen speichern</h3>
        <ol>
            <li>Klicken Sie auf den Button <strong>"Speichern"</strong></li>
            <li>Das Plugin validiert die Eingaben automatisch beim Speichern</li>
            <li>Wenn URL und Organization ID nicht übereinstimmen, werden die Einstellungen nicht gespeichert und eine
                Fehlermeldung angezeigt</li>
            <li>Nach erfolgreicher Konfiguration werden alle weiteren Einstellungen freigeschaltet</li>
        </ol>

        <div style="padding: 15px; background: #fff3cd; border-left: 4px solid #2271b1; margin-top: 20px;">
            <p style="margin: 0;"><strong>Wichtig:</strong> Die URL und die Organization ID müssen zusammenpassen, damit das
                Plugin funktioniert. Diese Validierung dient der Sicherheit und stellt sicher, dass nur die richtige
                Schwimmschule eingebunden wird.</p>
        </div>

        <div style="padding: 15px; background: #e7f3ff; border-left: 4px solid #2271b1; margin-top: 20px;">
            <p style="margin: 0;"><strong>Hinweis:</strong> Falls Ihnen die KursOrganizer Web-App URL oder die KursOrganizer
                Organization ID nicht bekannt sind, wenden Sie sich bitte an <a
                    href="mailto:support@kursorganizer.com">support@kursorganizer.com</a>.</p>
        </div>

        <h3>Was passiert nach dem Setup?</h3>
        <p>Nach erfolgreicher Konfiguration können Sie:</p>
        <ul>
            <li>Den <strong>Shortcode Generator</strong> verwenden, um Shortcodes zu erstellen</li>
            <li>Weitere Einstellungen wie Debug-Modus und CSS-Anpassungen konfigurieren</li>
            <li>Den Shortcode <code>[kursorganizer_iframe]</code> in Ihren WordPress-Seiten verwenden</li>
        </ul>
    </div>

    <!-- Shortcode Examples -->
    <div class="card" style="max-width: 1200px;">
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
        <p>Filtert Kurse nach bestimmten Tagen (ODER-Verknüpfung):</p>
        <code>[kursorganizer_iframe dayfilter="Montag,Dienstag,Mittwoch"]</code>
        <p class="description">Verwenden Sie deutsche Wochentagsnamen: Montag, Dienstag, Mittwoch, Donnerstag, Freitag,
            Samstag, Sonntag. Mehrere Tage werden komma-separiert angegeben.</p>

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
    <div class="card" style="max-width: 1200px; margin-top: 20px;">
        <h2>CSS-Anpassungen</h2>
        <p>Sie können das Aussehen des KursOrganizer iFrames über eine externe CSS-Datei anpassen.</p>

        <h3>So binden Sie eine CSS-Datei ein</h3>
        <ol>
            <li><strong>Beispiel-CSS testen:</strong> Aktivieren Sie die Option "Beispiel-CSS aktivieren" in den
                Einstellungen, um die mitgelieferte Beispiel-CSS-Datei zu testen. Sie können diese auch <a
                    href="<?php echo esc_url(KURSORGANIZER_PLUGIN_URL . 'assets/css/external-css-example.css'); ?>"
                    download="external-css-example.css">herunterladen</a> und als Vorlage verwenden.
                <strong>Hinweis:</strong> Die CSS wird über einen PHP-Endpoint geladen, um Chrome-Kompatibilität zu
                gewährleisten.
            </li>
            <li><strong>CSS-Datei erstellen:</strong> Erstellen Sie eine CSS-Datei mit Ihren Anpassungen und laden Sie
                diese auf Ihren Server hoch.</li>
            <li><strong>Öffentliche URL verwenden:</strong> Stellen Sie sicher, dass die CSS-Datei über eine öffentliche
                URL erreichbar ist.</li>
            <li><strong>URL in Einstellungen eintragen:</strong> Deaktivieren Sie die Beispiel-CSS und geben Sie die
                vollständige URL zu Ihrer CSS-Datei im
                Feld "CSS-Datei URL" oben in den Einstellungen ein.</li>
            <li><strong>Speichern:</strong> Klicken Sie auf "Speichern", damit die Änderungen wirksam werden.</li>
        </ol>

        <h3>Beispiel-URL</h3>
        <p>Die URL sollte folgendem Format entsprechen:</p>
        <code>https://www.ihre-domain.de/wp-content/themes/theme-name/custom-kursorganizer.css</code>

        <hr>

        <h3>Wichtige Hinweise</h3>
        <ul>
            <li><strong>Öffentliche Zugänglichkeit:</strong> Die CSS-Datei muss öffentlich über HTTP/HTTPS erreichbar
                sein</li>
            <li><strong>CORS-Header:</strong> Die CSS-Datei muss CORS-Header erlauben, damit sie vom iFrame geladen
                werden kann</li>
            <li><strong>Ant Design:</strong> Die App verwendet Ant Design. Sie können Ant Design Komponenten-Klassen
                direkt stylen (z.B. <code>.ant-btn-primary</code>, <code>.ant-card</code>, <code>.ant-table</code>)</li>
            <li><strong>CSS-Spezifität:</strong> Verwenden Sie ausreichend spezifische Selektoren, um die
                Standard-Styles zu überschreiben</li>
            <li><strong>Performance:</strong> Große CSS-Dateien können die Ladezeit beeinträchtigen</li>
        </ul>
    </div>
    <?php
}

// Shortcode Generator tab content
function kursorganizer_generator_tab_content()
{
    $is_configured = kursorganizer_is_configured();

    if (!$is_configured) {
        $options = get_option('kursorganizer_settings', array());
        $main_app_url = isset($options['main_app_url']) ? trim($options['main_app_url']) : '';
        $ko_organization_id = isset($options['ko_organization_id']) ? trim($options['ko_organization_id']) : '';
        $is_first_time = empty($main_app_url) && empty($ko_organization_id);
    ?>
        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <?php if ($is_first_time): ?>
                <div style="padding: 15px; background: #fff3cd; border-left: 4px solid #2271b1; margin-bottom: 20px;">
                    <h2 style="margin-top: 0;">Initialkonfiguration erforderlich</h2>
                    <p><strong>Bevor Sie den Shortcode Generator verwenden können, müssen Sie zunächst die Plugin-Einstellungen
                            konfigurieren.</strong></p>
                    <p>Bitte gehen Sie zum Tab <strong>"Einstellungen"</strong> und geben Sie dort die folgenden Werte ein:</p>
                    <ol>
                        <li><strong>KursOrganizer Web-App URL</strong></li>
                        <li><strong>KursOrganizer Organization ID</strong></li>
                    </ol>
                    <p>Nach erfolgreicher Konfiguration können Sie den Shortcode Generator verwenden.</p>
                </div>
            <?php else: ?>
                <div style="padding: 15px; background: #fff3cd; border-left: 4px solid #d63638; margin-bottom: 20px;">
                    <h2 style="margin-top: 0; color: #d63638;">⚠️ Konfigurationsfehler</h2>
                    <p><strong>Die URL und die Organization ID stimmen nicht überein oder die Konfiguration ist
                            unvollständig.</strong></p>
                    <p>Bitte korrigieren Sie die Einstellungen im Tab <strong>"Einstellungen"</strong>, bevor Sie den Shortcode
                        Generator verwenden können.</p>
                    <p><strong>Konfigurierte URL:</strong> <code><?php echo esc_html($main_app_url ?: 'Nicht gesetzt'); ?></code>
                    </p>
                    <p><strong>Konfigurierte Organization ID:</strong>
                        <code><?php echo esc_html($ko_organization_id ?: 'Nicht gesetzt'); ?></code>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    <?php
        return;
    }

    // Fetch data from API
    $course_types = KursOrganizer_API::get_course_types();
    $locations = KursOrganizer_API::get_locations();
    $categories = KursOrganizer_API::get_course_categories();
    $instructors = KursOrganizer_API::get_instructors();

    // Check for errors
    $has_errors = false;
    $error_message = '';
    $debug_info = '';

    // Get debug information
    $api_url = KursOrganizer_API::get_api_url();
    $origin = KursOrganizer_API::get_origin();
    $options = get_option('kursorganizer_settings');
    $web_app_url = isset($options['main_app_url']) ? $options['main_app_url'] : '';

    $debug_info = sprintf(
        '<strong>Debug-Informationen:</strong><br>' .
            'API-URL: <code>%s</code><br>' .
            'Origin: <code>%s</code><br>' .
            'Web-App URL: <code>%s</code>',
        esc_html($api_url),
        esc_html($origin),
        esc_html($web_app_url ?: '(nicht konfiguriert)')
    );

    if (is_wp_error($course_types)) {
        $has_errors = true;
        $error_message = 'Fehler beim Laden der Kurstypen: ' . $course_types->get_error_message();
    } elseif (is_wp_error($locations)) {
        $has_errors = true;
        $error_message = 'Fehler beim Laden der Standorte: ' . $locations->get_error_message();
    } elseif (is_wp_error($categories)) {
        $has_errors = true;
        $error_message = 'Fehler beim Laden der Kategorien: ' . $categories->get_error_message();
    } elseif (is_wp_error($instructors)) {
        $has_errors = true;
        $error_message = 'Fehler beim Laden der Kursleiter: ' . $instructors->get_error_message();
    }
    ?>
    <div class="card" style="max-width: 800px; margin-top: 20px;">
        <h2>Shortcode Generator</h2>
        <p>Wählen Sie die gewünschten Optionen aus und generieren Sie automatisch den passenden Shortcode.</p>

        <?php if ($has_errors): ?>
            <div class="notice notice-error">
                <p><strong>Fehler:</strong> <?php echo wp_kses_post($error_message); ?></p>
                <p>Bitte stellen Sie sicher, dass die "KursOrganizer Web-App URL" in den Einstellungen korrekt konfiguriert ist.
                </p>
                <div style="margin-top: 15px; padding: 10px; background: #f0f0f1; border-left: 4px solid #d63638;">
                    <?php echo $debug_info; ?>
                </div>
                <p style="margin-top: 15px;">
                    <strong>Tipps für lokale Entwicklung:</strong><br>
                    • Stelle sicher, dass deine lokale API unter <code><?php echo esc_html($api_url); ?></code> läuft<br>
                    • Die API muss die Origin <code><?php echo esc_html($origin); ?></code> akzeptieren<br>
                    • Prüfe die Browser-Konsole (F12) für weitere Details<br>
                    • Prüfe die WordPress-Debug-Logs (normalerweise in <code>wp-content/debug.log</code>) für detaillierte
                    API-Requests<br>
                    • Teste die Query direkt im GraphQL Playground: <a href="<?php echo esc_url($api_url); ?>"
                        target="_blank"><?php echo esc_html($api_url); ?></a>
                </p>
                <p style="margin-top: 15px;">
                    <strong>Test-Query für GraphQL Playground:</strong><br>
                    <textarea readonly
                        style="width: 100%; height: 100px; font-family: monospace; font-size: 12px; padding: 10px; background: #f0f0f1;">
query GetCompany {
  companyPublic {
    name
    host
    koOrganization {
      id
    }
  }
}
                    </textarea><br>
                    <strong>HTTP Headers für GraphQL Playground:</strong><br>
                    <textarea readonly
                        style="width: 100%; height: 80px; font-family: monospace; font-size: 12px; padding: 10px; background: #f0f0f1;">
{
  "Origin": "<?php echo esc_html($origin); ?>",
  "x-application-type": "end-user-app"
}
                    </textarea>
                </p>
            </div>
        <?php else: ?>

            <form id="kursorganizer-generator-form">
                <table class="form-table" role="presentation">
                    <!-- Course Types -->
                    <tr>
                        <th scope="row"><label for="generator-coursetypes">Kurstypen</label></th>
                        <td>
                            <select id="generator-coursetypes" name="coursetypes[]" multiple
                                style="min-height: 150px; width: 100%; max-width: 400px;">
                                <?php foreach ($course_types as $type): ?>
                                    <?php if ($type['showInWeb']): ?>
                                        <option value="<?php echo esc_attr($type['id']); ?>">
                                            <?php echo esc_html($type['name']); ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                Mehrfachauswahl: Halten Sie Strg (Windows) oder Cmd (Mac) gedrückt, um mehrere Kurstypen
                                auszuwählen.
                            </p>
                        </td>
                    </tr>

                    <!-- Locations -->
                    <tr>
                        <th scope="row"><label for="generator-location">Standort</label></th>
                        <td>
                            <select id="generator-location" name="location">
                                <option value="">-- Alle Standorte --</option>
                                <?php foreach ($locations as $location): ?>
                                    <option value="<?php echo esc_attr($location['id']); ?>">
                                        <?php echo esc_html($location['city'] . ' - ' . $location['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Optional: Filtern nach einem bestimmten Standort</p>
                        </td>
                    </tr>

                    <!-- City -->
                    <tr>
                        <th scope="row"><label for="generator-city">Stadt</label></th>
                        <td>
                            <input type="text" id="generator-city" name="city" class="regular-text" placeholder="z.B. Berlin">
                            <p class="description">
                                Optional: Filtern nach Stadt als Text-String (nur wenn kein Standort ausgewählt).<br>
                                Die Suche ist case-insensitive und unterstützt Teilstrings (z.B. "Berlin" findet auch
                                "Berlin-Mitte").<br>
                                <strong>Hinweis:</strong> Hier wird mit dem Stadtnamen gearbeitet, nicht mit einer ID.
                            </p>
                        </td>
                    </tr>

                    <!-- Categories -->
                    <tr>
                        <th scope="row"><label for="generator-category">Kategorie</label></th>
                        <td>
                            <select id="generator-category" name="category">
                                <option value="">-- Alle Kategorien --</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo esc_attr($category['id']); ?>">
                                        <?php echo esc_html($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Optional: Filtern nach Kurskategorie</p>
                        </td>
                    </tr>

                    <!-- Instructors -->
                    <tr>
                        <th scope="row"><label for="generator-instructor">Kursleiter</label></th>
                        <td>
                            <select id="generator-instructor" name="instructor">
                                <option value="">-- Alle Kursleiter --</option>
                                <?php foreach ($instructors as $instructor): ?>
                                    <option value="<?php echo esc_attr($instructor['id']); ?>">
                                        <?php echo esc_html($instructor['firstname'] . ' ' . $instructor['lastname']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Optional: Filtern nach Kursleiter</p>
                        </td>
                    </tr>

                    <!-- Day Filter -->
                    <tr>
                        <th scope="row"><label>Wochentage</label></th>
                        <td>
                            <fieldset id="day-filter-fieldset">
                                <label><input type="checkbox" name="days[]" id="day-montag" value="Montag"> Montag</label><br>
                                <label><input type="checkbox" name="days[]" id="day-dienstag" value="Dienstag">
                                    Dienstag</label><br>
                                <label><input type="checkbox" name="days[]" id="day-mittwoch" value="Mittwoch">
                                    Mittwoch</label><br>
                                <label><input type="checkbox" name="days[]" id="day-donnerstag" value="Donnerstag">
                                    Donnerstag</label><br>
                                <label><input type="checkbox" name="days[]" id="day-freitag" value="Freitag">
                                    Freitag</label><br>
                                <label><input type="checkbox" name="days[]" id="day-samstag" value="Samstag">
                                    Samstag</label><br>
                                <label><input type="checkbox" name="days[]" id="day-sonntag" value="Sonntag"> Sonntag</label>
                            </fieldset>
                            <p class="description">
                                Optional: Nur Kurse an bestimmten Wochentagen anzeigen (ODER-Verknüpfung).<br>
                                Mehrere Tage können ausgewählt werden. Es werden alle Kurse angezeigt, die an einem der
                                ausgewählten Tage stattfinden.<br>
                                <strong>Format:</strong> Deutsche Wochentagsnamen (Montag, Dienstag, etc.), komma-separiert.
                            </p>
                        </td>
                    </tr>

                    <!-- Show Filter Menu -->
                    <tr>
                        <th scope="row"><label for="generator-showfiltermenu">Filtermenü anzeigen</label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="generator-showfiltermenu" name="showfiltermenu" checked>
                                Filtermenü im iFrame anzeigen
                            </label>
                            <p class="description">Wenn deaktiviert, wird das Filtermenü im iFrame ausgeblendet</p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="button" id="generate-shortcode-btn" class="button button-primary">
                        Shortcode generieren
                    </button>
                    <button type="button" id="reset-form-btn" class="button" style="margin-left: 10px;">
                        Felder zurücksetzen
                    </button>
                    <button type="button" id="clear-cache-btn" class="button" style="margin-left: 10px;">
                        Cache leeren
                    </button>
                </p>
            </form>

            <div id="generated-shortcode-container"
                style="display: none; margin-top: 20px; padding: 15px; background: #f0f0f1; border-left: 4px solid #2271b1;">
                <h3>Generierter Shortcode:</h3>
                <textarea id="generated-shortcode" readonly
                    style="width: 100%; height: 80px; font-family: monospace; font-size: 13px; padding: 10px;"></textarea>
                <p>
                    <button type="button" id="copy-shortcode-btn" class="button">
                        In Zwischenablage kopieren
                    </button>
                    <span id="copy-success-message" style="color: green; margin-left: 10px; display: none;">✓ Kopiert!</span>
                </p>
                <p class="description">
                    Fügen Sie diesen Shortcode in eine beliebige WordPress-Seite oder einen Beitrag ein.<br>
                    <strong>Tipp:</strong> In WordPress Gutenberg wird der Shortcode automatisch als "Shortcode"-Block erkannt.
                    Das ist normal und funktioniert korrekt. Falls Sie ihn als normalen Text benötigen, können Sie den Block-Typ
                    nach dem Einfügen ändern.
                </p>
            </div>

        <?php endif; ?>
    </div>
<?php
}

// Changelog tab content
function kursorganizer_changelog_tab_content()
{
    $changelog_file = KURSORGANIZER_PLUGIN_DIR . 'CHANGELOG.md';

    if (!file_exists($changelog_file)) {
        echo '<div class="card" style="max-width: 1200px;"><p>Changelog-Datei nicht gefunden.</p></div>';
        return;
    }

    $changelog_content = file_get_contents($changelog_file);

    if (empty($changelog_content)) {
        echo '<div class="card" style="max-width: 1200px;"><p>Changelog ist leer.</p></div>';
        return;
    }

    // Parse Markdown zu HTML (einfache Konvertierung)
    $html_content = kursorganizer_parse_changelog_markdown($changelog_content);
?>
    <div class="card" style="max-width: 1200px;">
        <h2>Changelog</h2>
        <div class="kursorganizer-changelog-content" style="line-height: 1.6;">
            <?php echo wp_kses_post($html_content); ?>
        </div>
    </div>
<?php
}

/**
 * Konvertiert Markdown-Changelog zu HTML
 * 
 * @param string $markdown Der Markdown-Inhalt
 * @return string HTML-formatierter Inhalt
 */
function kursorganizer_parse_changelog_markdown($markdown)
{
    $lines = explode("\n", $markdown);
    $html = '';
    $in_list = false;
    $current_section = '';

    foreach ($lines as $line) {
        $line = rtrim($line);

        // Leere Zeile
        if (empty($line)) {
            if ($in_list) {
                $html .= '</ul>';
                $in_list = false;
            }
            continue;
        }

        // Hauptüberschrift (# Changelog)
        if (preg_match('/^# (.+)$/', $line, $matches)) {
            if ($in_list) {
                $html .= '</ul>';
                $in_list = false;
            }
            continue; // Überspringen, da wir bereits eine Überschrift haben
        }

        // Versionsüberschrift (## [1.0.5] - 2025-01-XX)
        if (preg_match('/^## \[(.+?)\](.*)$/', $line, $matches)) {
            if ($in_list) {
                $html .= '</ul>';
                $in_list = false;
            }
            $version = esc_html($matches[1]);
            $date = esc_html(trim($matches[2]));
            $html .= '<h2 style="margin-top: 30px; padding-bottom: 10px; border-bottom: 2px solid #2271b1; color: #2271b1;">Version ' . $version;
            if (!empty($date)) {
                $html .= ' <span style="font-size: 0.8em; font-weight: normal; color: #666;">' . $date . '</span>';
            }
            $html .= '</h2>';
            continue;
        }

        // Unterüberschrift (### Added, ### Changed, etc.)
        if (preg_match('/^### (.+)$/', $line, $matches)) {
            if ($in_list) {
                $html .= '</ul>';
                $in_list = false;
            }
            $section = esc_html($matches[1]);
            $html .= '<h3 style="margin-top: 20px; margin-bottom: 10px; color: #333;">' . $section . '</h3>';
            continue;
        }

        // Listenpunkt (- Item)
        if (preg_match('/^- (.+)$/', $line, $matches)) {
            if (!$in_list) {
                $html .= '<ul style="margin-left: 20px; margin-bottom: 15px; list-style-type: disc;">';
                $in_list = true;
            }
            $item = esc_html($matches[1]);
            // "N/A" durch "Keine" ersetzen
            if ($item === 'N/A') {
                $item = '<em>Keine</em>';
            }
            // Code-Formatierung für Backticks
            $item = preg_replace('/`(.+?)`/', '<code style="background: #f0f0f1; padding: 2px 6px; border-radius: 3px; font-family: monospace;">$1</code>', $item);
            $html .= '<li style="margin-bottom: 5px;">' . $item . '</li>';
            continue;
        }

        // Normaler Text (sollte nicht vorkommen, aber falls doch)
        if ($in_list) {
            $html .= '</ul>';
            $in_list = false;
        }
        $html .= '<p>' . esc_html($line) . '</p>';
    }

    // Liste schließen, falls noch offen
    if ($in_list) {
        $html .= '</ul>';
    }

    return $html;
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
    $mainAppUrl = isset($options['main_app_url']) ? trim($options['main_app_url']) : '';
    $ko_organization_id = isset($options['ko_organization_id']) ? trim($options['ko_organization_id']) : '';

    // Check if plugin is configured
    if (empty($mainAppUrl) || empty($ko_organization_id)) {
        // Plugin not configured - show message for admins, nothing for regular users
        if (current_user_can('manage_options')) {
            return '<div style="padding: 15px; background: #fff3cd; border-left: 4px solid #2271b1; margin: 20px 0;"><strong>ℹ️ Plugin nicht konfiguriert:</strong> Bitte konfigurieren Sie das Plugin im WordPress-Admin unter "KursOrganizer X" → "Einstellungen". Geben Sie die KursOrganizer Web-App URL und die Organization ID ein.</div>';
        } else {
            // For non-admin users, show nothing
            return '';
        }
    }

    // Check if validation is required and if it passes
    $validation_result = kursorganizer_validate_organization_id($mainAppUrl, $ko_organization_id);
    if ($validation_result === false) {
        // Validation failed - don't render iframe
        if (current_user_can('manage_options')) {
            return '<div style="padding: 15px; background: #fff3cd; border-left: 4px solid #d63638; margin: 20px 0;"><strong style="color: #d63638;">⚠️ FEHLER:</strong> Die URL und die Organization ID stimmen nicht überein. Bitte korrigieren Sie die Einstellungen im WordPress-Admin unter "KursOrganizer X" → "Einstellungen".</div>';
        } else {
            // For non-admin users, show nothing or a generic message
            return '';
        }
    }

    // CSS-Parameter aus Settings lesen
    // Prüfe sowohl Boolean true als auch String "1" für Checkbox-Werte
    $use_example_css = isset($options['use_example_css']) && ($options['use_example_css'] === true || $options['use_example_css'] === '1' || $options['use_example_css'] === 1);
    $custom_css_url = isset($options['custom_css_url']) ? trim($options['custom_css_url']) : '';

    // Aktuelle URL der Elternseite (ohne Preview-Parameter)
    $permalink = get_permalink();
    // Remove preview parameters to avoid infinite loops
    $permalink = remove_query_arg(['preview_id', 'preview_nonce', 'preview'], $permalink);
    $parentUrl = urlencode($permalink);

    // Erstellen der iFrame-URL mit den Parametern
    // Cache-Busting: Füge Versions-Parameter hinzu, um Browser-Caching zu umgehen
    // Kombiniere Plugin-Version mit manuell inkrementierbarem Cache-Buster
    // Der Cache-Buster kann über "Cache leeren" Button aktualisiert werden
    $cache_buster_value = get_option('kursorganizer_cache_buster', 1);
    $cache_buster = '&_v=' . KURSORGANIZER_CACHE_VERSION . '&_cb=' . $cache_buster_value;

    // Baue URL-Parameter nur hinzu, wenn sie nicht leer sind
    $url_params = array();
    $url_params[] = "parentUrl=" . $parentUrl;

    if (!empty($atts['city'])) {
        $url_params[] = "city=" . urlencode($atts['city']);
    }
    if (!empty($atts['instructorid'])) {
        $url_params[] = "instructorId=" . urlencode($atts['instructorid']);
    }
    if (!empty($atts['coursetypeid'])) {
        $url_params[] = "courseTypeId=" . urlencode($atts['coursetypeid']);
    }
    if (!empty($atts['coursetypeids'])) {
        $url_params[] = "courseTypeIds=" . urlencode($atts['coursetypeids']);
    }
    if (!empty($atts['locationid'])) {
        $url_params[] = "locationId=" . urlencode($atts['locationid']);
    }
    if (!empty($atts['dayfilter'])) {
        // Don't urlencode dayfilter to keep comma-separated format readable
        $url_params[] = "dayFilter=" . $atts['dayfilter'];
    }
    if (!empty($atts['coursecategoryid'])) {
        $url_params[] = "courseCategoryId=" . urlencode($atts['coursecategoryid']);
    }
    // showFilterMenu wird immer übergeben, da es einen Standardwert hat
    $url_params[] = "showFilterMenu=" . urlencode($atts['showfiltermenu']);

    // Cache-Buster hinzufügen
    $url_params[] = "_v=" . KURSORGANIZER_CACHE_VERSION;
    $url_params[] = "_cb=" . $cache_buster_value;

    // CSS-Parameter hinzufügen (Beispiel-CSS hat Priorität)
    $example_css_url = '';
    if ($use_example_css) {
        // Verwende PHP-Endpoint statt direkter CSS-Datei für bessere CORS-Unterstützung
        // Der PHP-Endpoint setzt automatisch alle notwendigen CORS-Header
        $example_css_url = KURSORGANIZER_PLUGIN_URL . 'assets/css/external-css-example.php';
        $url_params[] = "customCssUrl=" . urlencode($example_css_url);
    } elseif (!empty($custom_css_url)) {
        $url_params[] = "customCssUrl=" . urlencode($custom_css_url);
    }

    // MaxWidth-Parameter hinzufügen
    $max_width = isset($options['max_width']) ? trim($options['max_width']) : '1200px';
    // Wenn leer, setze auf Default
    if (empty($max_width)) {
        $max_width = '1200px';
    }
    // Validiere und formatiere maxWidth-Wert
    $max_width = kursorganizer_format_max_width($max_width);
    $url_params[] = "maxWidth=" . urlencode($max_width);

    $iframe_src = esc_url($mainAppUrl) . "?" . implode("&", $url_params);

    // Get debug mode setting
    $debug_mode = isset($options['debug_mode']) ? $options['debug_mode'] : false;

    // Eindeutige ID für jeden iFrame generieren
    static $iframe_counter = 0;
    $iframe_counter++;
    $unique_id = 'kursorganizer-iframe-' . $iframe_counter;

    // Inline-Style für das iframe: Verwende max-width basierend auf der Einstellung
    // Das erlaubt dem iframe, die eingestellte maximale Breite anzunehmen
    $iframe_style = 'width: 1px; min-width: 100%; max-width: ' . esc_attr($max_width) . ';';

    // HTML für das iFrame mit eindeutiger ID und gemeinsamer CSS-Klasse
    $iframe_html = '<iframe id="' . $unique_id . '" class="kursorganizer-iframe" frameborder="0" style="' . $iframe_style . '" src="' . $iframe_src . '"></iframe>';

    // Optional: Platzhalter für Callback-Informationen (nur im Debug-Modus)
    $callback_html = $debug_mode ? '<p id="kursorganizer-callback-' . $iframe_counter . '"></p>' : '';

    // Debug-Informationen für CSS-URL (nur im Debug-Modus)
    $debug_info = '';
    if ($debug_mode) {
        $css_info = 'Keine CSS-URL';
        $css_test_link = '';
        $mixed_content_warning = '';

        // Prüfe auf Mixed Content Problem
        $iframe_is_https = strpos($mainAppUrl, 'https://') === 0;
        $css_is_http = false;
        $actual_css_url = '';

        if ($use_example_css) {
            $actual_css_url = $example_css_url;
            $css_is_http = strpos($actual_css_url, 'http://') === 0;
            $css_info = 'Beispiel-CSS aktiviert: ' . esc_html($actual_css_url);
            $css_test_link = '<br><a href="' . esc_url($actual_css_url) . '" target="_blank">CSS-Datei direkt testen (sollte im Browser öffnen)</a>';
        } elseif (!empty($custom_css_url)) {
            $actual_css_url = $custom_css_url;
            $css_is_http = strpos($actual_css_url, 'http://') === 0;
            $css_info = 'Benutzerdefinierte CSS-URL: ' . esc_html($actual_css_url);
            $css_test_link = '<br><a href="' . esc_url($actual_css_url) . '" target="_blank">CSS-Datei direkt testen (sollte im Browser öffnen)</a>';
        }

        // Mixed Content Warnung
        if ($iframe_is_https && $css_is_http) {
            $mixed_content_warning = '<div style="margin-top: 10px; padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107; color: #856404;">' .
                '<strong>⚠️ MIXED CONTENT WARNUNG!</strong><br>' .
                'Die Web-App läuft über HTTPS, aber die CSS-Datei wird über HTTP geladen.<br>' .
                'Browser blockieren aus Sicherheitsgründen HTTP-Ressourcen auf HTTPS-Seiten!<br><br>' .
                '<strong>Lösungen:</strong><br>' .
                '• <strong>Für lokale Entwicklung:</strong> Installieren Sie ein SSL-Zertifikat für ' . esc_html(parse_url($actual_css_url, PHP_URL_HOST)) . '<br>' .
                '• <strong>Für Produktion:</strong> Stellen Sie sicher, dass Ihre WordPress-Seite über HTTPS läuft<br>' .
                '• <strong>Schnelltest:</strong> Kopieren Sie die CSS-Datei auf den HTTPS-Server<br><br>' .
                '<strong>Befehl für lokales SSL (macOS):</strong><br>' .
                '<code style="display: block; background: #f8f9fa; padding: 5px; margin-top: 5px;">brew install mkcert<br>mkcert -install<br>mkcert ' . esc_html(parse_url($actual_css_url, PHP_URL_HOST)) . '</code>' .
                '</div>';
        }

        // MaxWidth Debug-Info
        $max_width_debug = isset($options['max_width']) ? esc_html($options['max_width']) : 'nicht gesetzt';
        $formatted_max_width = kursorganizer_format_max_width(isset($options['max_width']) ? trim($options['max_width']) : '1200px');

        $debug_info = '<div style="padding: 10px; background: #f0f0f1; border-left: 4px solid #2271b1; margin-top: 10px;">' .
            '<strong>CSS-Debug:</strong> ' . $css_info . $css_test_link . '<br>' .
            '<strong>MaxWidth-Einstellung (roh):</strong> ' . $max_width_debug . '<br>' .
            '<strong>MaxWidth formatiert:</strong> ' . esc_html($formatted_max_width) . '<br>' .
            '<strong>MaxWidth im URL-Parameter:</strong> <code>maxWidth=' . esc_html(urlencode($formatted_max_width)) . '</code><br>' .
            '<strong>iFrame-URL:</strong> <code style="font-size: 11px; word-break: break-all;">' . esc_html($iframe_src) . '</code><br>' .
            '<strong>Option use_example_css:</strong> ' . ($use_example_css ? 'true' : 'false') . ' (Typ: ' . gettype($options['use_example_css'] ?? 'nicht gesetzt') . ')<br>' .
            '<strong>iFrame Protocol:</strong> ' . ($iframe_is_https ? 'HTTPS ✓' : 'HTTP') . '<br>' .
            '<strong>CSS Protocol:</strong> ' . ($css_is_http ? 'HTTP ⚠️' : ($actual_css_url ? 'HTTPS ✓' : 'N/A')) .
            '</div>' . $mixed_content_warning;
    }

    return $iframe_html . $callback_html . $debug_info;
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
        KURSORGANIZER_CACHE_VERSION,
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

/**
 * Füge CORS-Header für die externe CSS-Datei hinzu
 * Chrome benötigt diese Header, um die CSS-Datei aus einem iFrame zu laden
 * Wichtig: Chrome blockiert auch "Private Network Access" - daher zusätzliche Header
 */
function kursorganizer_add_cors_headers_for_css()
{
    // Prüfe ob es eine Request für die CSS-Datei handelt
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($request_uri, '/wp-content/plugins/kursorganizer-wp-plugin/assets/css/external-css-example.css') !== false) {
        // CORS-Header setzen
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Access-Control-Max-Age: 86400');

        // WICHTIG: Chrome Private Network Access (PNA) Header
        // Erlaubt Cross-Origin-Requests von öffentlichen zu privaten Domains (.local)
        header('Access-Control-Allow-Private-Network: true');

        // Content-Type für CSS
        header('Content-Type: text/css; charset=utf-8');

        // Cache-Control für bessere Performance
        header('Cache-Control: public, max-age=31536000');

        // OPTIONS-Request behandeln (CORS Preflight)
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }
}
// Verwende frühere Hooks für CORS-Header (vor init)
add_action('send_headers', 'kursorganizer_add_cors_headers_for_css', 1);
add_action('template_redirect', 'kursorganizer_add_cors_headers_for_css', 1);

/**
 * Enqueue admin scripts and styles
 */
function kursorganizer_enqueue_admin_scripts($hook)
{
    // Only load on our settings page
    if ($hook !== 'toplevel_page_kursorganizer-settings') {
        return;
    }

    // Enqueue admin CSS
    wp_enqueue_style(
        'kursorganizer-admin',
        KURSORGANIZER_PLUGIN_URL . 'assets/css/admin.css',
        array(),
        KURSORGANIZER_CACHE_VERSION
    );

    wp_enqueue_script(
        'kursorganizer-admin',
        KURSORGANIZER_PLUGIN_URL . 'assets/js/admin.js',
        array('jquery'),
        KURSORGANIZER_CACHE_VERSION,
        true
    );

    // Pass nonce and ajaxurl to JavaScript
    wp_localize_script('kursorganizer-admin', 'kursorganizerAdmin', array(
        'nonce' => wp_create_nonce('kursorganizer_admin_nonce'),
        'ajaxurl' => admin_url('admin-ajax.php'),
    ));
}
add_action('admin_enqueue_scripts', 'kursorganizer_enqueue_admin_scripts');

/**
 * AJAX handler for clearing cache
 */
function kursorganizer_clear_cache_ajax()
{
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kursorganizer_admin_nonce')) {
        wp_send_json_error('Invalid nonce');
        return;
    }

    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }

    // Clear the cache
    KursOrganizer_API::clear_cache();

    // Update cache busting version to force iframe reload
    // This increments a counter that's used in the iframe URL
    $current_buster = get_option('kursorganizer_cache_buster', 1);
    update_option('kursorganizer_cache_buster', $current_buster + 1);

    wp_send_json_success('Cache cleared successfully');
}
add_action('wp_ajax_kursorganizer_clear_cache', 'kursorganizer_clear_cache_ajax');

/**
 * AJAX handler for testing Organization ID
 */
function kursorganizer_test_org_id_ajax()
{
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kursorganizer_admin_nonce')) {
        wp_send_json_error('Invalid nonce');
        return;
    }

    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }

    // Get URL and Organization ID from POST data
    $url = isset($_POST['url']) ? sanitize_text_field($_POST['url']) : '';
    $org_id = isset($_POST['org_id']) ? sanitize_text_field($_POST['org_id']) : '';

    if (empty($url) || empty($org_id)) {
        wp_send_json_error('URL und Organization ID müssen beide angegeben werden.');
        return;
    }

    // Use the validation function logic directly
    $parsed = parse_url($url);
    if (!$parsed || !isset($parsed['scheme']) || !isset($parsed['host'])) {
        wp_send_json_error('Ungültige URL.');
        return;
    }

    // Validate and fix URL to ensure it ends with /build
    if (strpos($parsed['host'], 'kursorganizer.com') !== false) {
        $path = isset($parsed['path']) ? rtrim($parsed['path'], '/') : '';

        // If path doesn't end with /build, add it
        if ($path !== '/build') {
            $url = rtrim($url, '/') . '/build';
        }

        // Add trailing slash
        $url = trailingslashit($url);
    } else {
        // For non-kursorganizer.com domains, require /build
        $path = isset($parsed['path']) ? rtrim($parsed['path'], '/') : '';
        if ($path !== '/build') {
            wp_send_json_error('Die URL muss auf "/build" enden. Beispiel: https://app.ihrefirma.kursorganizer.com/build/');
            return;
        }
        $url = trailingslashit($url);
    }

    $origin = $parsed['scheme'] . '://' . $parsed['host'];
    if (isset($parsed['port'])) {
        $origin .= ':' . $parsed['port'];
    }

    // Determine API URL based on the input URL
    $api_url = 'https://api.kursorganizer.com/graphql';
    if (strpos($url, 'localhost') !== false || strpos($url, '127.0.0.1') !== false || strpos($url, '.local') !== false) {
        $parsed_api = parse_url($url);
        $port = isset($parsed_api['port']) ? $parsed_api['port'] : '3000';
        $scheme = isset($parsed_api['scheme']) ? $parsed_api['scheme'] : 'http';
        $host = isset($parsed_api['host']) ? $parsed_api['host'] : 'localhost';
        $api_url = $scheme . '://' . $host . ':' . $port . '/graphql';
    } elseif (strpos($url, '.stage.') !== false) {
        $api_url = 'https://api.stage.kursorganizer.com/graphql';
    }

    // Query GetCompany to get organization ID directly
    $query = 'query GetCompany {
        companyPublic {
            name
            host
            koOrganization {
                id
            }
        }
    }';

    $headers = [
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
        'Origin' => $origin,
        'x-application-type' => 'end-user-app'
    ];

    $body = json_encode([
        'query' => $query,
        'operationName' => 'GetCompany',
        'variables' => []
    ]);

    $response = wp_remote_post($api_url, [
        'headers' => $headers,
        'body' => $body,
        'timeout' => 15,
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error('API-Fehler: ' . $response->get_error_message());
        return;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    $data = json_decode($response_body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        wp_send_json_error('Fehler beim Parsen der API-Antwort. Bitte prüfen Sie, ob die URL korrekt ist.');
        return;
    }

    // Check for GraphQL errors first
    if (isset($data['errors'])) {
        $error_message = $data['errors'][0]['message'] ?? 'Unbekannter API-Fehler';

        // Provide user-friendly error messages
        if (
            strpos($error_message, 'Cannot return null for non-nullable field') !== false ||
            strpos($error_message, 'null') !== false
        ) {
            wp_send_json_error('Die eingegebene URL ist ungültig oder es wurde keine Schwimmschule für diese URL gefunden. Bitte überprüfen Sie die URL und stellen Sie sicher, dass sie korrekt ist.');
        } else {
            wp_send_json_error('API-Fehler: ' . $error_message . '. Bitte überprüfen Sie die URL.');
        }
        return;
    }

    // Check if companyPublic is null (no company found for this URL)
    if (!isset($data['data']['companyPublic']) || $data['data']['companyPublic'] === null) {
        wp_send_json_error('Für die eingegebene URL wurde keine Schwimmschule gefunden. Bitte überprüfen Sie die URL und stellen Sie sicher, dass sie korrekt ist.');
        return;
    }

    if (!isset($data['data']['companyPublic']['koOrganization']['id'])) {
        wp_send_json_error('Die Organization ID konnte nicht aus der API-Antwort extrahiert werden. Bitte prüfen Sie die URL.');
        return;
    }

    $api_org_id = $data['data']['companyPublic']['koOrganization']['id'];

    // Compare IDs (case-insensitive)
    $input_org_id_clean = strtolower(trim($org_id));
    $api_org_id_clean = strtolower(trim($api_org_id));

    if ($input_org_id_clean === $api_org_id_clean) {
        wp_send_json_success('Die Organization ID stimmt mit der URL überein.');
    } else {
        wp_send_json_error('Die Organization ID stimmt nicht mit der URL überein.');
    }
}
add_action('wp_ajax_kursorganizer_test_org_id', 'kursorganizer_test_org_id_ajax');

/**
 * Clear WordPress object cache on plugin activation/update
 */
function kursorganizer_activation_hook()
{
    // Clear all transients
    if (class_exists('KursOrganizer_API')) {
        KursOrganizer_API::clear_cache();
    }

    // Clear WordPress object cache
    wp_cache_flush();

    // Clear rewrite rules
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'kursorganizer_activation_hook');

/**
 * Clear cache when plugin files are updated
 * This hook runs when WordPress detects plugin file changes
 */
function kursorganizer_check_version_update()
{
    $stored_version = get_option('kursorganizer_plugin_version');
    $current_version = KURSORGANIZER_CACHE_VERSION;

    if ($stored_version !== $current_version) {
        // Version changed - clear all caches
        if (class_exists('KursOrganizer_API')) {
            KursOrganizer_API::clear_cache();
        }
        wp_cache_flush();
        update_option('kursorganizer_plugin_version', $current_version);
    }
}
add_action('admin_init', 'kursorganizer_check_version_update');

/**
 * Add cache version query parameter to plugin URL
 * This helps with browser caching
 */
function kursorganizer_add_cache_buster($url)
{
    if (strpos($url, KURSORGANIZER_PLUGIN_URL) === 0) {
        $separator = strpos($url, '?') !== false ? '&' : '?';
        $url .= $separator . 'v=' . KURSORGANIZER_CACHE_VERSION;
    }
    return $url;
}
add_filter('plugins_url', 'kursorganizer_add_cache_buster', 10, 1);
