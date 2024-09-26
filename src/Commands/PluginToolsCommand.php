<?php

namespace YDTBWP\Commands;

use PhpSchool\CliMenu\Builder\CliMenuBuilder;
use PhpSchool\CliMenu\CliMenu;
use PhpSchool\CliMenu\Style\CheckboxStyle;
use \YDTBWP\Utils\Encryption;

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

        $updateTracked = function (CliMenu $menu) {

            $item = $menu->getSelectedItem();
            echo "Moving " . $item->getText() . " to " . $item->getChecked() ? "tracked" : "untracked";

            if ($item->getChecked()) {
                $tracked = get_option('ydtbwp_push_plugins', []);
                $tracked[] = $item->getText();
                update_option('ydtbwp_push_plugins', $tracked);
            } else {
                $tracked = get_option('ydtbwp_push_plugins', []);
                $tracked = array_diff($tracked, [$item->getText()]);
                update_option('ydtbwp_push_plugins', $tracked);
            }
        };

        $getMax = function () {
            return count($this->untracked());
        };

        $all_plugins = get_plugins();
        $tracked = get_option('ydtbwp_push_plugins', []);
        $all_slugs = array_map(function ($key) {
            return explode("/", $key)[0];
        }, array_keys($all_plugins));

        $menu = (new CliMenuBuilder)
            ->setTitle('Choose Plugins To Push')
            ->modifyCheckboxStyle(function (CheckboxStyle $style) {
                $style->setUncheckedMarker('[○] ')
                    ->setCheckedMarker('[●] ');
            })
            ->addStaticItem('Check the plugins that should be pushed to the tracking system')
            ->addStaticItem(' ');

        for ($i = 0; $i < count($all_slugs); $i++) {
            $item = $all_slugs[$i] ?? "";
            $menu->addCheckboxItem($item, $updateTracked);
        }

        $menu
            ->addStaticItem(' ')
            ->addLineBreak('-');
        $menu = $menu->build();

        foreach ($menu->getItems() as $item) {
            if (in_array($item->getText(), $tracked)) {
                $item->setChecked(true);
            }
        }

        $menu->open();

    }

    public function checkTracked()
    {
        $tracked = get_option('ydtb_push_plugins', []);
        var_dump($tracked);
    }

    public function runCron()
    {
        do_action('ydtb_check_update_cron');
    }

}
