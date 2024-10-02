<?php
/**
 * Plugin Name:     YDTB Plugin Tools
 * Plugin URI:      https://github.com/johnkraczek/ydtb-plugin-tools
 * Description:     Tools for tracking paid plugin updates.
 * Version:         0.0.3
 * Author:          John Kraczek
 * Author URI:      https://johnkraczek.com/
 * License:         GPL-2.0+
 * Text Domain:     ydtb-plugin-tools
 * Domain Path:     /resources/lang
 */
require_once __DIR__ . '/vendor/autoload.php';

define('YDTB_PLUGIN_PATH', plugin_dir_path(__FILE__));

$clover = new YDTBWP\Providers\PluginToolsServiceProvider;
$clover->register();
add_action('init', [$clover, 'boot']);

// setup cron to activate and deactivate with the plugin
$cron = new YDTBWP\Utils\Cron;

register_activation_hook(
    __FILE__,
    [$cron, 'setup_cron_schedule']
);

register_deactivation_hook(
    __FILE__,
    [$cron, 'clear_cron_schedule']
);
