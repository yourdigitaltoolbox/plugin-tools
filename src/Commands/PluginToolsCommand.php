<?php

namespace YDTBWP\Commands;

use \YDTBWP\Commands\MultiPluginMenu;
use \YDTBWP\Utils\Encryption;
use \YDTBWP\Utils\Requests;

class PluginToolsCommand extends \WP_CLI_Command

{
    public function __construct()
    {
        parent::__construct();
    }

    public function setToken($args, $assoc_args)
    {
        $token = $args[0];

        $data_encryption = new Encryption();
        $submitted_api_key = sanitize_text_field($token);
        $api_key = $data_encryption->encrypt($submitted_api_key);

        if (!empty($api_key)) {
            update_option('ydtbwp_github_token', $api_key);
            \WP_CLI::success('Token set!');
        } else {
            \WP_CLI::error('Token not set!');
        }
    }

    public function setPluginUpdateURL($args, $assoc_args)
    {
        $host = $args[0];
        update_option('ydtbwp_plugin_host', $host);
        \WP_CLI::success('Plugin host set!');
    }

    public function setSinglePluginURL($args, $assoc_args)
    {
        $host = $args[0];
        update_option('ydtbwp_plugin_host_single', $host);
        \WP_CLI::success('Single Plugin host set!');
    }

    public function setPluginFetchURL($args, $assoc_args)
    {
        $host = $args[0];
        update_option('ydtbwp_plugin_fetch_host', $host);
        \WP_CLI::success('Plugin fetch host set!');
    }

    public function checkUpgradeable()
    {
        echo "Checking for upgradeable plugins...\n";
        do_action('ydtbwp_update_plugins', false);
    }

    public function choose()
    {
        $menu = new MultiPluginMenu();
        $menu->build();
        $selected = $menu->getSelectedPlugins();

        echo "Selected Plugins: \n";
        var_dump($selected);

        update_option('ydtbwp_push_plugins', json_encode($selected));

    }

    public function checkTracked()
    {
        $tracked = json_decode(get_option('ydtbwp_push_plugins', []));
        var_dump($tracked);
    }

    public function runCron()
    {
        do_action('ydtb_check_update_cron');
    }

    public function pushSingle()
    {
        // first thing is to get all the plugins on the site.
        $plugins_to_push = [];

        $site_plugins = get_plugins();

        // then we need to get the plugins that are tracked from the repo host

        $remotePlugins = Requests::getRemotePlugins();

        $remotePluginArray = [];

        foreach ($remotePlugins as $plugin => $data) {
            $remotePluginArray[$data->slug] = $data->version;
        }

        // then we need to loop through each plugin and see if the plugin is tracked, if it is then we need to see if the local version is greater than the remote version
        foreach ($site_plugins as $plugin_file => $plugin_data) {
            $slug = explode('/', $plugin_file)[0];

            $plugin_data['file_path'] = $plugin_file;

            if (!isset($remotePluginArray[$slug])) {
                $plugins_to_push[$slug] = $plugin_data;
                continue;
            }

            // if the plugin is tracked then we need to check if the local version is greater than the remote version
            if (version_compare($plugin_data['Version'], $remotePluginArray[$slug], '>')) {
                $plugins_to_push[$slug] = $plugin_data;

            }
        }

        // Then we allow the user to choose the local plugin that they want to push
        $menu = new SinglePluginMenu($plugins_to_push, $remotePlugins);
        $menu->buildMenu();

        $selected_slug = $menu->getItem();
        $selected_vendor = $menu->getVendor();

        $selected_plugin = $plugins_to_push[$selected_slug];

        $selected_plugin['vendor'] = $selected_vendor;
        $selected_plugin['slug'] = $selected_slug;

        // then we will push the plugin to the repo host
        do_action('ydtbwp_push_single_plugin', $selected_plugin);

    }
};
