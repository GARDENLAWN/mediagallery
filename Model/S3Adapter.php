<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Model;

use Aws\CommandPool;
use Aws\S3\S3Client;
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
            // ACL removed: Use Bucket Policy for public access
            'Metadata' => [
                'CacheControl' => 'public, max-age=31536000'
            ]
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
                // ACL removed
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
            'public-read', // ACL (kept here as ObjectUploader might default to private, but check bucket settings)
            [
                'params' => [
                    'ContentType' => $contentType,
                    'Metadata' => [
                        'CacheControl' => 'public, max-age=31536000'
                    ]
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
            // ACL removed
            'Metadata' => [
                'CacheControl' => 'public, max-age=31536000'
            ]
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
                        // ACL removed
                        'Metadata' => [
                            'CacheControl' => 'public, max-age=31536000'
                        ],
                        'MetadataDirective' => 'REPLACE'
                    ]);
                } else {
                    // Perform standard upload
                    yield $s3Client->getCommand('PutObject', [
                        'Bucket' => $this->bucket,
                        'Key' => $fullKey,
                        'SourceFile' => $file['sourcePath'],
                        'ContentType' => $contentType,
                        // ACL removed
                        'Metadata' => [
                            'CacheControl' => 'public, max-age=31536000'
                        ]
                    ]);
                }
            }
        };

        // Retry logic wrapper
        $attempt = 0;
        $failedFiles = [];

        // We can't easily retry individual commands inside CommandPool generator without complex logic.
        // Instead, we rely on S3Client's built-in HTTP retries (configured in constructor).
        // If CommandPool fails, it throws exception for the specific command.

        // However, for bulk operations, we want to fail fast on fatal errors but maybe log warnings on minor ones?
        // No, for static sync, consistency is key. If one file fails, the deployment is broken.
        // So we rely on the robust S3Client retries (set to 3 in constructor).

        $pool = new CommandPool($s3Client, $commands(), [
            'concurrency' => $concurrency,
            'fulfilled' => function ($result, $index) use ($progressCallback) {
                if (is_callable($progressCallback)) {
                    $progressCallback();
                }
            },
            'rejected' => function ($reason, $index) {
                // $reason is the exception
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
            // ACL removed
            'Metadata' => [
                'CacheControl' => 'public, max-age=31536000'
            ]
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
