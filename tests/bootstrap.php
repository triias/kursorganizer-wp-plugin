<?php
define('ABSPATH', __DIR__ . '/fixtures/');
define('WP_DEBUG', false);
define('HOUR_IN_SECONDS', 3600);

$GLOBALS['ko_test_options'] = array();
$GLOBALS['ko_test_transients'] = array();
$GLOBALS['ko_test_site_transients'] = array();
$GLOBALS['ko_test_remote_response'] = null;
$GLOBALS['ko_test_remote_calls'] = 0;
$GLOBALS['ko_test_remote_get_args'] = array();
$GLOBALS['ko_test_filters'] = array();
$GLOBALS['ko_test_plugin_active'] = false;
$GLOBALS['ko_test_activated_plugins'] = array();

class WP_Error
{
    private $code;
    private $message;

    public function __construct($code = '', $message = '')
    {
        $this->code = $code;
        $this->message = $message;
    }

    public function get_error_code()
    {
        return $this->code;
    }

    public function get_error_message()
    {
        return $this->message;
    }
}

function is_wp_error($value)
{
    return $value instanceof WP_Error;
}

function get_option($name, $default = false)
{
    return array_key_exists($name, $GLOBALS['ko_test_options'])
        ? $GLOBALS['ko_test_options'][$name]
        : $default;
}

function update_option($name, $value, $autoload = null)
{
    $GLOBALS['ko_test_options'][$name] = $value;
    return true;
}

function get_transient($name)
{
    return array_key_exists($name, $GLOBALS['ko_test_transients'])
        ? $GLOBALS['ko_test_transients'][$name]
        : false;
}

function set_transient($name, $value, $expiration)
{
    $GLOBALS['ko_test_transients'][$name] = $value;
    return true;
}

function delete_transient($name)
{
    unset($GLOBALS['ko_test_transients'][$name]);
    return true;
}

function get_site_transient($name)
{
    return array_key_exists($name, $GLOBALS['ko_test_site_transients'])
        ? $GLOBALS['ko_test_site_transients'][$name]
        : false;
}

function sanitize_text_field($value)
{
    return trim(preg_replace('/[\r\n\t ]+/', ' ', strip_tags((string) $value)));
}

function sanitize_key($value)
{
    return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value));
}

function sanitize_html_class($value)
{
    return preg_replace('/[^A-Za-z0-9_-]/', '', (string) $value);
}

function absint($value)
{
    return abs((int) $value);
}

function esc_url_raw($url)
{
    return filter_var((string) $url, FILTER_SANITIZE_URL);
}

function esc_url($url)
{
    return htmlspecialchars(esc_url_raw($url), ENT_QUOTES, 'UTF-8');
}

function esc_attr($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function esc_attr__($value, $domain)
{
    return esc_attr($value);
}

function wp_json_encode($value)
{
    return json_encode($value);
}

function add_query_arg($args, $url)
{
    $parts = parse_url($url);
    $query = array();
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }
    $query = array_merge($query, $args);
    $result = $parts['scheme'] . '://' . $parts['host'];
    if (isset($parts['port'])) {
        $result .= ':' . $parts['port'];
    }
    $result .= isset($parts['path']) ? $parts['path'] : '';
    return $result . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
}

function get_site_url()
{
    return 'https://example.org';
}

function wp_remote_post($url, $args)
{
    $GLOBALS['ko_test_remote_calls']++;
    return $GLOBALS['ko_test_remote_response'];
}

function wp_remote_get($url, $args = array())
{
    $GLOBALS['ko_test_remote_calls']++;
    $GLOBALS['ko_test_remote_get_args'][] = array(
        'url' => $url,
        'args' => $args,
    );
    return $GLOBALS['ko_test_remote_response'];
}

function wp_remote_retrieve_response_code($response)
{
    return isset($response['response']['code']) ? $response['response']['code'] : 0;
}

function wp_remote_retrieve_body($response)
{
    return isset($response['body']) ? $response['body'] : '';
}

function is_plugin_active($plugin)
{
    return $GLOBALS['ko_test_plugin_active'];
}

function plugin_basename($file)
{
    return basename(dirname($file)) . '/' . basename($file);
}

function add_filter($hook, $callback, $priority = 10, $accepted_args = 1)
{
    $GLOBALS['ko_test_filters'][] = array(
        'hook' => $hook,
        'callback' => $callback,
        'priority' => $priority,
        'accepted_args' => $accepted_args,
    );
    return true;
}

function plugin_dir_path($file)
{
    return rtrim(dirname($file), '/\\') . '/';
}

function activate_plugin($plugin)
{
    $GLOBALS['ko_test_activated_plugins'][] = $plugin;
    return null;
}

function wp_strip_all_tags($value)
{
    return strip_tags((string) $value);
}

function wpautop($value)
{
    return '<p>' . (string) $value . '</p>';
}

require_once dirname(__DIR__) . '/includes/class-shortcode-url-builder.php';
require_once dirname(__DIR__) . '/includes/class-validation-state.php';
require_once dirname(__DIR__) . '/includes/class-kursorganizer-api.php';
require_once dirname(__DIR__) . '/includes/class-plugin-updater.php';
