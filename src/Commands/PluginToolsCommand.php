<?php

namespace YDTBWP\Commands;

use \YDTBWP\Commands\MultiItemMenu;
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

        $valid_types = ['plugin', 'theme'];

        if (!in_array($type, $valid_types)) {
            \WP_CLI::error('Invalid type specified. Use "plugin" or "theme".');
            return;
        }

        $menu = new MultiItemMenu($type);
        $menu->build();
        $selected = $menu->getSelectedItems();
        update_option('ydtbwp_push_' . $type . 's', json_encode($selected));
        \WP_CLI::success(ucfirst($type) . 's selected and saved!');
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

    public function pushSinglePackage($args, $assoc_args)
    {
        $type = $args[0];
        if ($type === 'plugin') {
            $items_to_push = [];
            $site_items = get_plugins();
            $remoteItems = Requests::getRemoteData();
            $remoteItemArray = [];

            foreach ($remoteItems as $item => $data) {
                $remoteItemArray[$data->slug] = $data->version;
            }

            foreach ($site_items as $item_file => $item_data) {
                $slug = explode('/', $item_file)[0];
                $item_data['file_path'] = $item_file;

                if (!isset($remoteItemArray[$slug])) {
                    $items_to_push[$slug] = $item_data;
                    continue;
                }

                if (version_compare($item_data['Version'], $remoteItemArray[$slug], '>')) {
                    $items_to_push[$slug] = $item_data;
                }
            }

            $menu_class = 'SinglePluginMenu';
            $action_hook = 'ydtbwp_push_single_plugin';
        } elseif ($type === 'theme') {
            $items_to_push = [];
            $site_items = wp_get_themes();
            $remoteItems = Requests::getRemoteData();
            $remoteItemArray = [];

            foreach ($remoteItems as $item => $data) {
                $remoteItemArray[$data->slug] = $data->version;
            }

            foreach ($site_items as $item_slug => $item_data) {
                $slug = $item_slug;
                $item_data['file_path'] = $item_data->get_stylesheet_directory();

                if (!isset($remoteItemArray[$slug])) {
                    $items_to_push[$slug] = $item_data;
                    continue;
                }

                if (version_compare($item_data->get('Version'), $remoteItemArray[$slug], '>')) {
                    $items_to_push[$slug] = $item_data;
                }
            }

            $menu_class = 'SingleThemeMenu';
            $action_hook = 'ydtbwp_push_single_theme';
        } else {
            \WP_CLI::error('Invalid type specified. Use "plugin" or "theme".');
            return;
        }

        if (count($items_to_push) == 0) {
            echo "\n---------- Result ----------\n\n";
            echo "There are no {$type}s currently available to push. \n\tTry again Later.\n\n";
            echo "----------------------------\n";
            return;
        }

        $menu = new $menu_class($items_to_push, $remoteItems);
        $menu->buildMenu();

        $selected_slug = $menu->getItem();
        $selected_vendor = $menu->getVendor();

        echo $selected_slug;
        echo $selected_vendor;

        if ($selected_slug == "" || $selected_vendor == "") {
            \WP_CLI::error('No ' . $type . ' selected');
        }

        $selected_item = $items_to_push[$selected_slug];
        $selected_item['vendor'] = $selected_vendor;
        $selected_item['slug'] = $selected_slug;

        if ($type === 'plugin') {
            $tracked_plugins = json_decode(get_option('ydtbwp_push_plugins', '[]'), true);
            $tracked_plugins[$selected_slug] = $selected_item;
            update_option('ydtbwp_push_plugins', json_encode($tracked_plugins));
        } elseif ($type === 'theme') {
            $tracked_themes = json_decode(get_option('ydtbwp_push_themes', '[]'), true);
            $tracked_themes[$selected_slug] = $selected_item;
            update_option('ydtbwp_push_themes', json_encode($tracked_themes));
        }

        do_action($action_hook, $selected_item);
    }

    public function testS3($args, $assoc_args)
    {
        echo "Testing S3...\n";
        $s3 = new \YDTBWP\Utils\AwsS3();
        $s3->init();
        $s3->uploadFile('test.txt', 'test.txt');
        $date = new \DateTime();
        $date->modify("+1 day");
        $presigned = $s3->generatePresignedUrl('test.txt', $date);
        echo $presigned . PHP_EOL;
    }

    public function setS3Config($args, $assoc_args)
    {
        $allowed_params = ['region', 'bucket', 'keyID', 'secretKey'];
        $s3 = new \YDTBWP\Utils\AwsS3();

        $config = [];

        foreach ($assoc_args as $key => $value) {

            echo $key . ' => ' . $value . PHP_EOL;

            if (in_array($key, $allowed_params)) {
                $config[$key] = $value;
            }
        }

        if (!empty($config)) {
            $s3->updateS3Config($config);
            \WP_CLI::success("S3 config updated successfully.");
        } else {
            \WP_CLI::error("No valid S3 config parameters provided. Allowed parameters are: region, bucket, keyID, secretKey.");
        }
    }

};
