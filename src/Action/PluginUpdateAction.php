<?php

namespace YDTBWP\Action;

use YDTBWP\Providers\Provider;
use \YDTBWP\Utils\Encryption;

class PluginUpdateAction implements Provider
{

    public function register()
    {
        add_action('ydtbwp_update_plugins', [$this, 'update_plugins']);
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
        $out('Updating plugins...');
        $checked_plugins = get_option('ydtbwp_push_plugins', []);
        $all_plugins = get_plugins();
        $upgrade_plugins = array();
        $current = get_site_transient('update_plugins');

        foreach ((array) $all_plugins as $plugin_file => $plugin_data) {
            if (isset($current->response[$plugin_file])) {
                $slug = \explode('/', $plugin_file)[0];
                $out("\n");
                $out("------- $plugin_file -------\n");
                $out("\n");
                $out("Plugin Name: " . $plugin_data['Name'] . "\n");
                $out("Plugin Version: " . $plugin_data['Version'] . "\n");
                $out("Plugin Update Version: " . $current->response[$plugin_file]->new_version . "\n");
                $out("Plugin Update URL: " . $current->response[$plugin_file]->package . "\n");
                $out("Plugin Slug: " . $slug . "\n");

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
        $out("Upgradeable plugins: " . count($upgrade_plugins) . "\n");
        $out("\n");

        $fetch_host = get_option('ydtbwp_plugin_fetch_host');

        if (!$fetch_host) {
            die('No fetch host found, Please use `wp pt setPluginFetchURL <host>` to set the fetch host');
        }

        // fetch the current stored plugins from the fetch host
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $fetch_host);
        $result = json_decode(curl_exec($ch));
        curl_close($ch);

        if (!isset($result->plugins)) {
            die('invlaid data returned from fetch host. Please check the fetch host');
        }

        $currentStoredPlugins = $result->plugins;

        foreach ($upgrade_plugins as $key => $plugin) {
            foreach ($currentStoredPlugins as $property => $value) {
                if ($plugin['plugin_slug'] === $property) {
                    if (in_array($plugin['plugin_update_version'], $value->tags)) {
                        unset($upgrade_plugins[$key]);
                    }
                }
            }
        }

        // check if the update version is already stored remotely, if so remove it from the upgrade_plugins array

        // debug the resultant array.
        // foreach ($upgrade_plugins as $key => $plugin) {
        //     echo "\n===============\n";
        //     echo "Plugin: ";
        //     echo $plugin['plugin_slug'] . "\n";
        //     echo $plugin['plugin_update_version'] . "\n";
        // }

        $body = new \stdClass();
        $body->ref = "main";
        $body->inputs = new \stdClass();
        $body->inputs->json = \json_encode($upgrade_plugins);

        $this->updateRequest(json_encode($body));
    }

    private function updateRequest($body)
    {
        // echo "Sending update request...\n";
        // echo $body;
        // echo "\n";

        $plugin_post_url = get_option('ydtbwp_plugin_host');
        if (!$plugin_post_url) {
            die('No plugin host found, Please use `wp pt setPluginUpdateURL <host>` to set the plugin host');
        }

        echo "Post Request against " . $plugin_post_url . "\n\n";

        $data_encryption = new Encryption();
        $encrypted_api_key = get_option('ydtbwp_github_token');
        if (!$encrypted_api_key) {
            die('No API key found, Please use `wp pt setToken <token>` to set the API key');
        }

        $api_key = $data_encryption->decrypt($encrypted_api_key);

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $plugin_post_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . $api_key,
                'X-GitHub-Api-Version: 2022-11-28',
                'Content-Type: application/json',
                'User-Agent: YDTB-WP-CLI',
            ),
        ));

        $response = curl_exec($curl);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($httpcode !== 200) {
            die('Error: ' . $httpcode);
        }

        echo " The request was successful\n Check Github for the action run status\n";

    }
}
