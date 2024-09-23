<?php
/**
 * Plugin Name:     YDTB Plugin Tools
 * Plugin URI:      https://github.com/johnkraczek/ydtb-plugin-tools
 * Description:     Tools for tracking paid plugin updates. 
 * Version:         0.0.1
 * Author:          John Kraczek
 * Author URI:      https://johnkraczek.com/
 * License:         GPL-2.0+
 * Text Domain:     ydtb-plugin-tools
 * Domain Path:     /resources/lang
 */
require_once __DIR__.'/vendor/autoload.php';

$clover = new YDTBWP\Providers\PluginToolsServiceProvider;
$clover->register();

add_action('init', [$clover, 'boot']);
