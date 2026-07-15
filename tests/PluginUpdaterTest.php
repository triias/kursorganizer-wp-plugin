<?php
use PHPUnit\Framework\TestCase;

final class PluginUpdaterTest extends TestCase
{
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
}
