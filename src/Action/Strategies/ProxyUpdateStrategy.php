<?php

namespace YDTBWP\Action\Strategies;

use YDTBWP\Action\UpdateStrategyInterface;
use YDTBWP\Utils\AwsS3;
use YDTBWP\Utils\Requests;

class ProxyUpdateStrategy implements UpdateStrategyInterface
{
    private $type;

    public function __construct($type)
    {
        $this->type = $type;
    }

    public function update($items)
    {
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/ydtbwp';
        $temp_url = $upload_dir['baseurl'] . '/ydtbwp';

        foreach ($items as &$item) {

            $outputPath = $temp_dir . "/" . $item["slug"] . "." . $item["version"] . ".zip";
            $outputURL = $temp_url . "/" . $item["slug"] . "." . $item["version"] . ".zip";

            echo "Downloading {$item["name"]}...\n";
            echo "Output path: {$outputPath}\n";
            echo "Output URL: {$outputURL}\n";

            // Download the file
            Requests::downloadFile($item["update_url"], $outputPath);

            // Update the item data with the output URL
            $item["update_url"] = $outputURL;

            if ($this->type == 'remote') {
                // Upload the file to S3
                $s3 = new AwsS3();
                $s3->init();
                $s3->uploadFile($outputPath, "remote_package/{$item["type"]}/{$item["slug"]}.{$item["version"]}.zip");
                $prsignedUrl = $s3->generatePresignedUrl("remote_package/{$item["type"]}/{$item["slug"]}.{$item["version"]}.zip", '+5 minutes');
                $item["update_url"] = $prsignedUrl;
                //remove the local file
                unlink($outputPath);
            } else {
                // create a one off wordpress event for 10 minutes to delete the file
                wp_schedule_single_event(time() + 600, 'ydtbwp_delete_temp_file', array($outputPath));
            }
        }

        // Prepare the request body
        $body = new \stdClass();
        $body->ref = "main";
        $body->inputs = new \stdClass();
        $body->inputs->json = \json_encode(array_values($items));

        // Send the request to the server
        Requests::updateRequest(json_encode($body));
    }
}
