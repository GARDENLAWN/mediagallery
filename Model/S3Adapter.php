<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Model;

use Aws\S3\S3Client;
use Magento\Framework\App\DeploymentConfig;
use Exception;

/**
 * Centralized adapter for all S3 operations.
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
            'Metadata' => [
                'CacheControl' => 'public, max-age=31536000'
            ]
        ]);
    }

    /**
     * @throws Exception
     */
    public function deleteFolder(string $path): void
    {
        $s3Client = $this->getS3Client();
        $fullPathPrefix = $this->getFullS3Path($path) . '/';
        $s3Client->deleteMatchingObjects($this->bucket, $fullPathPrefix);
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

        $this->deleteFolder($oldPath);
    }

    /**
     * @throws Exception
     */
    public function uploadFile(string $filePath, string $destinationPath): void
    {
        $s3Client = $this->getS3Client();
        $fullPath = $this->getFullS3Path($destinationPath);

        $s3Client->putObject([
            'Bucket' => $this->bucket,
            'Key' => $fullPath,
            'SourceFile' => $filePath,
            'Metadata' => [
                'CacheControl' => 'public, max-age=31536000'
            ]
        ]);
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
        $s3Client = $this->getS3Client();
        $fullPath = $this->getFullS3Path($path);

        $s3Client->deleteObject([
            'Bucket' => $this->bucket,
            'Key' => $fullPath,
        ]);
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
        ];
        return $map[$ext] ?? 'application/octet-stream';
    }
}
