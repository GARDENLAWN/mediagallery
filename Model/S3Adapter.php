<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Model;

use Aws\CommandPool;
use Aws\S3\S3Client;
use Aws\S3\Transfer;
use Aws\CloudFront\CloudFrontClient;
use Aws\S3\ObjectUploader;
use Aws\Exception\AwsException;
use Magento\Framework\App\DeploymentConfig;
use Exception;

/**
 * Centralized adapter for all S3 operations.
 * Optimized for reliability and performance using AWS SDK best practices.
 */
class S3Adapter
{
    private ?S3Client $s3Client = null;
    private DeploymentConfig $deploymentConfig;
    private string $bucket;
    private string $s3Prefix;

    public function __construct(DeploymentConfig $deploymentConfig)
    {
        $this->deploymentConfig = $deploymentConfig;
    }

    /**
     * @throws Exception
     */
    private function getS3Client(): S3Client
    {
        if ($this->s3Client === null) {
            $key = $this->deploymentConfig->get('remote_storage/config/credentials/key');
            $secret = $this->deploymentConfig->get('remote_storage/config/credentials/secret');
            $region = $this->deploymentConfig->get('remote_storage/config/region');
            $this->bucket = $this->deploymentConfig->get('remote_storage/config/bucket');
            $this->s3Prefix = $this->deploymentConfig->get('remote_storage/prefix', '');

            if (!$key || !$secret || !$region || !$this->bucket) {
                throw new Exception('S3 credentials are not fully configured in env.php.');
            }

            $this->s3Client = new S3Client([
                'version' => 'latest',
                'region' => $region,
                'credentials' => ['key' => $key, 'secret' => $secret],
                'retries' => 3, // Built-in SDK retries for standard requests
                'http'    => [
                    'connect_timeout' => 5,
                    'timeout'         => 30,
                ]
            ]);
        }
        return $this->s3Client;
    }

    /**
     * Synchronizes a local directory to S3 using Smart Sync (ETag comparison).
     * Prevents re-uploading identical files even if timestamps differ.
     *
     * @param string $sourceDir Local directory path.
     * @param string $storageType S3 storage type (e.g., 'static', 'media').
     * @param callable|null $callback Callback for progress/logging (receives 'upload' or 'delete').
     * @throws Exception
     */
    public function sync(string $sourceDir, string $storageType, ?callable $callback = null): void
    {
        $s3Client = $this->getS3Client();
        $bucket = $this->bucket;

        // 1. Prepare Paths
        $sourceDir = rtrim($sourceDir, '/');
        $s3Prefix = $this->getPrefixedPath($storageType, ''); // e.g. "pub/static/"
        $s3Prefix = rtrim($s3Prefix, '/') . '/';

        // 2. Fetch S3 Inventory (Key -> ETag)
        // We need to know what's already there to avoid re-uploading
        $s3Objects = [];
        $paginator = $s3Client->getPaginator('ListObjectsV2', [
            'Bucket' => $bucket,
            'Prefix' => $s3Prefix
        ]);

        foreach ($paginator as $page) {
            foreach ($page['Contents'] ?? [] as $object) {
                // ETag is usually wrapped in quotes
                $s3Objects[$object['Key']] = trim($object['ETag'], '"');
            }
        }

        // 3. Scan Local & Compare
        $filesToUpload = []; // [ 'source' => ..., 'key' => ... ]
        $keptKeys = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isDir()) continue;

            $localPath = $file->getPathname();
            $relativePath = substr($localPath, strlen($sourceDir) + 1);
            // Fix windows separators if any
            $relativePath = str_replace('\\', '/', $relativePath);

            $s3Key = $s3Prefix . $relativePath;

            // Calculate Hash (MD5) to compare content, not timestamp
            $localHash = md5_file($localPath);

