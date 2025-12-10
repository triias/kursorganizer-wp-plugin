<?php
if (!defined('ABSPATH')) {
    exit;
}

// Include admin functions for is_plugin_active()
require_once(ABSPATH . 'wp-admin/includes/plugin.php');

class KursOrganizer_Plugin_Updater
{
    private $file;
    private $plugin;
    private $basename;
    private $active;
    private $github_response;
    private $authorize_token;
    private $github_url;
    private $config;

    public function __construct($config)
    {
        $this->config = $config;
        $this->file = $config['slug'];
        $this->plugin = plugin_basename($this->file);
        $this->basename = plugin_basename($this->file);
        $this->active = is_plugin_active($this->plugin);
        $this->github_url = $config['api_url'];
        $this->authorize_token = $config['access_token'];

        // Hook into WordPress Update processes
        add_filter('pre_set_site_transient_update_plugins', array($this, 'modify_transient'), 10, 1);
        add_filter('plugins_api', array($this, 'plugin_popup'), 10, 3);
        add_filter('upgrader_post_install', array($this, 'after_install'), 10, 3);
    }

    public function modify_transient($transient)
    {
        if (!$transient || !isset($transient->checked)) {
            return $transient;
        }

        // Check if our plugin is in the checked list
        if (!isset($transient->checked[$this->plugin])) {
            return $transient;
        }

        // Hole GitHub Release-Informationen
        $this->get_repository_info();

        // Wenn eine neue Version verfügbar ist
        if ($this->github_response && isset($this->github_response->tag_name)) {
            // Remove 'v' prefix from tag name if present (e.g., "v1.2.0" -> "1.2.0")
            $github_version = ltrim($this->github_response->tag_name, 'v');
            $current_version = $transient->checked[$this->plugin];
            
            if (version_compare($github_version, $current_version, '>')) {
                $plugin = array(
                    'url' => $this->plugin,
                    'slug' => $this->basename,
                    'package' => $this->github_response->zipball_url,
                    'new_version' => $github_version
                );
                $transient->response[$this->plugin] = (object) $plugin;
            }
        }

        return $transient;
    }

    public function plugin_popup($result, $action, $args)
    {
        if ($action !== 'plugin_information') {
            return $result;
        }

        if (!empty($args->slug)) {
            if ($args->slug == $this->basename) {
                $this->get_repository_info();

                // Remove 'v' prefix from tag name if present
                $github_version = ltrim($this->github_response->tag_name, 'v');
                
                $plugin = array(
                    'name'              => $this->plugin,
                    'slug'              => $this->basename,
                    'version'           => $github_version,
                    'author'            => 'KursOrganizer GmbH',
                    'author_profile'    => 'https://github.com/triias',
                    'last_updated'      => $this->github_response->published_at,
                    'homepage'          => $this->github_response->html_url,
                    'short_description' => $this->github_response->description,
                    'sections'          => array(
                        'Description'   => $this->github_response->description,
                        'Updates'       => $this->get_changelog(),
                    ),
                    'download_link'     => $this->github_response->zipball_url
                );

                return (object) $plugin;
            }
        }

        return $result;
    }

    private function get_repository_info()
    {
        if (is_null($this->github_response)) {
            $args = array(
                'timeout' => 15,
                'sslverify' => true
            );
            if ($this->authorize_token) {
                $args['headers']['Authorization'] = "token {$this->authorize_token}";
            }

            $response = wp_remote_get(
                "{$this->github_url}/releases/latest",
                $args
            );

            if (is_wp_error($response)) {
                // Log error for debugging (only in debug mode)
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('KursOrganizer Updater Error: ' . $response->get_error_message());
                }
                return false;
            }

            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('KursOrganizer Updater Error: HTTP ' . $response_code);
                }
                return false;
            }

            $this->github_response = json_decode(wp_remote_retrieve_body($response));
            
            // Check if response is valid
            if (!$this->github_response || !isset($this->github_response->tag_name)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('KursOrganizer Updater Error: Invalid response from GitHub API');
                }
                return false;
            }
        }
    }

    private function get_changelog()
    {
        $response = wp_remote_get(
            "{$this->github_url}/raw/master/CHANGELOG.md",
            array('headers' => array('Authorization' => "token {$this->authorize_token}"))
        );

        if (is_wp_error($response)) {
            return 'Keine Änderungshistorie verfügbar.';
        }

        return $response['body'];
    }

    public function after_install($response, $hook_extra, $result)
    {
        global $wp_filesystem;

        $install_directory = plugin_dir_path($this->file);
        $wp_filesystem->move($result['destination'], $install_directory);
        $result['destination'] = $install_directory;

        if ($this->active) {
            activate_plugin($this->plugin);
        }

        return $result;
    }
}
