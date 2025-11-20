<?php
namespace GardenLawn\MediaGallery\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\DeploymentConfig;
use Aws\S3\S3Client;
use Psr\Log\LoggerInterface;

class S3AssetSynchronizer
{
    // Corrected paths for the user's env.php structure
    const string CONFIG_PATH_BUCKET = 'remote_storage/config/bucket';
    const string CONFIG_PATH_REGION = 'remote_storage/config/region';
    const string CONFIG_PATH_KEY = 'remote_storage/config/credentials/key';
    const string CONFIG_PATH_SECRET = 'remote_storage/config/credentials/secret';
    const string CONFIG_PATH_PREFIX = 'remote_storage/prefix';

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
     * Synchronizes S3 assets with the database.
     *
     * @param bool $dryRun If true, no database modifications will be made.
     * @param bool $enableDeletion If true, assets missing from S3 will be deleted from the database.
     * @return array A summary of operations: ['inserted' => [...], 'deleted' => [...]].
     * @throws \Exception
     */
    public function synchronize(bool $dryRun = false, bool $enableDeletion = false): array
    {
        $bucket = $this->deploymentConfig->get(self::CONFIG_PATH_BUCKET);
        $prefix = $this->deploymentConfig->get(self::CONFIG_PATH_PREFIX, '');

        if (empty($bucket)) {
            throw new \Exception('S3 bucket name is not configured in env.php.');
        }

        $s3FilePaths = $this->getAllS3FilePaths($bucket, $prefix);
        $dbAssetPaths = $this->getExistingDbAssetPaths();

        $assetsToInsert = $this->findNewAssets($s3FilePaths, $dbAssetPaths);
        $assetsToDelete = [];
        if ($enableDeletion) {
            $assetsToDelete = $this->findOrphanedAssets($s3FilePaths, $dbAssetPaths);
        }

        if (!$dryRun) {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $connection->getTableName('media_gallery_asset');

            if (!empty($assetsToInsert)) {
                $connection->insertMultiple($tableName, $assetsToInsert);
            }

            if ($enableDeletion && !empty($assetsToDelete)) {
                $connection->delete($tableName, ['path IN (?)' => $assetsToDelete]);
            }
        }

        return [
            'inserted' => $assetsToInsert,
            'deleted' => $assetsToDelete
        ];
    }

    private function findOrphanedAssets(array $s3FilePaths, array $dbAssetPaths): array
    {
        $s3PathsMap = array_flip($s3FilePaths);
        $orphanedPaths = [];
        foreach ($dbAssetPaths as $dbPath => $value) {
            if (!isset($s3PathsMap[$dbPath])) {
                $orphanedPaths[] = $dbPath;
            }
        }
        return $orphanedPaths;
    }

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
                    if (substr($object['Key'], -1) !== '/') {
                        // Remove base prefix from path if it exists
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
