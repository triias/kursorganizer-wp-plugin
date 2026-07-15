<?php
if (!defined('ABSPATH')) {
    exit;
}

class KursOrganizer_Plugin_Updater
{
    private $file;
    private $plugin;
    private $basename;
    private $active;
    private $github_response;
    private $github_url;
    private $config;

    public function __construct($config)
    {
        $this->config = $config;
        $this->file = $config['slug'];
        $this->plugin = plugin_basename($this->file);
        $this->basename = plugin_basename($this->file);
        // Diese Klasse wird durch die aktive Plugin-Hauptdatei geladen. Der
        // Guard erlaubt gleichzeitig isolierte Tests ohne WordPress-Admin-Dateien.
        $this->active = function_exists('is_plugin_active') ? is_plugin_active($this->plugin) : true;
        $this->github_url = $config['api_url'];

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
                // Pflichtfelder fuer WordPress' AJAX-Update-Endpoint:
                //   plugin: Pfad zur Plugin-Datei (z. B. "kursorganizer-wp-plugin/kursorganizer-wp-plugin.php")
                //   slug:   Verzeichnisname (NICHT der Pfad!) — sonst schlaegt der "Jetzt aktualisieren"-Button im
                //           Plugin-Information-Popup mit "Es wurde kein Plugin angegeben" fehl.
                $plugin_dir_slug = dirname($this->plugin);
                $plugin = array(
                    'id'          => "github.com/{$plugin_dir_slug}",
                    'plugin'      => $this->plugin,
                    'slug'        => $plugin_dir_slug,
                    'new_version' => $github_version,
                    'url'         => isset($this->github_response->html_url) ? $this->github_response->html_url : '',
                    'package'     => $this->get_download_url(),
                    'icons'       => $this->get_icons(),
                    'banners'     => array(),
                    'banners_rtl' => array(),
                    'tested'      => '',
                    'requires_php' => '',
                    'compatibility' => new \stdClass(),
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

        // Akzeptiere sowohl den Verzeichnis-Slug als auch den vollen Plugin-Pfad,
        // damit Aufrufe aus alten Cache-States (oder anderer Code, der den Pfad sendet) sauber funktionieren.
        $plugin_dir_slug = dirname($this->plugin);
        if (empty($args->slug) || ($args->slug !== $plugin_dir_slug && $args->slug !== $this->basename)) {
            return $result;
        }

        $this->get_repository_info();

        if (!$this->github_response || !isset($this->github_response->tag_name)) {
            return $result;
        }

        $github_version = ltrim($this->github_response->tag_name, 'v');
        $plugin_data    = function_exists('get_plugin_data') ? get_plugin_data($this->file, false, false) : array();

        $description = isset($plugin_data['Description']) ? wp_strip_all_tags($plugin_data['Description']) : '';

        $plugin = array(
            'name'              => isset($plugin_data['Name']) ? $plugin_data['Name'] : 'KursOrganizer X iFrame',
            'slug'              => $plugin_dir_slug,
            'version'           => $github_version,
            'author'            => isset($plugin_data['AuthorName']) ? $plugin_data['AuthorName'] : 'KursOrganizer GmbH',
            'author_profile'    => 'https://github.com/triias',
            'last_updated'      => isset($this->github_response->published_at) ? $this->github_response->published_at : '',
            'homepage'          => isset($plugin_data['PluginURI']) && $plugin_data['PluginURI']
                ? $plugin_data['PluginURI']
                : (isset($this->github_response->html_url) ? $this->github_response->html_url : ''),
            'requires'          => isset($plugin_data['RequiresWP']) ? $plugin_data['RequiresWP'] : '',
            'requires_php'      => isset($plugin_data['RequiresPHP']) ? $plugin_data['RequiresPHP'] : '',
            'short_description' => $description,
            'sections'          => array(
                'description' => $description ? wpautop($description) : '<p>KursOrganizer X iFrame Plugin.</p>',
                'changelog'   => $this->get_changelog(),
            ),
            'icons'             => $this->get_icons(),
            'download_link'     => $this->get_download_url(),
        );

        return (object) $plugin;
    }

    /**
     * Liefert Icon-URLs fuer das Plugin (WordPress nutzt diese in der Update-Liste
     * und im Plugin-Information-Popup).
     */
    private function get_icons()
    {
        $raw_base = preg_replace(
            '#^https://api\.github\.com/repos/#',
            'https://raw.githubusercontent.com/',
            $this->github_url
        );
        $icon_base = "{$raw_base}/main/assets/icons";
        return array(
            'svg'     => "{$icon_base}/icon.svg",
            'default' => "{$icon_base}/icon.svg",
        );
    }

    /**
     * Prefer the curated plugin ZIP attached to a release. Older releases and
     * clients remain compatible through GitHub's source zipball fallback.
     */
    private function get_download_url()
    {
        if (!empty($this->github_response->assets) && is_array($this->github_response->assets)) {
            foreach ($this->github_response->assets as $asset) {
                if (
                    isset($asset->name, $asset->browser_download_url)
                    && $asset->name === 'kursorganizer-wp-plugin.zip'
                    && filter_var($asset->browser_download_url, FILTER_VALIDATE_URL)
                ) {
                    return $asset->browser_download_url;
                }
            }
        }

        return isset($this->github_response->zipball_url) ? $this->github_response->zipball_url : '';
    }

    private function get_repository_info()
    {
        if (is_null($this->github_response)) {
            $args = array(
                'timeout' => 15,
                'sslverify' => true
            );

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
        // GitHub-API: bei Releases gibt es body als Markdown direkt mit
        if ($this->github_response && !empty($this->github_response->body)) {
            return $this->github_response->body;
        }

        // Fallback: CHANGELOG.md vom Default-Branch laden (main, mit master als Fallback).
        // Repo-Pfad aus der API-URL extrahieren, damit ein Repo-Rename keinen weiteren Patch erfordert.
        $args = array(
            'timeout' => 15,
            'sslverify' => true,
        );

        $raw_base = preg_replace('#^https://api\.github\.com/repos/#', 'https://raw.githubusercontent.com/', $this->github_url);
        foreach (['main', 'master'] as $branch) {
            $response = wp_remote_get("{$raw_base}/{$branch}/CHANGELOG.md", $args);
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                return wp_remote_retrieve_body($response);
            }
        }

        return 'Keine Änderungshistorie verfügbar.';
    }

    public function after_install($response, $hook_extra, $result)
    {
        global $wp_filesystem;

        // Der Filter ist global. Installationen anderer Plugins duerfen niemals
        // in das KursOrganizer-Verzeichnis verschoben werden.
        if (empty($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->plugin) {
            return $response;
        }

        if (is_wp_error($response) || empty($result['destination'])) {
            return $response;
        }

        $install_directory = rtrim(plugin_dir_path($this->file), '/\\');
        $current_destination = rtrim($result['destination'], '/\\');

        // Aeltere GitHub-Archive wurden als kursorganizer-wp-plugin-main
        // installiert. Das kuratierte Release-Asset hat den kanonischen
        // Wurzelordner; bei einem Update bleibt der bestehende Ordnername
        // erhalten, damit WordPress das aktive Plugin weiterhin findet.
        if ($current_destination !== $install_directory) {
            if (!is_object($wp_filesystem) || !method_exists($wp_filesystem, 'move')) {
                return new WP_Error(
                    'kursorganizer_updater_filesystem_unavailable',
                    'Das Plugin-Verzeichnis konnte nicht aktualisiert werden.'
                );
            }

            if (
                method_exists($wp_filesystem, 'exists')
                && $wp_filesystem->exists($install_directory)
                && (
                    !method_exists($wp_filesystem, 'delete')
                    || !$wp_filesystem->delete($install_directory, true)
                )
            ) {
                return new WP_Error(
                    'kursorganizer_updater_destination_cleanup_failed',
                    'Das bisherige Plugin-Verzeichnis konnte nicht bereinigt werden.'
                );
            }

            if (!$wp_filesystem->move($current_destination, $install_directory, true)) {
                return new WP_Error(
                    'kursorganizer_updater_move_failed',
                    'Das Plugin-Verzeichnis konnte nicht an seinen bisherigen Ort verschoben werden.'
                );
            }
        }

        $result['destination'] = $install_directory;

        if ($this->active && function_exists('activate_plugin')) {
            activate_plugin($this->plugin);
        }

        return $result;
    }
}
