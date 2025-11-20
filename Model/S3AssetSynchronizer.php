<?php
namespace GardenLawn\MediaGallery\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\DeploymentConfig;
use Aws\S3\S3Client;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\RuntimeException;
use Psr\Log\LoggerInterface;

/**
 * Service class to handle synchronization between an S3 bucket and the media_gallery_asset table.
 */
class S3AssetSynchronizer
{
    // Constants for env.php configuration paths
    const string CONFIG_PATH_KEY = 'remote_storage/driver_options/key';
    const string CONFIG_PATH_SECRET = 'remote_storage/driver_options/secret';
    const string CONFIG_PATH_REGION = 'remote_storage/driver_options/region';
    const string CONFIG_PATH_BUCKET = 'remote_storage/driver_options/bucket';
    const string CONFIG_PATH_PREFIX = 'remote_storage/driver_options/prefix';

    protected ResourceConnection $resourceConnection;
    protected DeploymentConfig $deploymentConfig;
    protected LoggerInterface $logger;
    private ?S3Client $s3Client = null;

    public function __construct(
        ResourceConnection $resourceConnection,
        DeploymentConfig   $deploymentConfig,
        LoggerInterface    $logger
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->deploymentConfig = $deploymentConfig;
        $this->logger = $logger;
    }

    /**
     * Finds new assets in S3 and adds them to the media_gallery_asset table.
     *
     * @param bool $dryRun If true, will not modify the database.
     * @return array The list of asset data that was (or would be) inserted.
     * @throws \Exception
     */
    public function synchronize(bool $dryRun = false): array
    {
        $bucket = $this->deploymentConfig->get(self::CONFIG_PATH_BUCKET);
        $prefix = $this->deploymentConfig->get(self::CONFIG_PATH_PREFIX, '');

        if (empty($bucket)) {
            throw new \Exception('S3 bucket name is not configured in env.php.');
        }

        $s3FilePaths = $this->getAllS3FilePaths($bucket, $prefix);
        $dbAssetPaths = $this->getExistingDbAssetPaths();
        $assetsToInsert = $this->findNewAssets($s3FilePaths, $dbAssetPaths);

        if (!$dryRun && !empty($assetsToInsert)) {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $connection->getTableName('media_gallery_asset');
            $connection->insertMultiple($tableName, $assetsToInsert);
        }

        return $assetsToInsert;
    }

    /**
     * @throws FileSystemException
     * @throws RuntimeException
     */
    private function getS3Client(): S3Client
    {
        if ($this->s3Client === null) {
            $key = $this->deploymentConfig->get(self::CONFIG_PATH_KEY);
            $secret = $this->deploymentConfig->get(self::CONFIG_PATH_SECRET);
            $region = $this->deploymentConfig->get(self::CONFIG_PATH_REGION);

            if (!$key || !$secret || !$region) {
                throw new \Exception('S3 credentials (key, secret, region) are not fully configured in env.php.');
            }

            $config = [
                'version' => 'latest',
                'region' => $region,
                'credentials' => [
                    'key' => $key,
                    'secret' => $secret,
                ],
            ];

            $this->s3Client = new S3Client($config);
        }
        return $this->s3Client;
    }

    /**
     * @throws FileSystemException
     * @throws RuntimeException
     */
    private function getAllS3FilePaths(string $bucket, string $prefix): array
    {
        $s3Client = $this->getS3Client();
        $allPaths = [];
        $continuationToken = null;

        do {
            $params = ['Bucket' => $bucket, 'Prefix' => $prefix];
            if ($continuationToken) {
                $params['ContinuationToken'] = $continuationToken;
            }

            $result = $s3Client->listObjectsV2($params);
            $contents = $result->get('Contents');

            if (is_array($contents)) {
                foreach ($contents as $object) {
                    if (!str_ends_with($object['Key'], '/')) {
                        $path = $prefix ? preg_replace('/^' . preg_quote($prefix, '/') . '\/?/', '', $object['Key']) : $object['Key'];
                        if (!empty($path)) {
                            $allPaths[] = $path;
                        }
                    }
                }
            }
            $continuationToken = $result->get('NextContinuationToken');
        } while ($continuationToken);

        return $allPaths;
    }

    private function getExistingDbAssetPaths(): array
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $connection->getTableName('media_gallery_asset');
        $select = $connection->select()->from($tableName, ['path']);
        return array_flip($connection->fetchCol($select));
    }

    private function findNewAssets(array $s3FilePaths, array $dbAssetPaths): array
    {
        $newAssets = [];
        foreach ($s3FilePaths as $s3Path) {
            if (!isset($dbAssetPaths[$s3Path])) {
                $newAssets[] = $this->prepareAssetData($s3Path);
            }
        }
        return $newAssets;
    }

    private function prepareAssetData(string $path): array
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $filename = pathinfo($path, PATHINFO_BASENAME);
        $mediaType = 'image';
        if (in_array($extension, ['mp4', 'mov', 'avi', 'webm'])) {
            $mediaType = 'video';
        }
        $mimeTypes = [
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
            'gif' => 'image/gif', 'webp' => 'image/webp', 'svg' => 'image/svg+xml',
            'mp4' => 'video/mp4', 'webm' => 'video/webm'
        ];
        return [
            'path' => $path,
            'title' => $filename,
            'source' => 'aws-s3',
            'content_type' => $mimeTypes[$extension] ?? 'application/octet-stream',
            'media_type' => $mediaType,
        ];
    }
}
