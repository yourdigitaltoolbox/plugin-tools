<?php

namespace YDTBWP\Action;

use YDTBWP\Providers\Provider;
use \YDTBWP\Utils\Requests;
use \YDTBWP\Utils\ZipDirectory;

class PluginUpdateAction implements Provider
{

    public function register()
    {
        add_action('ydtbwp_update_plugins', [$this, 'update_plugins']);
        add_action('ydtbwp_push_single_plugin', [$this, 'push_single_plugin']);
    }

    public function out(string $arg1): void
    {
        if (!$this->quiet) {
            echo $arg1;
        }
    }

    private $quiet = true;

    public function update_plugins($quiet = true)
    {
        $out = [$this, 'out'];
        $this->quiet = $quiet;
        $checked_plugins = json_decode(get_option('ydtbwp_push_plugins', []));
        $all_plugins = get_plugins();
        $upgrade_plugins = array();
        $current = get_site_transient('update_plugins');

        foreach ((array) $all_plugins as $plugin_file => $plugin_data) {
            if (isset($current->response[$plugin_file])) {
                $slug = \explode('/', $plugin_file)[0];

                if ($current->response[$plugin_file]->package == "") {
                    echo "No update URL found for $plugin_file\n";
                    continue;
                }

                $pluginData = [
                    'plugin_name' => $plugin_data['Name'],
                    'plugin_version' => $plugin_data['Version'],
                    'plugin_update_version' => $current->response[$plugin_file]->new_version,
                    'plugin_update_url' => $current->response[$plugin_file]->package,
                    'plugin_slug' => \explode('/', $plugin_file)[0],
                    'plugin_file' => $plugin_file,
                ];

                $upgrade_plugins[] = $pluginData;
            }
        }
        $out("\n");
        $out("This Site has | " . count($upgrade_plugins) . " | plugins with pending updates... ");
        $out("\n\n");

        // We need to check if the plugin has been whitelisted to be pushed to the remote repo.
        foreach ($upgrade_plugins as $key => $plugin) {
            $slug = explode('/', $plugin['plugin_file'])[0];
            if (!isset($checked_plugins->$slug)) {
                echo "\t-- Plugin $slug is not whitelisted, removing from possible push list... \n";
                unset($upgrade_plugins[$key]);
                continue;
            }
            // set the plugin vendor
            $upgrade_plugins[$key]['plugin_vendor'] = $checked_plugins->$slug;
        }

        // if there are no plugins to update then we can return early
        if (empty($upgrade_plugins)) {
            $out("No updates for whitelisted plugins Available to push \n");
            return;
        }

        echo "\nThis Site has | " . count($upgrade_plugins) . " | plugins with pending updates that are whitelisted to be pushed to the remote repo.  \n\n";

        // We need to check if the plugin version has been pushed to the remote repo. to do that we need to make a request to the remote repo to get the plugin versions that are currently there.
        $RemotePlugins = Requests::getRemotePlugins();

        $remotePluginArray = [];
        foreach ($RemotePlugins as $property => $value) {
            $remotePluginArray[$value->slug] = $value->tags;
        }

        foreach ($upgrade_plugins as $key => $plugin) {
            if (isset($remotePluginArray[$plugin['plugin_slug']]) && in_array($plugin['plugin_update_version'], $remotePluginArray[$plugin['plugin_slug']])) {
                unset($upgrade_plugins[$key]);

                echo "\t-- Plugin $plugin[plugin_name] - $plugin[plugin_update_version] is already pushed to the remote repo, removing from possible push list... \n";
            }
        }

        echo "\nThis Site has | " . count($upgrade_plugins) . " | plugins with pending updates, that are whitelisted, and are not already pushed remotely.  \n\n";

        if (empty($upgrade_plugins)) {
            echo ("Good News! All plugin updates are already pushed so, No plugins to update \n");
            return;
        }

        $body = new \stdClass();
        $body->ref = "main";
        $body->inputs = new \stdClass();
        $body->inputs->json = \json_encode(array_values($upgrade_plugins));

        echo "\n------ Generated Plugin Update Info ------\n\n";

        var_dump($body);

        echo "\n\n";
        Requests::updateRequest(json_encode($body));
    }

    /**
     * Push a single plugin to the remote repo
     * This does not proxy a plugin update from another source, it is used to zip a local plugin and push it to the remote repo.
     */

    public function push_single_plugin($plugin)
    {
        echo "----- Pushing single plugin ----- \n";

        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/ydtbwp';
        $temp_url = $upload_dir['baseurl'] . '/ydtbwp';

        if (!file_exists($temp_dir)) {
            mkdir($temp_dir, 0777, true);
        }

        $targetDir = WP_PLUGIN_DIR . "/" . $plugin['slug'];
        $outputPath = $temp_dir . "/" . $plugin['slug'] . "." . $plugin['Version'] . ".zip";
        $outputURL = $temp_url . "/" . $plugin['slug'] . "." . $plugin['Version'] . ".zip";

        if (file_exists($outputPath)) {
            unlink($outputPath);
        }

        $zipPath = (new ZipDirectory($targetDir, $outputPath))->make();

        echo "$plugin[Name] - $plugin[Version] has been zipped to: \n";
        echo $outputURL . "\n";

        $body = new \stdClass();
        $body->ref = "main";
        $body->inputs = new \stdClass();
        $body->inputs->json = \json_encode([
            [
                'plugin_name' => $plugin['Name'],
                'plugin_version' => $plugin['Version'],
                'plugin_update_version' => $plugin['Version'],
                'plugin_update_url' => $outputURL,
                'plugin_slug' => $plugin['slug'],
                'plugin_file' => $plugin['file_path'],
                'plugin_vendor' => $plugin['vendor'],
            ],
        ]);

        var_dump($body);

        Requests::updateRequest(json_encode($body));
    }
}
