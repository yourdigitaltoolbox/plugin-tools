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

    /**
     * This section is for plugin configuration
     */

    public function setup()
    {
        $menu = new SetupMenu();
        $menu->setupPage();

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

    public function setSingleUpdateWorkflowURL($args, $assoc_args)
    {
        $host = $args[0];
        update_option('ydtbwp_workflow_url_single', $host);
        \WP_CLI::success('Single Plugin host set!');
    }

    public function setUpdateWorkflowURL($args, $assoc_args)
    {
        $host = $args[0];
        update_option('ydtbwp_workflow_url', $host);
        \WP_CLI::success('Update Workflow URL Set!');
    }
    # automatically update the plugin when there is an update available after we capture the new version in github
    public function toggleAutomaticUpdates($args, $assoc_args)
    {
        $value = get_option('ydtbwp_plugin_auto_update', false);
        update_option('ydtbwp_plugin_auto_update', !$value);

        if (!$value) {
            \WP_CLI::success('Automatic updates enabled!');
        } else {
            \WP_CLI::success('Automatic updates disabled!');
        }
    }

    public function setDataFetchURL($args, $assoc_args)
    {
        $host = $args[0];
        update_option('ydtbwp_fetch_host', $host);
        \WP_CLI::success('Fetch host set!');
    }

    /**
     * End of plugin configuration
     */

    public function checkPackages($args, $assoc_args)
    {
        $type = $args[0];
        if ($type === 'plugin') {
            echo "Checking for upgradeable plugins...\n";
            do_action('ydtbwp_update_plugins', false);
        } elseif ($type === 'theme') {
            echo "Checking for upgradeable themes...\n";
            do_action('ydtbwp_update_themes', false);
        } elseif ($type === 'all') {
            echo "Checking all upgradable packages (plugins, themes)\n";
            do_action('ydtbwp_update_plugins', false);
            do_action('ydtbwp_update_themes', false);
        } else {
            \WP_CLI::error('Invalid type specified. Use "plugin", "theme" or "all".');
        }
    }

    public function chooseTrackedItems($args, $assoc_args)
    {
        $type = $args[0];

        if ($type === 'plugin') {
            $menu = new MultiPluginMenu();
            $menu->build();
            $selected = $menu->getSelectedPlugins();
            update_option('ydtbwp_push_plugins', json_encode($selected));
            \WP_CLI::success('Plugins selected and saved!');
        } elseif ($type === 'theme') {
            $menu = new MultiThemeMenu();
            $menu->build();
            $selected = $menu->getSelectedThemes();
            update_option('ydtbwp_push_themes', json_encode($selected));
            \WP_CLI::success('Themes selected and saved!');
        } else {
            \WP_CLI::error('Invalid type specified. Use "plugin" or "theme".');
        }
    }

    public function checkTrackedPackage($args, $assoc_args)
    {
        $type = $args[0];

        if ($type === 'plugin') {
            $tracked = json_decode(get_option('ydtbwp_push_plugins', '[]'));
            var_dump($tracked);
        } elseif ($type === 'theme') {
            $tracked = json_decode(get_option('ydtbwp_push_themes', '[]'));
            var_dump($tracked);
        } else {
            \WP_CLI::error('Invalid type specified. Use "plugin" or "theme".');
        }
    }

    public function runCron()
    {
        do_action('ydtb_check_update_cron');
    }

    public function pushSinglePlugin()
    {
        // first thing is to get all the plugins on the site.
        $plugins_to_push = [];

        $site_plugins = get_plugins();

        // then we need to get the plugins that are tracked from the repo host
        $remotePlugins = Requests::getRemoteData();

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

        if (count($plugins_to_push) == 0) {
            echo "\n---------- Result ----------\n\n";
            echo "There are no plugins currently available to push. \n\tTry again Later.\n\n";
            echo "----------------------------\n";
            return;
        }

        // Then we allow the user to choose the local plugin that they want to push
        $menu = new SinglePluginMenu($plugins_to_push, $remotePlugins);
        $menu->buildMenu();

        $selected_slug = $menu->getItem();
        $selected_vendor = $menu->getVendor();

        echo $selected_slug;
        echo $selected_vendor;

        if ($selected_slug == "" || $selected_vendor == "") {
            \WP_CLI::error('No plugin selected');
        }

        $selected_plugin = $plugins_to_push[$selected_slug];

        $selected_plugin['vendor'] = $selected_vendor;
        $selected_plugin['slug'] = $selected_slug;
        // then we will push the plugin to the repo host
        do_action('ydtbwp_push_single_plugin', $selected_plugin);
    }
};
