<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Model;

use Exception;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\RuntimeException;
use Psr\Log\LoggerInterface;
use Aws\S3\S3Client; // Keep for getAllS3Files method if not moved to adapter
use Magento\Framework\App\DeploymentConfig; // Keep for getAllS3Files method if not moved to adapter

class S3AssetSynchronizer
{
    private ResourceConnection $resourceConnection;
    private LoggerInterface $logger;
    private S3Adapter $s3Adapter;
    private DeploymentConfig $deploymentConfig; // Keep for direct access if needed by S3Adapter's internal logic

    public function __construct(
        ResourceConnection $resourceConnection,
        LoggerInterface $logger,
        S3Adapter $s3Adapter,
        DeploymentConfig $deploymentConfig
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->logger = $logger;
        $this->s3Adapter = $s3Adapter;
        $this->deploymentConfig = $deploymentConfig; // Keep for now
    }

    /**
     * @throws Exception
     */
    public function synchronize(bool $dryRun = false, bool $enableDeletion = false, bool $forceUpdate = false): array
    {
        // Logic remains the same, but S3 interactions could be proxied if adapter is expanded
        $s3Files = $this->getAllS3Files();
        $s3Paths = array_keys($s3Files);

        $dbAssets = $this->getExistingDbAssets();
        $dbPaths = array_keys($dbAssets);

        $pathsToInsert = array_diff($s3Paths, $dbPaths);
        $pathsToDelete = $enableDeletion ? array_diff($dbPaths, $s3Paths) : [];
        $pathsToUpdate = $forceUpdate ? array_intersect($s3Paths, $dbPaths) : [];

        $assetsToInsert = [];
        foreach ($pathsToInsert as $path) {
            $assetsToInsert[] = $this->prepareAssetData($path, $s3Files[$path]);
        }

        $assetsToUpdate = [];
        foreach ($pathsToUpdate as $path) {
            $dbAsset = $dbAssets[$path];
            $s3Asset = $s3Files[$path];
            if ($dbAsset['hash'] === null || $dbAsset['width'] == 0 || $dbAsset['hash'] !== $s3Asset['hash']) {
                $updateData = $this->prepareAssetData($path, $s3Asset, $dbAsset);
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

    // This method is specific to the synchronizer, so it's okay it stays here,
    // but it should use the S3 client from the adapter if possible.
    // For now, keeping its own client logic to avoid breaking anything.
    /**
     * @throws FileSystemException
     * @throws RuntimeException
     * @throws Exception
     */
    private function getS3Client(): S3Client
    {
        $key = $this->deploymentConfig->get('remote_storage/config/credentials/key');
        $secret = $this->deploymentConfig->get('remote_storage/config/credentials/secret');
        $region = $this->deploymentConfig->get('remote_storage/config/region');
        $bucket = $this->deploymentConfig->get('remote_storage/config/bucket');

        if (!$key || !$secret || !$region || !$bucket) {
            throw new Exception('S3 credentials are not fully configured in env.php.');
        }

        return new S3Client([
            'version' => 'latest',
            'region' => $region,
            'credentials' => ['key' => $key, 'secret' => $secret],
        ]);
    }

    /**
     * @throws FileSystemException
     * @throws RuntimeException
     */
    private function getAllS3Files(): array
    {
        $s3Client = $this->getS3Client();
        $bucket = $this->deploymentConfig->get('remote_storage/config/bucket');
        $prefix = ($this->deploymentConfig->get('remote_storage/prefix', '') ? rtrim($this->deploymentConfig->get('remote_storage/prefix', ''), '/') . '/' : '') . 'media/';

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

    private function getExistingDbAssets(): array
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $connection->getTableName('media_gallery_asset');
        $select = $connection->select()->from($tableName, ['id', 'path', 'hash', 'width', 'height']);
        $rows = $connection->fetchAll($select);

        $assetsByPath = [];
        foreach ($rows as $row) {
            $assetsByPath[$row['path']] = $row;
        }
        return $assetsByPath;
    }

    private function prepareAssetData(string $path, array $data, ?array $existingAsset = null): array
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $filename = pathinfo($path, PATHINFO_BASENAME);

        $width = isset($existingAsset['width']) ? (int)$existingAsset['width'] : 0;
        $height = isset($existingAsset['height']) ? (int)$existingAsset['height'] : 0;

        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (($width === 0 || $height === 0) && in_array($extension, $imageExtensions)) {
            try {
                $s3Client = $this->getS3Client();
                $bucket = $this->deploymentConfig->get('remote_storage/config/bucket');
                $fullS3Key = $this->s3Adapter->getFullS3Path($path);
                $imageUrl = $s3Client->getObjectUrl($bucket, $fullS3Key);
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
