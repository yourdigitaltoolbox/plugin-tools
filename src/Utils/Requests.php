<?php

namespace YDTBWP\Utils;

class Requests
{
    public static function getRemotePlugins()
    {
        $fetch_host = get_option('ydtbwp_plugin_fetch_host');

        if (!$fetch_host) {
            echo ('No fetch host found, Please use `wp pt setPluginFetchURL <host>` to set the fetch host');
            return;
        }

        if (!$fetch_host || !is_string($fetch_host) || !preg_match('/^http(s)?:\/\/[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(\/.*)?$/i', $fetch_host)) {
            echo "Fetch Host: " . $fetch_host . "\n";
            echo ('Invalid URL provided for fetch host. Please use `wp pt setPluginFetchURL <host>` to set the fetch host correctly');
            return;
        }

        // fetch the current stored plugins from the fetch host
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $fetch_host);
        $result = json_decode(curl_exec($ch));
        curl_close($ch);

        if (!isset($result->plugins)) {
            echo ('invlaid data returned from fetch host. Please check the fetch host');
            return;
        }
        // @TODO we could do more checks here to make sure the plugin data is valid.
        return $result->plugins;
    }

    public static function updateRequest($body, $type = 'list')
    {
        echo "Sending update request...\n";
        // echo $body;
        echo "\n";

        if ($type == 'list') {
            $plugin_post_url = get_option('ydtbwp_plugin_host');
            if (!$plugin_post_url) {
                echo ('No plugin host found, Please use `wp pt setPluginUpdateURL <host>` to set the plugin host');
                return;
            }

            if (!$plugin_post_url || !is_string($plugin_post_url) || !preg_match('/^http(s)?:\/\/[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(\/.*)?$/i', $plugin_post_url)) {
                echo ('Invalid URL provided for plugin host. Please use `wp pt setPluginUpdateURL <host>` to set the plugin host correctly');
                return;
            }
        }
        if ($type == 'single') {
            $plugin_post_url = get_option('ydtbwp_plugin_host_single');
            if (!$plugin_post_url) {
                echo ('No plugin host found, Please use `wp pt setSinglePluginURL <host>` to set the plugin host');
                return;
            }

            if (!$plugin_post_url || !is_string($plugin_post_url) || !preg_match('/^http(s)?:\/\/[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(\/.*)?$/i', $plugin_post_url)) {
                echo ('Invalid URL provided for plugin host. Please use `wp pt setSinglePluginURL <host>` to set the plugin host correctly');
                return;
            }
        }

        echo "Post Request against " . $plugin_post_url . "\n\n";

        $data_encryption = new Encryption();
        $encrypted_api_key = get_option('ydtbwp_github_token');
        if (!$encrypted_api_key) {
            echo ('No API key found, Please use `wp pt setToken <token>` to set the API key');
            return;
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
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($httpcode !== 200) {
            echo ('Error: ' . $httpcode);
            return;
        }

        echo " The request was successful\n Check Github for the action run status\n";

    }

}
