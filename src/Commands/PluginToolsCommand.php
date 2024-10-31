<?php

namespace YDTBWP\Commands;

use \YDTBWP\Commands\MultiItemMenu;
use \YDTBWP\Utils\AwsS3;
use \YDTBWP\Utils\Encryption;

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

    public function choose($args, $assoc_args)
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

    public function setS3Config($args, $assoc_args)
    {
        $allowed_params = ['region', 'bucket', 'keyID', 'secretKey'];
        $s3 = new AwsS3();

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
