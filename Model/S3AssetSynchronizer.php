<?php
namespace GardenLawn\MediaGallery\Model;

use Exception;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\DeploymentConfig;
use Aws\S3\S3Client;
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
        $s3Paths = array_keys($s3Files);

        $dbAssets = $this->getExistingDbAssets();
        $dbPaths = array_keys($dbAssets);

        $pathsToInsert = array_diff($s3Paths, $dbPaths);
        $pathsToDelete = $enableDeletion ? array_diff($dbPaths, $s3Paths) : [];
        $pathsToUpdate = $forceUpdate ? array_intersect($s3Paths, $dbPaths) : [];

        $assetsToInsert = [];
        foreach ($pathsToInsert as $path) {
            $assetsToInsert[] = $this->prepareAssetData($path, $s3Files[$path], $bucket, $s3MediaPrefix);
        }

        $assetsToUpdate = [];
        foreach ($pathsToUpdate as $path) {
            $dbAsset = $dbAssets[$path];
            $s3Asset = $s3Files[$path];
            if ($dbAsset['hash'] === null || $dbAsset['width'] == 0 || $dbAsset['hash'] !== $s3Asset['hash']) {
                $updateData = $this->prepareAssetData($path, $s3Asset, $bucket, $s3MediaPrefix, $dbAsset);
                $updateData['id'] = $dbAsset['id'];
                $assetsToUpdate[] = $updateData;
            }
        }

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
                        unset($asset['id']);
                        $connection->update($tableName, $asset, ['id = ?' => $id]);
                    }
                    $connection->commit();
                } catch (Exception $e) {
                    $connection->rollBack();
                    throw $e;
                }
            }
            if (!empty($pathsToDelete)) {
                $connection->delete($tableName, ['path IN (?)' => $pathsToDelete]);
            }
        }

        return [
            'inserted' => $assetsToInsert,
            'updated' => $assetsToUpdate,
            'deleted' => array_values($pathsToDelete)
        ];
    }

    /**
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

    private function getAllS3Files(string $bucket, string $prefix): array
    {
        $s3Client = $this->getS3Client();
        $allFiles = [];
        $paginator = $s3Client->getPaginator('ListObjectsV2', ['Bucket' => $bucket, 'Prefix' => $prefix]);
        foreach ($paginator as $result) {
            $contents = $result->get('Contents');
            if (is_array($contents)) {
                foreach ($contents as $object) {
                    if (substr($object['Key'], -1) !== '/') {
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

    /**
     * CORRECTED: Fetches assets and keys the returned array by the 'path' column.
     */
    private function getExistingDbAssets(): array
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $connection->getTableName('media_gallery_asset');
        $select = $connection->select()->from($tableName, ['id', 'path', 'hash', 'width', 'height']);
        $rows = $connection->fetchAll($select);

        $assetsByPath = [];
        foreach ($rows as $row) {
            // This ensures the array is keyed by the case-sensitive path.
            $assetsByPath[$row['path']] = $row;
        }
        return $assetsByPath;
    }

    private function prepareAssetData(string $path, array $data, string $bucket, string $s3MediaPrefix, ?array $existingAsset = null): array
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $filename = pathinfo($path, PATHINFO_BASENAME);

        $width = isset($existingAsset['width']) ? (int)$existingAsset['width'] : 0;
        $height = isset($existingAsset['height']) ? (int)$existingAsset['height'] : 0;

        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (($width === 0 || $height === 0) && in_array($extension, $imageExtensions)) {
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
