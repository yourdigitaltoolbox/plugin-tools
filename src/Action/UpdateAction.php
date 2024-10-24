<?php

namespace YDTBWP\Action;

use \YDTBWP\Action\Strategies\ProxyUpdateStrategy;
use \YDTBWP\Action\Strategies\SimpleUpdateStrategy;
use \YDTBWP\Providers\Provider;
use \YDTBWP\Utils\Requests;
use \YDTBWP\Utils\ZipDirectory;

class UpdateAction implements Provider
{
    private $type;
    private $quiet = true;

    public function __construct($type)
    {
        $this->type = $type;
    }

    public function register()
    {
        add_action("ydtbwp_update_{$this->type}s", [$this, "update_items"]);
        add_action("ydtbwp_push_local_{$this->type}", [$this, "push_local_item"]);
        add_action("ydtbwp_delete_temp_{$this->type}_file", function ($file) {
            unlink($file);
        });
    }

    public function update_items()
    {
        $checked_items = json_decode(get_option("ydtbwp_push_{$this->type}s", []));
        $all_items = $this->type === 'theme' ? wp_get_themes() : get_plugins();
        $upgrade_items = array();
        $current = get_site_transient("update_{$this->type}s");

        foreach ((array) $all_items as $item_file => $item_data) {
            if (isset($current->response[$item_file])) {

                if ($this->type == 'plugin') {
                    $new_version = $current->response[$item_file]->new_version;
                    $new_package_url = $current->response[$item_file]->package;
                } else {
                    $new_version = $current->response[$item_file]['new_version'];
                    $new_package_url = $current->response[$item_file]['package'];
                }

                $itemData = [
                    "name" => $item_data['Name'],
                    "version" => $item_data['Version'],
                    "update_version" => $new_version,
                    "update_url" => $new_package_url,
                    "slug" => explode('/', $item_file)[0],
                    "file" => $item_file,
                    "type" => $this->type,
                ];
                $upgrade_items[] = $itemData;
            }
        }

        echo ("\n");
        echo ("This Site has | " . count($upgrade_items) . " | {$this->type}s with pending updates... ");
        echo ("\n\n");

        foreach ($upgrade_items as $key => $item) {
            $slug = $item["slug"];
            if (!isset($checked_items->$slug)) {
                echo ("\t-- {$this->type} {$item["name"]} is not whitelisted, removing from possible push list... \n");
                unset($upgrade_items[$key]);
                continue;
            }
            $upgrade_items[$key]["vendor"] = $checked_items->$slug;
        }

        if (empty($upgrade_items)) {
            echo ("No updates for whitelisted {$this->type}s Available to push \n");
            return;
        }

        echo ("\nThis Site has | " . count($upgrade_items) . " | {$this->type}s with pending updates that are whitelisted to be pushed to the remote repo.  \n\n");

        $remoteItems = Requests::getRemoteData("{$this->type}s");
        $remoteItemArray = [];
        foreach ($remoteItems as $property => $value) {
            $remoteItemArray[$value->slug] = $value->tags;
        }

        $automatic_updates = get_option('ydtbwp_plugin_auto_update', false);

        foreach ($upgrade_items as $key => $item) {
            echo "\t-- Checking {$this->type} {$item["name"]} - {$item["update_version"]}: ";

            if (isset($remoteItemArray[$item["slug"]]) && in_array($item["update_version"], $remoteItemArray[$item["slug"]])) {
                unset($upgrade_items[$key]);
                echo (" [Skipping... (Already Tracked Version)]\n");

                if ($automatic_updates) {
                    $skin = new \Automatic_Upgrader_Skin();
                    $upgrader = $this->type === 'theme' ? new \Theme_Upgrader($skin) : new \Plugin_Upgrader($skin);
                    $result = $upgrader->upgrade($item["file"]);
                    if ($result) {
                        echo (" \t   >> Local {$this->type} Updated Successfully \n");
                    } else {
                        echo ("\t   >> Local {$this->type} Update Failed \n");
                    }
                    activate_plugin($item["file"]);
                }
            } else {
                echo "[** Adding to push list **...] \n";
            }
        }

        if (empty($upgrade_items)) {
            echo ("\nNo updates for whitelisted {$this->type}s Available to push \n\n");
            return;
        }

        $strategyName = get_option('ydtbwp_update_strategy', 'remote');
        $updateStrategy = $this->getUpdateStrategy($strategyName);
        $updateStrategy->update($upgrade_items);
    }

