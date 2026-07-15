<?php
$plugin = 'kursorganizer-wp-plugin-main/kursorganizer-wp-plugin.php';

update_option('ko_mock_release_version', '1.2.8', false);
delete_option('ko_mock_authorization_seen');
delete_site_transient('update_plugins');

// Deliberately do not run admin_init. This is the same hook used by WP-Cron.
do_action('wp_update_plugins');

$updates = get_site_transient('update_plugins');
if (empty($updates->response[$plugin])) {
    throw new RuntimeException('The early updater did not participate in the cron update check.');
}

if ($updates->response[$plugin]->new_version !== '1.2.8') {
    throw new RuntimeException('Cron update check exposed an unexpected version.');
}

if (get_option('ko_mock_authorization_seen', false)) {
    throw new RuntimeException('The updater sent the stale GitHub token.');
}

echo "Cron update check completed without admin_init or Authorization header.\n";
