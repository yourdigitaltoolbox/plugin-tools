<?php

namespace YDTBWP\Utils;

class Requests
{
    public static function getRemoteData($type = 'plugins')
    {
        if (!in_array($type, ['plugins', 'themes'])) {
            throw new \Exception('Invalid type provided. Only "plugin" or "theme" are allowed.');
        }

        $fetch_host = get_option('ydtbwp_fetch_host', defined('YDTBWP_FETCH_URL') ? YDTBWP_FETCH_URL : '');

        if (!$fetch_host) {
            echo ('No fetch host found, Please use `wp pt setDataFetchURL <host>` to set the fetch host');
            return;
        }

        if (!$fetch_host || !is_string($fetch_host) || !preg_match('/^http(s)?:\/\/[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(\/.*)?$/i', $fetch_host)) {
            echo "Fetch Host: " . $fetch_host . "\n";
            echo ('Invalid URL provided for fetch host. Please use `wp pt setDataFetchURL <host>` to set the fetch host correctly');
            return;
        }

        // fetch the current stored data from the fetch host
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $fetch_host);
        $result = json_decode(curl_exec($ch));
        curl_close($ch);

        if (!isset($result->$type)) {
            echo ('Invalid data returned from fetch host. Please check the fetch host');
            return;
        }

        // @TODO we could do more checks here to make sure the data is valid.
        return $result->$type;
    }

    public static function updateRequest($body, $type = 'list')
    {
        echo "Sending update request...\n";
        echo "\n";

        if ($type == 'list') {
            $update_workflow_url = get_option('ydtbwp_workflow_url', defined('YDTBWP_WORKFLOW_URL') ? YDTBWP_WORKFLOW_URL : '');
            if (!$update_workflow_url) {
                echo ('No plugin host found, Please use `wp pt setUpdateWorkflowURL <host>` to set the plugin host');
                return;
            }

            if (!$update_workflow_url || !is_string($update_workflow_url) || !preg_match('/^http(s)?:\/\/[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(\/.*)?$/i', $update_workflow_url)) {
                echo ('Invalid URL provided for plugin host. Please use `wp pt setUpdateWorkflowURL <host>` to set the plugin host correctly');
                return;
            }
        }

        echo "Post Request against " . $update_workflow_url . "\n\n";

        $data_encryption = new Encryption();
        $encrypted_api_key = get_option('ydtbwp_github_token');

        if (!$encrypted_api_key) {
            if (defined('YDTBWP_GITHUB_TOKEN') && YDTBWP_GITHUB_TOKEN) {
                $api_key = YDTBWP_GITHUB_TOKEN;
            } else {
                echo ('No API key found, Please use `wp pt setToken <token>` to set the API key');
                return;
            }
        } else {
            $api_key = $data_encryption->decrypt($encrypted_api_key);
        }

        if (!$api_key) {
            echo ('Invalid API key found, Please use `wp pt setToken <token>` to set the API key correctly');
            return;
        }

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $update_workflow_url,
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

        if ($httpcode !== 200 && $httpcode !== 204) {
            echo ('Error: ' . $httpcode);
            return;
        }

        echo " The request was successful\n Check Github for the action run status\n";

        $webUrl = str_replace('api.github.com/repos', 'github.com', $update_workflow_url);
        $webUrl = str_replace('/dispatches', '', $webUrl);

        echo "You can view the status of the action run here: " . $webUrl . "\n";

    }

    public static function downloadFile($url, $path)
    {

        echo "Downloading file from {$url} to {$path}...\n";

        $fileContents = file_get_contents($url);

        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($path, $fileContents);
    }
}
