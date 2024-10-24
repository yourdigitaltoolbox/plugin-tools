<?php

namespace YDTBWP\Utils;

use Aws\S3\S3Client;

class AwsS3
{
    private $s3Client;

    private $bucketName;
    private $region = 'us-east-1';
    private $version = '2006-03-01';
    private $accessKeyID;
    private $secretAccessKey;

    public function __construct()
    {
    }

    public function init()
    {
        $this->loadS3DataFromOptions();

        if (!$this->bucketName || !$this->region || !$this->accessKeyID || !$this->secretAccessKey || !$this->version) {
            \WP_CLI::error('Invalid S3 configuration!');
        }

        $this->s3Client = new S3Client([
            'version' => $this->version,
            'region' => $this->region,
            'credentials' => [
                'key' => $this->accessKeyID,
                'secret' => $this->secretAccessKey,
            ],
        ]);
    }

    public function getData($key = null)
    {
        $properties = [
            'bucketName' => $this->bucketName,
            'region' => $this->region,
            'accessKeyID' => $this->accessKeyID,
            'version' => $this->version,
            'secretAccessKey' => $this->secretAccessKey ? '**********' : null,
        ];

        $data = [];

        foreach ($properties as $propKey => $value) {
            $data[$propKey] = $value ?: '** Not Set **';
        }

        if ($key === null) {
            return $data;
        }

        if (!array_key_exists($key, $data)) {
            throw new \InvalidArgumentException("Property '{$key}' does not exist.");
        }

        return $data[$key];
    }

    public function debugS3Config()
    {
        echo 'Bucket Name: ' . $this->bucketName . PHP_EOL;
        echo 'Region: ' . $this->region . PHP_EOL;
        echo 'Version: ' . $this->version . PHP_EOL;
        echo 'Access Key ID: ' . $this->accessKeyID . PHP_EOL;
        echo 'Secret Access Key: ' . $this->secretAccessKey . PHP_EOL;
    }

    public function updateS3Config(array $config): void
    {
        $valueWasUpdated = false;
        $this->loadS3DataFromOptions();
        if (isset($config['keyID'])) {
            echo 'Updating accessKeyID to: ' . $config['keyID'] . PHP_EOL;
            $this->accessKeyID = $config['keyID'];
            $valueWasUpdated = true;
        }

        if (isset($config['secretKey'])) {
            $this->secretAccessKey = $config['secretKey'];
            $valueWasUpdated = true;
        }

        if (isset($config['bucket'])) {
            echo 'Updating bucketName to: ' . $config['bucket'] . PHP_EOL;
            $this->bucketName = $config['bucket'];
            $valueWasUpdated = true;
        }

        if (isset($config['region'])) {
            echo 'Updating region to: ' . $config['region'] . PHP_EOL;
            $this->region = $config['region'];
            $valueWasUpdated = true;
        }

        if ($valueWasUpdated) {
            $this->storeS3DataToOptions();
        }
    }

    public function storeS3DataToOptions()
    {
        $data_encryption = new Encryption();
        $encrypted_secret = $data_encryption->encrypt($this->secretAccessKey);
        $encrypted_keyId = $data_encryption->encrypt($this->accessKeyID);

        $credentials = [
            'secretAccessKey' => $encrypted_secret,
            'accessKeyID' => $encrypted_keyId,
        ];

        $data = [
            'credentials' => $credentials,
            'version' => sanitize_text_field($this->version),
            'region' => sanitize_text_field($this->region),
            'bucketName' => sanitize_text_field($this->bucketName),
        ];

        update_option('ydtbwp_s3_data', $data);
        \WP_CLI::success('Credentials and data stored successfully!');
    }

    public function loadS3DataFromOptions()
    {
        $data_encryption = new Encryption();
        $stored_data = get_option('ydtbwp_s3_data');
        if ($stored_data) {
            $this->bucketName = $stored_data['bucketName'] == "" ? $this->bucketName : $stored_data['bucketName'];
            $this->region = $stored_data['region'] == "" ? $this->region : $stored_data['region'];
            $this->version = $stored_data['version'] == "" ? $this->version : $stored_data['version'];
            $this->accessKeyID = isset($stored_data['credentials']['accessKeyID']) ? $data_encryption->decrypt($stored_data['credentials']['accessKeyID']) : $this->accessKeyID;
            $this->secretAccessKey = isset($stored_data['credentials']['secretAccessKey']) ? $data_encryption->decrypt($stored_data['credentials']['secretAccessKey']) : $this->secretAccessKey;
        } else {
            $this->bucketName = defined('S3_UPLOADS_BUCKET') && S3_UPLOADS_BUCKET ? S3_UPLOADS_BUCKET : $this->bucketName;
            $this->region = defined('S3_UPLOADS_REGION') && S3_UPLOADS_REGION ? S3_UPLOADS_REGION : $this->region;
            $this->accessKeyID = defined('S3_UPLOADS_KEY') && S3_UPLOADS_KEY ? S3_UPLOADS_KEY : $this->accessKeyID;
            $this->secretAccessKey = defined('S3_UPLOADS_SECRET') && S3_UPLOADS_SECRET ? S3_UPLOADS_SECRET : $this->secretAccessKey;
        }
    }

    public function removeS3DataFromOptions()
    {
        delete_option('ydtbwp_s3_data');
        \WP_CLI::success('Credentials and data removed successfully!');
    }

    public function uploadFile($file, $key)
    {

        if (!$this->s3Client) {
            \WP_CLI::error('S3 client not initialized!');
        }

        try {
            $result = $this->s3Client->putObject([
                'Bucket' => $this->bucketName,
                'Key' => $key,
                'SourceFile' => $file,
            ]);

            return $result;
        } catch (Aws\S3\Exception\S3Exception $e) {
            return $e->getMessage();
        }
    }

    public function deleteFile($key)
    {
        if (!$this->s3Client) {
            \WP_CLI::error('S3 client not initialized!');
        }

        try {
            $result = $this->s3Client->deleteObject([
                'Bucket' => $this->bucketName,
                'Key' => $key,
            ]);
            return $result;
        } catch (Aws\S3\Exception\S3Exception $e) {
            return $e->getMessage();
        }
    }

    public function getFileUrl($key)
    {

        if (!$this->s3Client) {
            \WP_CLI::error('S3 client not initialized!');
        }

        try {
            $result = $this->s3Client->getObjectUrl($this->bucketName, $key);
            return $result;
        } catch (Aws\S3\Exception\S3Exception $e) {
            return $e->getMessage();
        }
    }

    public function generatePresignedUrl($key, $expires)
    {

        if (!$this->s3Client) {
            \WP_CLI::error('S3 client not initialized!');
        }

        try {
            $cmd = $this->s3Client->getCommand('GetObject', [
                'Bucket' => $this->bucketName,
                'Key' => $key,
            ]);

            $request = $this->s3Client->createPresignedRequest($cmd, '+20 minutes');
            $presignedUrl = (string) $request->getUri();
            return $presignedUrl;
        } catch (Aws\S3\Exception\S3Exception $e) {
            return $e->getMessage();
        }
    }

}
