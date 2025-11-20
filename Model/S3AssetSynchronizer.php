<?php
namespace GardenLawn\MediaGallery\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\DeploymentConfig;
use Magento\AwsS3\Model\S3ClientFactory;
use Aws\S3\S3Client;
use Psr\Log\LoggerInterface;

/**
 * Service class to handle synchronization between an S3 bucket and the media_gallery_asset table.
 */
class S3AssetSynchronizer
{
    const DEPLOYMENT_CONFIG_S3_BUCKET = 'remote_storage/driver_options/bucket';
    const DEPLOYMENT_CONFIG_S3_PREFIX = 'remote_storage/driver_options/prefix';

    protected ResourceConnection $resourceConnection;
    protected S3ClientFactory $s3ClientFactory;
    protected DeploymentConfig $deploymentConfig;
    protected LoggerInterface $logger;
    private ?S3Client $s3Client = null;

    public function __construct(
        ResourceConnection $resourceConnection,
        S3ClientFactory    $s3ClientFactory,
        DeploymentConfig   $deploymentConfig,
        LoggerInterface    $logger
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->s3ClientFactory = $s3ClientFactory;
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
        $bucket = $this->deploymentConfig->get(self::DEPLOYMENT_CONFIG_S3_BUCKET);
        $prefix = $this->deploymentConfig->get(self::DEPLOYMENT_CONFIG_S3_PREFIX, '');

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

    private function getS3Client(): S3Client
    {
        if ($this->s3Client === null) {
            $this->s3Client = $this->s3ClientFactory->create();
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