            if (isset($s3Objects[$s3Key]) && $s3Objects[$s3Key] === $localHash) {
                // Content is identical, skip upload
                $keptKeys[$s3Key] = true;
            } else {
                // Content changed or new file
                $filesToUpload[] = [
                    'source' => $localPath,
                    'key' => $s3Key,
                    'type' => $this->getContentTypeByPath($localPath)
                ];
                $keptKeys[$s3Key] = true; // It will be there after upload
            }
        }

        // 4. Upload Changed Files (Concurrency using CommandPool)
        if (!empty($filesToUpload)) {
            $commands = function () use ($s3Client, $bucket, $filesToUpload) {
                foreach ($filesToUpload as $file) {
                    yield $s3Client->getCommand('PutObject', [
                        'Bucket' => $bucket,
                        'Key' => $file['key'],
                        'SourceFile' => $file['source'],
                        'ContentType' => $file['type'],
                        'CacheControl' => 'public, max-age=0, must-revalidate',
                    ]);
                }
            };

            $pool = new CommandPool($s3Client, $commands(), [
                'concurrency' => 25,
                'fulfilled' => function ($result, $iterKey, $aggregatePromise) use ($callback) {
                    if ($callback) $callback('upload');
                },
                'rejected' => function ($reason, $iterKey, $aggregatePromise) {
                    $msg = $reason instanceof \Exception ? $reason->getMessage() : (string)$reason;
                    throw new Exception("Upload failed: " . $msg);
                },
            ]);

            $promise = $pool->promise();
            $promise->wait();
        }

        // 5. Delete Orphans (Files on S3 that are not local)
        $keysToDelete = [];
        foreach (array_keys($s3Objects) as $s3Key) {
            if (!isset($keptKeys[$s3Key])) {
                $keysToDelete[] = $s3Key;
            }
        }

        if (!empty($keysToDelete)) {
            $this->deleteObjects($keysToDelete);
            if ($callback) {
                foreach ($keysToDelete as $k) $callback('delete');
            }
        }
    }

    /**
     * Creates a CloudFront invalidation.
     *
     * @param string $distributionId
     * @param array $paths
     * @return string The Invalidation ID.
     * @throws Exception
     */
    public function invalidateCloudFront(string $distributionId, array $paths = ['/*']): string
    {
        $key = $this->deploymentConfig->get('remote_storage/config/credentials/key');
        $secret = $this->deploymentConfig->get('remote_storage/config/credentials/secret');
        $region = $this->deploymentConfig->get('remote_storage/config/region') ?? 'us-east-1';

        $cfClient = new CloudFrontClient([
            'version' => 'latest',
            'region' => $region,
            'credentials' => ['key' => $key, 'secret' => $secret],
            'http'    => [
                'connect_timeout' => 5,
                'timeout'         => 30,
            ]
        ]);

        $result = $cfClient->createInvalidation([
            'DistributionId' => $distributionId,
            'InvalidationBatch' => [
                'CallerReference' => uniqid(),
                'Paths' => [
                    'Quantity' => count($paths),
                    'Items' => $paths,
                ],
            ],
        ]);

        return $result->get('Invalidation')['Id'];
    }

    /**
     * @throws Exception
     */
    public function getFullS3Path(string $path): string
    {
        $this->getS3Client(); // Ensure client and properties are initialized
        return ($this->s3Prefix ? rtrim($this->s3Prefix, '/') . '/' : '') . 'media/' . ltrim($path, '/');
    }

    /**
     * @throws Exception
     */
    public function createFolder(string $path): void
    {
        $s3Client = $this->getS3Client();
        $fullPath = $this->getFullS3Path($path) . '/';
        $s3Client->putObject([
            'Bucket' => $this->bucket,
            'Key' => $fullPath,
            'Body' => '',
            'CacheControl' => 'public, max-age=31536000',
        ]);
    }

    /**
     * @throws Exception
     */
    public function deleteFolder(string $path, bool $forceDeleteAll = false): void
    {
        $s3Client = $this->getS3Client();
        $fullPathPrefix = $this->getFullS3Path($path) . '/';

        if ($forceDeleteAll) {
            $s3Client->deleteMatchingObjects($this->bucket, $fullPathPrefix);
        } else {
            // Delete everything EXCEPT .svg files
            $s3Client->deleteMatchingObjects($this->bucket, $fullPathPrefix, '/^.*(?<!\.svg)$/i');
        }
    }

    /**
     * Deletes a folder by full S3 prefix (bypassing getFullS3Path logic if needed).
     * Useful for static content which might have different prefix logic.
     *
     * @param string $fullPrefix
     * @throws Exception
     */
    public function deleteByPrefix(string $fullPrefix): void
    {
        $s3Client = $this->getS3Client();
        // Ensure prefix ends with / to avoid deleting partial matches (e.g. version1 vs version10)
        $fullPrefix = rtrim($fullPrefix, '/') . '/';
        $s3Client->deleteMatchingObjects($this->bucket, $fullPrefix);
    }

    /**
     * @throws Exception
     */
    public function moveFolder(string $oldPath, string $newPath): void
    {
        $s3Client = $this->getS3Client();
        $oldFullPathPrefix = $this->getFullS3Path($oldPath) . '/';
        $newFullPathPrefix = $this->getFullS3Path($newPath) . '/';

        $objects = $s3Client->getIterator('ListObjects', [
            'Bucket' => $this->bucket,
            'Prefix' => $oldFullPathPrefix
        ]);

        foreach ($objects as $object) {
            $sourceKey = $object['Key'];
            $destinationKey = str_replace($oldFullPathPrefix, $newFullPathPrefix, $sourceKey);

            $s3Client->copyObject([
                'Bucket' => $this->bucket,
                'CopySource' => "{$this->bucket}/{$sourceKey}",
                'Key' => $destinationKey,
            ]);
        }

        $this->deleteFolder($oldPath, true);
    }

    /**
     * Uploads a file using ObjectUploader (handles Multipart automatically).
     *
     * @throws Exception
     */
    public function uploadFile(string $filePath, string $destinationPath): void
    {
        $s3Client = $this->getS3Client();
        $fullPath = $this->getFullS3Path($destinationPath);
        $contentType = $this->getContentTypeByPath($destinationPath);

        // Use ObjectUploader for smart multipart handling
        $source = fopen($filePath, 'rb');
        $uploader = new ObjectUploader(
            $s3Client,
            $this->bucket,
            $fullPath,
            $source,
            'public-read',
            [
                'params' => [
                    'ContentType' => $contentType,
                    'CacheControl' => 'public, max-age=31536000',
                ]
            ]
        );

        try {
            $uploader->upload();
        } finally {
            if (is_resource($source)) {
                fclose($source);
            }
        }
    }

    /**
     * Uploads a static asset file.
     *
     * @param string $filePath The local path to the file.
     * @param string $destinationPath The destination path relative to the 'static' directory on S3.
     * @throws Exception
     */
    public function uploadStaticFile(string $filePath, string $destinationPath): void
    {
        $s3Client = $this->getS3Client();
        $fullKey = $this->getPrefixedPath('static', $destinationPath);

        $s3Client->putObject([
            'Bucket' => $this->bucket,
            'Key' => $fullKey,
            'SourceFile' => $filePath,
            'ContentType' => $this->getContentTypeByPath($destinationPath),
            'CacheControl' => 'public, max-age=31536000',
        ]);
    }

    /**
     * Uploads multiple static asset files concurrently with Retry Logic.
     *
     * @param array $files An array of files, where each element is an array with 'sourcePath' and 'destinationPath'.
     *                     Optionally 'copyFromS3Key' can be set to perform a server-side copy instead of upload.
     * @param callable|null $progressCallback A callback to be invoked as files are uploaded.
     * @param int $concurrency Number of concurrent uploads.
     * @throws Exception
     */
    public function uploadStaticFiles(array $files, ?callable $progressCallback = null, int $concurrency = 25): void
    {
        $s3Client = $this->getS3Client();
        $maxRetries = 3;

        $commands = function () use ($s3Client, $files) {
            foreach ($files as $file) {
                $fullKey = $this->getPrefixedPath('static', $file['destinationPath']);
                $contentType = $this->getContentTypeByPath($file['destinationPath']);

                if (isset($file['copyFromS3Key']) && $file['copyFromS3Key']) {
                    // Perform server-side copy
                    $copySource = $this->bucket . '/' . $file['copyFromS3Key'];

                    yield $s3Client->getCommand('CopyObject', [
                        'Bucket' => $this->bucket,
                        'Key' => $fullKey,
                        'CopySource' => str_replace('+', '%2B', $copySource),
                        'ContentType' => $contentType,
                        'CacheControl' => 'public, max-age=31536000',
                        'MetadataDirective' => 'REPLACE'
                    ]);
                } else {
                    // Perform standard upload
                    yield $s3Client->getCommand('PutObject', [
                        'Bucket' => $this->bucket,
                        'Key' => $fullKey,
                        'SourceFile' => $file['sourcePath'],
                        'ContentType' => $contentType,
                        'CacheControl' => 'public, max-age=31536000',
                    ]);
                }
            }
        };

        $pool = new CommandPool($s3Client, $commands(), [
            'concurrency' => $concurrency,
            'fulfilled' => function ($result, $index) use ($progressCallback) {
                if (is_callable($progressCallback)) {
                    $progressCallback();
                }
            },
            'rejected' => function ($reason, $index) {
                $msg = $reason instanceof \Exception ? $reason->getMessage() : (string)$reason;
                throw new Exception("Failed to upload/copy file at index {$index}. Reason: {$msg}");
            },
        ]);

        $promise = $pool->promise();
        $promise->wait();
    }

    /**
     * @param string $content The file content to upload.
     * @param string $destinationKey The full S3 key (path) for the destination object.
     * @throws Exception
     */
    public function uploadContent(string $content, string $destinationKey): void
    {
        $s3Client = $this->getS3Client();

        $s3Client->putObject([
            'Bucket' => $this->bucket,
            'Key' => $destinationKey,
            'Body' => $content,
            'ContentType' => $this->getContentTypeByPath($destinationKey),
            'CacheControl' => 'public, max-age=31536000',
        ]);
    }

    /**
     * Get the S3 prefix for a given storage type (e.g., 'static', 'media').
     * @throws Exception
     */
    public function getPrefixedPath(string $storageType, string $filePath): string
    {
        $this->getS3Client(); // Ensure client and properties are initialized
        $storageType = rtrim($storageType, '/');
        $filePath = ltrim($filePath, '/');
        $prefix = $this->s3Prefix ? rtrim($this->s3Prefix, '/') . '/' : '';

        return $prefix . $storageType . '/' . $filePath;
    }

    /**
     * @throws Exception
     */
    public function listObjects(string $prefix = '', array $extensions = []): \Generator
    {
        $s3Client = $this->getS3Client();
        $fullPrefix = $this->getFullS3Path($prefix);

        $paginator = $s3Client->getPaginator('ListObjectsV2', [
            'Bucket' => $this->bucket,
            'Prefix' => $fullPrefix,
        ]);

        foreach ($paginator as $result) {
            foreach ($result['Contents'] ?? [] as $object) {
                $key = $object['Key'];
                if ($extensions) {
                    $ext = pathinfo($key, PATHINFO_EXTENSION);
                    if (in_array($ext, $extensions)) {
                        yield $key;
                    }
                } else {
                    yield $key;
                }
            }
        }
    }

    /**
     * @throws Exception
     */
    public function listObjectsByStorageType(string $storageType, string $prefix = ''): \Generator
    {
        $s3Client = $this->getS3Client();
        $fullPrefix = $this->getPrefixedPath($storageType, $prefix);

        $paginator = $s3Client->getPaginator('ListObjectsV2', [
            'Bucket' => $this->bucket,
            'Prefix' => $fullPrefix,
        ]);

        foreach ($paginator as $result) {
            if (isset($result['Contents']) && is_array($result['Contents'])) {
                foreach ($result['Contents'] as $object) {
                    yield $object;
                }
            }
        }
    }

    /**
     * Lists "directories" (common prefixes) within a specific path.
     * Useful for finding version folders like 'static/version123/'
     *
     * @param string $storageType
     * @param string $prefix
     * @return array
     * @throws Exception
     */
    public function listDirectoriesByStorageType(string $storageType, string $prefix = ''): array
    {
        $s3Client = $this->getS3Client();
        $fullPrefix = $this->getPrefixedPath($storageType, $prefix);
        // Ensure prefix ends with / if not empty
        if (!empty($fullPrefix) && !str_ends_with($fullPrefix, '/')) {
            $fullPrefix .= '/';
        }

        $results = $s3Client->listObjectsV2([
            'Bucket' => $this->bucket,
            'Prefix' => $fullPrefix,
            'Delimiter' => '/'
        ]);

        $directories = [];
        if (isset($results['CommonPrefixes'])) {
            foreach ($results['CommonPrefixes'] as $prefixInfo) {
                $directories[] = $prefixInfo['Prefix'];
            }
        }

        return $directories;
    }

    /**
     * @throws Exception
     */
    public function doesObjectExist(string $path): bool
    {
        $s3Client = $this->getS3Client();
        $fullPath = $this->getFullS3Path($path);

        return $s3Client->doesObjectExist($this->bucket, $fullPath);
    }

    /**
     * @throws Exception
     */
    public function deleteObject(string $path): void
    {
        if (str_ends_with(strtolower($path), '.svg')) {
            return;
        }

        $s3Client = $this->getS3Client();
        $fullPath = $this->getFullS3Path($path);

        $s3Client->deleteObject([
            'Bucket' => $this->bucket,
            'Key' => $fullPath,
        ]);
    }

    /**
     * Deletes multiple objects from S3.
     *
     * @param array $keys Array of full S3 keys to delete.
     * @throws Exception
     */
    public function deleteObjects(array $keys): void
    {
        if (empty($keys)) {
            return;
        }

        $s3Client = $this->getS3Client();

        // S3 deleteObjects accepts max 1000 keys per request
        $chunks = array_chunk($keys, 1000);

        foreach ($chunks as $chunk) {
            $objects = array_map(function ($key) {
                return ['Key' => $key];
            }, $chunk);

            $s3Client->deleteObjects([
                'Bucket' => $this->bucket,
                'Delete' => [
                    'Objects' => $objects,
                ],
            ]);
        }
    }

    /**
     * Determines the MIME type of a file based on its extension.
     */
    private function getContentTypeByPath(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $map = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'svg' => 'image/svg+xml',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'avif' => 'image/avif',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject',
            'otf' => 'font/otf',
            'json' => 'application/json',
            'html' => 'text/html',
            'xml' => 'application/xml',
            'ico' => 'image/x-icon',
            'txt' => 'text/plain',
            'map' => 'application/json',
            'gz'  => 'application/x-gzip',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'pdf' => 'application/pdf',
            'zip' => 'application/zip',
        ];
        return $map[$ext] ?? 'application/octet-stream';
    }
}
