<?php
/**
 * Local integration-test release endpoint.
 *
 * Loaded as an MU plugin inside the disposable WordPress container.
 */

add_filter('pre_http_request', function ($preempt, $args, $url) {
    $release_url = 'https://api.github.com/repos/triias/kursorganizer-wp-plugin/releases/latest';
    if ($url !== $release_url) {
        return $preempt;
    }

    foreach ((array) (isset($args['headers']) ? $args['headers'] : array()) as $name => $value) {
        if (strtolower((string) $name) === 'authorization' && trim((string) $value) !== '') {
            update_option('ko_mock_authorization_seen', true, false);
        }
    }

    $version = get_option('ko_mock_release_version', '1.2.7');
    $package_url = 'http://ko-updater-wp/ko-update-package.zip';

    return array(
        'headers' => array(),
        'body' => wp_json_encode(array(
            'tag_name' => 'v' . $version,
            'html_url' => 'https://example.invalid/releases/v' . $version,
            'published_at' => '2026-07-15T10:00:00Z',
            'body' => 'Updater integration test',
            'zipball_url' => $package_url,
            'assets' => array(array(
                'name' => 'kursorganizer-wp-plugin.zip',
                'browser_download_url' => $package_url,
            )),
        )),
        'response' => array(
            'code' => 200,
            'message' => 'OK',
        ),
        'cookies' => array(),
        'filename' => null,
    );
}, 10, 3);
