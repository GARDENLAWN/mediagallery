<?php

namespace GardenLawn\MediaGallery\Cron;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use GardenLawn\Core\Utils\Logger;
use GardenLawn\Core\Utils\Utils;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class AwsS3Sync
{
    private const string PATH_MEDIA = 'pub/media';
    private const string PATH_MEDIA_CATALOG_PRODUCT = 'pub/media/catalog/product';
    private const string PATH_MEDIA_GALLERY = 'pub/media/gallery';

    protected S3Client $s3client;
    protected AdapterInterface $connection;
    private bool $isTest = false;

    public function __construct(ResourceConnection $resource)
    {
        $this->s3client = Utils::getS3Client();
        $this->connection = $resource->getConnection();
    }

    public function execute(): void
    {
        try {
            $this->moveProductImages();
            $this->deleteTmpImages();
            $this->mediaGalleryExecute();
            $this->mediaGalleryExecute(self::PATH_MEDIA_CATALOG_PRODUCT);
            $this->mediaGalleryExecute(self::PATH_MEDIA_GALLERY);
        } catch (S3Exception $e) {
            Logger::writeLog($e);
        }
    }

    public function deleteTmpImages(): void
    {
        $images = $this->getMediaFiles('pub');
        foreach ($images as $image) {
            if (substr_count($image, '/') == 1 && Utils::isImage($image, true)) {
                $this->s3client->deleteObject([
                    'Bucket' => Utils::Bucket,
                    'Key' => $image
                ]);
            }
        }
    }

    public function moveProductImages(): void
    {
        $images = $this->getMediaFiles('pub/pub');
        foreach ($images as $image) {
            if (Utils::isImage($image, true)) {
                $to = str_replace('pub/pub/', 'pub/', $image);
                Logger::writeLog("Copy from $image to $to");
                $this->s3client->copyObject([
                    'Bucket' => Utils::Bucket,
                    'CopySource' => Utils::Bucket . '/' . $image,
                    'Key' => $to
                ]);
                $this->s3client->deleteObject([
                    'Bucket' => Utils::Bucket,
                    'Key' => $image
                ]);
            }
        }
    }

    public function getMediaFiles(string $prefix): array
    {
        $allFiles = [];
        $continuationToken = null;

        do {
            $result = $this->s3client->listObjectsV2([
                'Bucket' => Utils::Bucket,
                'Prefix' => $prefix,
                'ContinuationToken' => $continuationToken,
            ]);

            if (!empty($result['Contents'])) {
                foreach ($result['Contents'] as $content) {
                    $allFiles[] = $content['Key'];
                }
            }

            $continuationToken = $result['NextContinuationToken'] ?? null;
        } while ($continuationToken);

        return array_unique($allFiles);
    }

    public function mediaGalleryExecute(string $path = self::PATH_MEDIA): void
    {
        try {
            $this->isTest = false;

            $mediaUrl = Utils::getMediaUrl();
            $path = str_replace($mediaUrl, '', $path);
            $images = $this->getMediaFiles($path);

            $existingPaths = array_flip(Utils::getMediaGalleryAssetPaths());

            foreach ($images as $image) {
                $path = str_replace('pub/media/', '', $image);
                if (!array_key_exists($path, $existingPaths)) {
                    $fullPath = $mediaUrl . $path;
                    $path_parts = pathinfo($fullPath);
                    if (array_key_exists('extension', $path_parts)) {
                        $extension = $path_parts['extension'];
                        if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp']) &&
                            !str_contains($image, 'cache')) {
                            $this->insertToMediaGalleryAsset($path);
                        }
                    }
                }
            }

            $paths = Utils::getMediaGalleryAssetPaths();
            $dirs = [];

            foreach ($paths as $path) {
                $dirs [] = dirname($path);
            }

            $dirs = array_unique($dirs);
            foreach ($dirs as $dir) {
                $insertSql = "INSERT INTO gardenlawn_mediagallery (name) SELECT '" . $dir . "' WHERE NOT EXISTS (SELECT * FROM gardenlawn_mediagallery WHERE name = '" . $dir . "')";
                $this->connection->query($insertSql);
            }

            $assetsToUpdate = Utils::getMediaGalleryAssetWithoutMediaGallery();
            foreach ($assetsToUpdate as $asset) {
                $updateSql = "UPDATE media_gallery_asset SET mediagallery_id = (SELECT m.id FROM gardenlawn_mediagallery m WHERE m.name = '" . dirname($asset['path']) . "') WHERE id = " . $asset['id'];
                $this->connection->query($updateSql);
            }
        } catch (NoSuchEntityException $e) {
            Logger::writeLog($e);
        }
    }

    private function insertToMediaGalleryAsset($path): void
    {
        if ($this->isTest) {
            return;
        }

        try {
            $mediaUrl = Utils::getMediaUrl();
            $path = str_replace('pub/media/', '', str_replace($mediaUrl, '', $path));
            $fullPath = $mediaUrl . str_replace(' ', '+', $path);

            $select = "SELECT * FROM media_gallery_asset WHERE path = '" . $path . "'";
            $result = $this->connection->fetchAll($select);

            if (count($result) > 0) {
                return;
            }

            $path_parts = pathinfo($fullPath);
            $title = str_replace('_', ' ', $path_parts['filename']);
            $info = getimagesize($fullPath);
            $mime = $info['mime'];
            $width = $info[0];
            $height = $info[1];
            $size = get_headers($fullPath, true)["Content-Length"];
            $hash = hash_hmac_file('sha256', $fullPath, 'secret');
            $insertSql = 'INSERT INTO media_gallery_asset(path, title, description, source, hash, content_type, width, height, size)
                SELECT "' . $path . '","' . $title . '",null , "Local", "' . $hash . '","' . $mime . '",' . $width . ',' . $height . ',' . $size;

            $this->connection->query($insertSql);
        } catch (NoSuchEntityException $e) {
            Logger::writeLog($e);
        }
    }
}
