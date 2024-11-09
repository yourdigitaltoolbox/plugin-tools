<?php
/**
 * Plugin Name:     YDTB Plugin Tools
 * Plugin URI:      https://github.com/johnkraczek/ydtb-plugin-tools
 * Description:     Tools for tracking paid plugin updates.
 * Version:         0.0.13
 * Author:          John Kraczek
 * Author URI:      https://johnkraczek.com/
 * License:         GPL-2.0+
 * Text Domain:     ydtb-plugin-tools
 * Domain Path:     /resources/lang
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

// check if the vendor directory exists, and load it if it does
$autoload = __DIR__ . '/vendor/autoload.php';

if (!file_exists(filename: $autoload)) {
    add_action(hook_name: 'admin_notices', callback: function (): void {
        $message = __(text: 'YDTB Plugin Tools was downloaded from source and has not been built. Please run `composer install` inside the plugin directory <br> OR <br> install a released version of the plugin which will have already been built.', domain: 'ydtb-plugin-tools');
        echo '<div class="notice notice-error">';
        echo '<p>' . $message . '</p>';
        echo '</div>';
    });
    return false;
}
require_once $autoload;

$plugin = new YDTBWP\Plugin;
$plugin->register();