    private function getUpdateStrategy(string $strategyName): UpdateStrategyInterface
    {
        switch ($strategyName) {
            case 'simple':
                return new SimpleUpdateStrategy();
            case 'local':
                return new ProxyUpdateStrategy('local');
            case 'remote':
                return new ProxyUpdateStrategy('remote');
            default:
                throw new \InvalidArgumentException("Unknown update strategy: $strategyName");
        }
    }

/**
 * Push a single item to the remote repo. Use this to push a new plugin or theme to the remote repo.
 * @param array $item
 */

    public function push_local_item($pushItem)
    {
        $strategyName = get_option('ydtbwp_update_strategy', 'remote');

        if ($strategyName === 'simple') {
            echo ("Simple strategy does not support pushing local items. Please setup a different strategy. \n");
            return;
        }

        echo "----- Pushing local {$this->type} {$pushItem->name} ----- \n";

        $all_packages = $this->type === 'theme' ? wp_get_themes() : get_plugins();

        $item = null;
        foreach ($all_packages as $key => $package) {
            $package_slug = explode('/', $key)[0];
            if ($package_slug === $pushItem->slug) {
                $item = [
                    "Name" => $package['Name'],
                    "Version" => $package['Version'],
                    "slug" => $pushItem->slug,
                    "file_path" => $key,
                    "vendor" => $pushItem->vendor,
                    "type" => $this->type,
                ];
                break;
            }
        }

        if (!isset($item)) {
            echo ("{$this->type} {$pushItem->slug} not found. \n");
            throw new \Exception("{$this->type} {$pushItem->slug} not found.");
        }

        $upload_dir = wp_upload_dir();

        $temp_dir = $upload_dir['basedir'] . '/ydtbwp';
        $temp_url = $upload_dir['baseurl'] . '/ydtbwp';

        if (!file_exists($temp_dir)) {
            mkdir($temp_dir, 0777, true);
        }

        if ($this->type === 'theme') {
            $theme = wp_get_theme($item['slug']);
            $targetDir = $theme->get_stylesheet_directory();
        } else {
            $targetDir = WP_PLUGIN_DIR . "/" . $item['slug'];
        }

        $outputPath = $temp_dir . "/" . $item['slug'] . ".zip";

        if (file_exists(echoputPath)) {
            unlink(echoputPath);
        }

        $zipPath = (new ZipDirectory($targetDir, echoputPath, $item['slug']))->make();
        $newZipPath = $temp_dir . "/" . $item['slug'] . "." . $item['Version'] . ".zip";

        rename($zipPath, $newZipPath);

        $zipPath = $newZipPath;
        $outputURL = $temp_url . "/" . $item['slug'] . "." . $item['Version'] . ".zip";

        echo ("{$item['Name']} - {$item['Version']} has been zipped to: \n");
        echo ($outputURL . "\n");

        echo "Uploading {$item['Name']}...\n";

        $updateStrategy = $this->getUpdateStrategy($strategyName);
        $updateStrategy->update([[
            "name" => $item['Name'],
            "version" => $item['Version'],
            "update_version" => $item['Version'],
            "update_url" => $outputURL,
            "slug" => $item['slug'],
            "file" => $item['file_path'],
            "type" => $this->type,
            "vendor" => $item['vendor'],
        ]]);
    }
}
