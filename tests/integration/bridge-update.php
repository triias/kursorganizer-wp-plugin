<?php
$plugin = 'kursorganizer-wp-plugin-main/kursorganizer-wp-plugin.php';

if (!function_exists('kursorganizer_init_updater')) {
    throw new RuntimeException('The legacy KursOrganizer plugin is not loaded.');
}

// Version 1.2.2 registers too late. This explicit one-time call models the
// controlled bridge used to test its actual updater and after-install code.
kursorganizer_init_updater();
wp_update_plugins(array('kursorganizer_bridge' => time()));

$updates = get_site_transient('update_plugins');
if (empty($updates->response[$plugin])) {
    throw new RuntimeException('Legacy updater did not expose the mocked release.');
}

if ($updates->response[$plugin]->new_version !== '1.2.7') {
    throw new RuntimeException('Legacy updater exposed an unexpected version.');
}

require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

$upgrader = new Plugin_Upgrader(new Automatic_Upgrader_Skin());
$result = $upgrader->upgrade($plugin);

if (is_wp_error($result)) {
    throw new RuntimeException($result->get_error_code() . ': ' . $result->get_error_message());
}

if (!$result) {
    throw new RuntimeException('Legacy-to-current plugin upgrade returned false.');
}

echo "Legacy bridge update completed.\n";
