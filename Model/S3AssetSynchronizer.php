<?php
namespace GardenLawn\MediaGallery\Model;

use Exception;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\DeploymentConfig;
use Aws\S3\S3Client;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\RuntimeException;
use Psr\Log\LoggerInterface;

class S3AssetSynchronizer
{
    const string CONFIG_PATH_BUCKET = 'remote_storage/config/bucket';
    const string CONFIG_PATH_REGION = 'remote_storage/config/region';
    const string CONFIG_PATH_KEY = 'remote_storage/config/credentials/key';
    const string CONFIG_PATH_SECRET = 'remote_storage/config/credentials/secret';
    const string CONFIG_PATH_PREFIX = 'remote_storage/prefix';
    const string MEDIA_DIR = 'media';

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
     * @throws FileSystemException
     * @throws RuntimeException
     * @throws Exception
     */
    public function synchronize(bool $dryRun = false, bool $enableDeletion = false, bool $forceUpdate = false): array
    {
        $bucket = $this->deploymentConfig->get(self::CONFIG_PATH_BUCKET);
        $envPrefix = $this->deploymentConfig->get(self::CONFIG_PATH_PREFIX, '');
        if (empty($bucket)) {
            throw new Exception('S3 bucket name is not configured in env.php.');
        }
        $s3MediaPrefix = $envPrefix ? rtrim($envPrefix, '/') . '/' . self::MEDIA_DIR . '/' : self::MEDIA_DIR . '/';

        $s3Files = $this->getAllS3Files($bucket, $s3MediaPrefix);
        $dbAssets = $this->getExistingDbAssets();

        $assetsToInsert = [];
        $assetsToUpdate = [];
        $assetsToDelete = [];

        // --- Main processing loop ---
        foreach ($s3Files as $path => $s3Data) {
            if (!isset($dbAssets[$path])) {
                // Case 1: New asset, needs to be inserted
                $assetsToInsert[] = $this->prepareAssetData($path, $s3Data, $bucket, $s3MediaPrefix);
            } elseif ($forceUpdate) {
                // Case 2: Existing asset, check if it needs an update
                $dbAsset = $dbAssets[$path];
                if ($dbAsset['hash'] === null || $dbAsset['width'] == 0 || $dbAsset['hash'] !== $s3Data['hash']) {
                    $updateData = $this->prepareAssetData($path, $s3Data, $bucket, $s3MediaPrefix);
                    $updateData['id'] = $dbAsset['id']; // Important: keep the ID for the update
                    $assetsToUpdate[] = $updateData;
                }
            }
        }

        if ($enableDeletion) {
            $assetsToDelete = $this->findOrphanedAssets(array_keys($s3Files), $dbAssets);
        }

        // --- Database operations ---
        if (!$dryRun) {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $connection->getTableName('media_gallery_asset');

            if (!empty($assetsToInsert)) {
                $connection->insertMultiple($tableName, $assetsToInsert);
            }
            if (!empty($assetsToUpdate)) {
                $connection->beginTransaction();
                try {
                    foreach ($assetsToUpdate as $asset) {
                        $id = $asset['id'];
                        unset($asset['id']); // Don't try to update the ID itself
                        $connection->update($tableName, $asset, ['id = ?' => $id]);
                    }
                    $connection->commit();
                } catch (Exception $e) {
                    $connection->rollBack();
                    throw $e;
                }
            }
            if ($enableDeletion && !empty($assetsToDelete)) {
                $connection->delete($tableName, ['path IN (?)' => $assetsToDelete]);
            }
        }

        return [
            'inserted' => $assetsToInsert,
            'updated' => $assetsToUpdate,
            'deleted' => $assetsToDelete
        ];
    }

    private function findOrphanedAssets(array $s3FilePaths, array $dbAssets): array
    {
        $s3PathsMap = array_flip($s3FilePaths);
        $orphanedPaths = [];
        foreach ($dbAssets as $dbPath => $dbData) {
            if (!isset($s3PathsMap[$dbPath])) {
                $orphanedPaths[] = $dbPath;
            }
        }
        return $orphanedPaths;
    }

    /**
     * @throws FileSystemException
     * @throws RuntimeException
     * @throws Exception
     */
    private function getS3Client(): S3Client
    {
        if ($this->s3Client === null) {
            $key = $this->deploymentConfig->get(self::CONFIG_PATH_KEY);
            $secret = $this->deploymentConfig->get(self::CONFIG_PATH_SECRET);
            $region = $this->deploymentConfig->get(self::CONFIG_PATH_REGION);
            if (!$key || !$secret || !$region) {
                throw new Exception('S3 credentials (key, secret, region) are not fully configured in env.php.');
            }
            $this->s3Client = new S3Client([
                'version' => 'latest',
                'region' => $region,
                'credentials' => ['key' => $key, 'secret' => $secret],
            ]);
        }
        return $this->s3Client;
    }

    /**
     * @throws Exception
     */
    private function getAllS3Files(string $bucket, string $prefix): array
    {
        $s3Client = $this->getS3Client();
        $allFiles = [];
        $paginator = $s3Client->getPaginator('ListObjectsV2', ['Bucket' => $bucket, 'Prefix' => $prefix]);
        foreach ($paginator as $result) {
            $contents = $result->get('Contents');
            if (is_array($contents)) {
                foreach ($contents as $object) {
                    if (!str_ends_with($object['Key'], '/')) {
                        $path = str_starts_with($object['Key'], $prefix) ? substr($object['Key'], strlen($prefix)) : $object['Key'];
                        if (!empty($path)) {
                            $allFiles[$path] = [
                                'size' => $object['Size'],
                                'hash' => trim($object['ETag'], '"')
                            ];
                        }
                    }
                }
            }
        }
        return $allFiles;
    }

    private function getExistingDbAssets(): array
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $connection->getTableName('media_gallery_asset');
        $select = $connection->select()->from($tableName, ['id', 'path', 'hash', 'width', 'height']);
        // Returns an array like: ['path/to/file.jpg' => ['id' => 1, 'path' => ...]]
        return $connection->fetchAssoc($select);
    }

    private function prepareAssetData(string $path, array $data, string $bucket, string $s3MediaPrefix): array
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $filename = pathinfo($path, PATHINFO_BASENAME);
        $width = 0;
        $height = 0;

        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($extension, $imageExtensions)) {
            try {
                $fullS3Key = $s3MediaPrefix . $path;
                $imageUrl = $this->getS3Client()->getObjectUrl($bucket, $fullS3Key);
                $imageSizeInfo = @getimagesize($imageUrl);
                if ($imageSizeInfo) {
                    $width = (int)$imageSizeInfo[0];
                    $height = (int)$imageSizeInfo[1];
                }
            } catch (Exception $e) {
                $this->logger->warning('Could not get image size for ' . $path . ': ' . $e->getMessage());
            }
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
            'size' => $data['size'] ?? 0,
            'hash' => $data['hash'] ?? null,
            'width' => $width,
            'height' => $height,
        ];
    }
}
