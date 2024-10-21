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
        add_action("ydtbwp_push_single_{$this->type}", [$this, "push_single_item"]);

        add_action('ydtbwp_delete_temp_file', function ($file) {
            unlink($file);
        });
    }

    private function out(string $arg1): void
    {
        if (!$this->quiet) {
            echo $arg1;
        }
    }

    public function update_items($quiet = true)
    {
        $out = [$this, 'out'];
        $this->quiet = $quiet;
        $checked_items = json_decode(get_option("ydtbwp_push_{$this->type}s", []));
        $all_items = $this->type === 'theme' ? wp_get_themes() : get_plugins();
        $upgrade_items = array();
        $current = get_site_transient("update_{$this->type}s");

        // echo "Checking for updates for {$this->type}s... \n";
        // var_dump($current);

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

        $out("\n");
        $out("This Site has | " . count($upgrade_items) . " | {$this->type}s with pending updates... ");
        $out("\n\n");

        foreach ($upgrade_items as $key => $item) {
            $slug = $item["slug"];
            if (!isset($checked_items->$slug)) {
                $out("\t-- {$this->type} {$item["name"]} is not whitelisted, removing from possible push list... \n");
                unset($upgrade_items[$key]);
                continue;
            }
            $upgrade_items[$key]["vendor"] = $checked_items->$slug;
        }

        if (empty($upgrade_items)) {
            $out("No updates for whitelisted {$this->type}s Available to push \n");
            return;
        }

        $out("\nThis Site has | " . count($upgrade_items) . " | {$this->type}s with pending updates that are whitelisted to be pushed to the remote repo.  \n\n");

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
                $out(" [Skipping... (Already Tracked Version)]\n");

                if ($automatic_updates) {
                    $skin = new \Automatic_Upgrader_Skin();
                    $upgrader = $this->type === 'theme' ? new \Theme_Upgrader($skin) : new \Plugin_Upgrader($skin);
                    $result = $upgrader->upgrade($item["file"]);
                    if ($result) {
                        $out(" \t   >> Local {$this->type} Updated Successfully \n");
                    } else {
                        $out("\t   >> Local {$this->type} Update Failed \n");
                    }
                    activate_plugin($item["file"]);
                }
            } else {
                echo "[** Adding to push list **...] \n";
            }
        }

        if (empty($upgrade_items)) {
            $out("\nNo updates for whitelisted {$this->type}s Available to push \n\n");
            return;
        }

        die();

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

    public function push_single_item($item)
    {
        $out("----- Pushing single {$this->type} ----- \n");
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/ydtbwp';
        $temp_url = $upload_dir['baseurl'] . '/ydtbwp';
        if (!file_exists($temp_dir)) {
            mkdir($temp_dir, 0777, true);
        }
        $targetDir = $this->type === 'theme' ? WP_theme_DIR . "/" . $item['slug'] : WP_PLUGIN_DIR . "/" . $item['slug'];
        $outputPath = $temp_dir . "/" . $item['slug'] . ".zip";
        if (file_exists($outputPath)) {
            unlink($outputPath);
        }
        $zipPath = (new ZipDirectory($targetDir, $outputPath, $item['slug']))->make();
        $newZipPath = $temp_dir . "/" . $item['slug'] . "." . $item['Version'] . ".zip";
        rename($zipPath, $newZipPath);
        $zipPath = $newZipPath;
        $outputURL = $temp_url . "/" . $item['slug'] . "." . $item['Version'] . ".zip";
        $out("{$item['Name']} - {$item['Version']} has been zipped to: \n");
        $out($outputURL . "\n");

        $body = new \stdClass();
        $body->ref = "main";
        $body->inputs = new \stdClass();
        $body->inputs->json = \json_encode([
            [
                "{$this->type}_name" => $item['Name'],
                "{$this->type}_version" => $item['Version'],
                "{$this->type}_update_version" => $item['Version'],
                "{$this->type}_update_url" => $outputURL,
                "{$this->type}_slug" => $item['slug'],
                "{$this->type}_file" => $item['file_path'],
            ],
        ]);
        Requests::updateRequest(json_encode($body));
    }
}
