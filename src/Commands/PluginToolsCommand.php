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

    public function setup()
    {
        $menu = new SetupMenu();
        $menu->setupPage();

    }

    /**
     * End of plugin configuration
     */

    public function check($args, $assoc_args)
    {
        $type = $args[0];
        if ($type === 'plugin' || !isset($type)) {
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

        if (!isset($type)) {
            $type = 'plugin';
        }

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

    public function tracked($args, $assoc_args)
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
}
;
