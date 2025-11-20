<?php
namespace GardenLawn\MediaGallery\Model;

use Exception;
use GardenLawn\Core\Utils\Logger;
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
    public function synchronize(bool $dryRun = false, bool $enableDeletion = false): array
    {
        $bucket = $this->deploymentConfig->get(self::CONFIG_PATH_BUCKET);
        $envPrefix = $this->deploymentConfig->get(self::CONFIG_PATH_PREFIX, '');

        if (empty($bucket)) {
            throw new Exception('S3 bucket name is not configured in env.php.');
        }

        $s3MediaPrefix = $envPrefix ? rtrim($envPrefix, '/') . '/' . self::MEDIA_DIR . '/' : self::MEDIA_DIR . '/';

        $s3Files = $this->getAllS3Files($bucket, $s3MediaPrefix);
        $dbAssetPaths = $this->getExistingDbAssetPaths();

        $assetsToInsert = $this->findNewAssets($s3Files, $dbAssetPaths);
        $assetsToDelete = [];
        if ($enableDeletion) {
            $assetsToDelete = $this->findOrphanedAssets(array_keys($s3Files), $dbAssetPaths);
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
                throw new Exception('S3 credentials (key, secret, region) are not fully configured in env.php.');
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
     * Gets all file paths from S3 using the recommended Paginator for reliability.
     */
    private function getAllS3Files(string $bucket, string $prefix): array
    {
        try {
            $s3Client = $this->getS3Client();
        } catch (FileSystemException|RuntimeException) {
            return [];
        }
        $allFiles = [];

        // Use the recommended AWS SDK Paginator to handle large numbers of files automatically.
        $paginator = $s3Client->getPaginator('ListObjectsV2', [
            'Bucket' => $bucket,
            'Prefix' => $prefix
        ]);

        foreach ($paginator as $result) {
            $contents = $result->get('Contents');
            Logger::writeLog($contents);
            if (is_array($contents)) {
                foreach ($contents as $object) {
                    // Ignore directories
                    if (!str_ends_with($object['Key'], '/')) {
                        // Strip the full media prefix (e.g., "pub/media/") to get the correct relative path
                        $path = str_starts_with($object['Key'], $prefix)
                            ? substr($object['Key'], strlen($prefix))
                            : $object['Key'];

                        if (!empty($path)) {
                            $allFiles[$path] = [
                                'size' => $object['Size']
                            ];
                        }
                    }
                }
            }
        }

        return $allFiles;
    }

    private function getExistingDbAssetPaths(): array
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $connection->getTableName('media_gallery_asset');
        $select = $connection->select()->from($tableName, ['path']);
        return array_flip($connection->fetchCol($select));
    }

    private function findNewAssets(array $s3Files, array $dbAssetPaths): array
    {
        $newAssets = [];
        foreach ($s3Files as $s3Path => $s3Data) {
            if (!isset($dbAssetPaths[$s3Path])) {
                $newAssets[] = $this->prepareAssetData($s3Path, $s3Data);
            }
        }
        return $newAssets;
    }

    private function prepareAssetData(string $path, array $data): array
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $filename = pathinfo($path, PATHINFO_BASENAME);
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
        ];
    }
}
