<?php
use PHPUnit\Framework\TestCase;

final class PluginUpdaterTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['ko_test_remote_response'] = null;
        $GLOBALS['ko_test_remote_calls'] = 0;
        $GLOBALS['ko_test_remote_get_args'] = array();
        $GLOBALS['ko_test_filters'] = array();
        $GLOBALS['ko_test_plugin_active'] = false;
        $GLOBALS['ko_test_activated_plugins'] = array();
        $GLOBALS['ko_test_site_transients'] = array();
        unset($GLOBALS['wp_filesystem']);
    }

    public function testReleaseAssetIsPreferredAndZipballIsFallback(): void
    {
        $updater = new KursOrganizer_Plugin_Updater(array(
            'slug' => '/plugins/kursorganizer-wp-plugin/kursorganizer-wp-plugin.php',
            'api_url' => 'https://api.github.com/repos/triias/kursorganizer-wp-plugin',
            'access_token' => '',
        ));

        $responseProperty = new ReflectionProperty($updater, 'github_response');
        $responseProperty->setAccessible(true);
        $downloadMethod = new ReflectionMethod($updater, 'get_download_url');
        $downloadMethod->setAccessible(true);

        $responseProperty->setValue($updater, (object) array(
            'zipball_url' => 'https://github.com/source.zip',
            'assets' => array((object) array(
                'name' => 'kursorganizer-wp-plugin.zip',
                'browser_download_url' => 'https://github.com/release/plugin.zip',
            )),
        ));
        self::assertSame('https://github.com/release/plugin.zip', $downloadMethod->invoke($updater));

        $responseProperty->setValue($updater, (object) array(
            'zipball_url' => 'https://github.com/source.zip',
            'assets' => array(),
        ));
        self::assertSame('https://github.com/source.zip', $downloadMethod->invoke($updater));
    }

    public function testLegacyFolderReceivesUpdateMetadataAndStoredTokenIsIgnored(): void
    {
        $GLOBALS['ko_test_remote_response'] = array(
            'response' => array('code' => 200),
            'body' => json_encode(array(
                'tag_name' => 'v1.2.7',
                'html_url' => 'https://github.com/triias/kursorganizer-wp-plugin/releases/tag/v1.2.7',
                'zipball_url' => 'https://github.com/source.zip',
                'assets' => array(array(
                    'name' => 'kursorganizer-wp-plugin.zip',
                    'browser_download_url' => 'https://github.com/release/plugin.zip',
                )),
            )),
        );

        $updater = new KursOrganizer_Plugin_Updater(array(
            'slug' => '/plugins/kursorganizer-wp-plugin-main/kursorganizer-wp-plugin.php',
            'api_url' => 'https://api.github.com/repos/triias/kursorganizer-wp-plugin',
            'access_token' => 'stale-token-must-not-be-used',
        ));

        $transient = (object) array(
            'checked' => array(
                'kursorganizer-wp-plugin-main/kursorganizer-wp-plugin.php' => '1.2.2',
            ),
            'response' => array(),
        );

        $result = $updater->modify_transient($transient);
        $plugin = $result->response['kursorganizer-wp-plugin-main/kursorganizer-wp-plugin.php'];

        self::assertSame('kursorganizer-wp-plugin-main/kursorganizer-wp-plugin.php', $plugin->plugin);
        self::assertSame('kursorganizer-wp-plugin-main', $plugin->slug);
        self::assertSame('1.2.7', $plugin->new_version);
        self::assertSame('https://github.com/release/plugin.zip', $plugin->package);
        self::assertArrayNotHasKey('Authorization', $GLOBALS['ko_test_remote_get_args'][0]['args']['headers'] ?? array());
    }

    public function testUpdaterRegistersBeforeWordPressUpdateChecksCanRun(): void
    {
        new KursOrganizer_Plugin_Updater(array(
            'slug' => '/plugins/kursorganizer-wp-plugin/kursorganizer-wp-plugin.php',
            'api_url' => 'https://api.github.com/repos/triias/kursorganizer-wp-plugin',
        ));

        $hooks = array_column($GLOBALS['ko_test_filters'], 'hook');
        self::assertContains('pre_set_site_transient_update_plugins', $hooks);
        self::assertContains('plugins_api', $hooks);
        self::assertContains('upgrader_post_install', $hooks);

        $mainFile = file_get_contents(dirname(__DIR__) . '/kursorganizer-wp-plugin.php');
        self::assertStringContainsString("kursorganizer_init_updater();", $mainFile);
        self::assertStringNotContainsString("add_action('admin_init', 'kursorganizer_init_updater')", $mainFile);
    }

    public function testPluginPopupAcceptsHistoricalSlugWhenInstalledCanonically(): void
    {
        $GLOBALS['ko_test_remote_response'] = array(
            'response' => array('code' => 200),
            'body' => json_encode(array(
                'tag_name' => 'v1.2.9',
                'html_url' => 'https://github.com/triias/kursorganizer-wp-plugin/releases/tag/v1.2.9',
                'zipball_url' => 'https://github.com/source.zip',
                'body' => 'Popup-Fix',
                'assets' => array(array(
                    'name' => 'kursorganizer-wp-plugin.zip',
                    'browser_download_url' => 'https://github.com/release/plugin.zip',
                )),
            )),
        );

        $updater = new KursOrganizer_Plugin_Updater(array(
            'slug' => '/plugins/kursorganizer-wp-plugin/kursorganizer-wp-plugin.php',
            'api_url' => 'https://api.github.com/repos/triias/kursorganizer-wp-plugin',
        ));

        $result = $updater->plugin_popup(
            false,
            'plugin_information',
            (object) array('slug' => 'kursorganizer-wp-plugin-main')
        );

        self::assertIsObject($result);
        self::assertSame('kursorganizer-wp-plugin', $result->slug);
        self::assertSame('1.2.9', $result->version);
        self::assertSame('https://github.com/release/plugin.zip', $result->download_link);
    }

    public function testPluginPopupUsesCachedUpdateWhenGithubIsTemporarilyUnavailable(): void
    {
        $plugin = 'kursorganizer-wp-plugin-main/kursorganizer-wp-plugin.php';
        $GLOBALS['ko_test_remote_response'] = new WP_Error('timeout', 'GitHub timeout');
        $GLOBALS['ko_test_site_transients']['update_plugins'] = (object) array(
            'response' => array(
                $plugin => (object) array(
                    'new_version' => '1.2.9',
                    'url' => 'https://github.com/triias/kursorganizer-wp-plugin/releases/tag/v1.2.9',
                    'package' => 'https://github.com/release/plugin.zip',
                ),
            ),
        );

        $updater = new KursOrganizer_Plugin_Updater(array(
            'slug' => '/plugins/' . $plugin,
            'api_url' => 'https://api.github.com/repos/triias/kursorganizer-wp-plugin',
        ));

        $result = $updater->plugin_popup(
            false,
            'plugin_information',
            (object) array('slug' => 'kursorganizer-wp-plugin-main')
        );

        self::assertIsObject($result);
        self::assertSame('1.2.9', $result->version);
        self::assertSame('https://github.com/release/plugin.zip', $result->download_link);
        self::assertStringContainsString('vorübergehend nicht geladen', $result->sections['changelog']);
    }

    public function testPluginPopupDoesNotInterceptUnrelatedPluginSlugs(): void
    {
        $updater = new KursOrganizer_Plugin_Updater(array(
            'slug' => '/plugins/kursorganizer-wp-plugin/kursorganizer-wp-plugin.php',
            'api_url' => 'https://api.github.com/repos/triias/kursorganizer-wp-plugin',
        ));
        $original = (object) array('source' => 'another-plugin');

        $result = $updater->plugin_popup(
            $original,
            'plugin_information',
            (object) array('slug' => 'another-plugin')
        );

        self::assertSame($original, $result);
        self::assertSame(0, $GLOBALS['ko_test_remote_calls']);
    }

    public function testAfterInstallIgnoresOtherPlugins(): void
    {
        $filesystem = new KursOrganizer_Test_Filesystem();
        $GLOBALS['wp_filesystem'] = $filesystem;
        $updater = new KursOrganizer_Plugin_Updater(array(
            'slug' => '/plugins/kursorganizer-wp-plugin-main/kursorganizer-wp-plugin.php',
            'api_url' => 'https://api.github.com/repos/triias/kursorganizer-wp-plugin',
        ));

        $response = array('original' => true);
        $result = $updater->after_install(
            $response,
            array('plugin' => 'advanced-iframe/advanced-iframe.php'),
            array('destination' => '/plugins/advanced-iframe')
        );

        self::assertSame($response, $result);
        self::assertSame(array(), $filesystem->moves);
    }

    public function testAfterInstallPreservesLegacyFolderForCuratedPackage(): void
    {
        $GLOBALS['ko_test_plugin_active'] = true;
        $filesystem = new KursOrganizer_Test_Filesystem(array(
            '/plugins/kursorganizer-wp-plugin-main',
        ));
        $GLOBALS['wp_filesystem'] = $filesystem;
        $updater = new KursOrganizer_Plugin_Updater(array(
            'slug' => '/plugins/kursorganizer-wp-plugin-main/kursorganizer-wp-plugin.php',
            'api_url' => 'https://api.github.com/repos/triias/kursorganizer-wp-plugin',
        ));

        $result = $updater->after_install(
            true,
            array('plugin' => 'kursorganizer-wp-plugin-main/kursorganizer-wp-plugin.php'),
            array('destination' => '/plugins/kursorganizer-wp-plugin')
        );

        self::assertSame('/plugins/kursorganizer-wp-plugin-main', $result['destination']);
        self::assertSame(array('/plugins/kursorganizer-wp-plugin-main'), $filesystem->deletes);
        self::assertSame(array(array(
            '/plugins/kursorganizer-wp-plugin',
            '/plugins/kursorganizer-wp-plugin-main',
            true,
        )), $filesystem->moves);
        self::assertSame(
            array('kursorganizer-wp-plugin-main/kursorganizer-wp-plugin.php'),
            $GLOBALS['ko_test_activated_plugins']
        );
    }

    public function testAfterInstallDoesNotMoveCanonicalFolderOntoItself(): void
    {
        $filesystem = new KursOrganizer_Test_Filesystem();
        $GLOBALS['wp_filesystem'] = $filesystem;
        $updater = new KursOrganizer_Plugin_Updater(array(
            'slug' => '/plugins/kursorganizer-wp-plugin/kursorganizer-wp-plugin.php',
            'api_url' => 'https://api.github.com/repos/triias/kursorganizer-wp-plugin',
        ));

        $result = $updater->after_install(
            true,
            array('plugin' => 'kursorganizer-wp-plugin/kursorganizer-wp-plugin.php'),
            array('destination' => '/plugins/kursorganizer-wp-plugin/')
        );

        self::assertSame('/plugins/kursorganizer-wp-plugin', $result['destination']);
        self::assertSame(array(), $filesystem->moves);
        self::assertSame(array(), $filesystem->deletes);
    }
}

final class KursOrganizer_Test_Filesystem
{
    public $moves = array();
    public $deletes = array();
    private $existingPaths;

    public function __construct(array $existingPaths = array())
    {
        $this->existingPaths = $existingPaths;
    }

    public function exists($path)
    {
        return in_array($path, $this->existingPaths, true);
    }

    public function delete($path, $recursive = false)
    {
        $this->deletes[] = $path;
        $this->existingPaths = array_values(array_diff($this->existingPaths, array($path)));
        return true;
    }

    public function move($source, $destination, $overwrite = false)
    {
        $this->moves[] = array($source, $destination, $overwrite);
        return true;
    }
}
