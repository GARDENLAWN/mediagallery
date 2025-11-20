<?php

namespace GardenLawn\MediaGallery\Cron;

use Aws\S3\S3Client;
use Exception;
use GardenLawn\Core\Utils\Logger;
use GardenLawn\Core\Utils\Utils;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class AwsS3Sync
{
    private const int MAX_SIZE = 1600;
    protected ObjectManager $objectManager;
    protected S3Client $s3client;
    protected AdapterInterface $connection;
    private bool $isTest = false;

    public function __construct()
    {
        $this->objectManager = ObjectManager::getInstance();
        $this->s3client = Utils::getS3Client();
        $resource = $this->objectManager->get('Magento\Framework\App\ResourceConnection');
        $this->connection = $resource->getConnection();
    }

    /**
     * @throws Exception
     */
    public function execute(): void
    {
        $this->moveProductImages();
        $this->deleteTmpImages();
        $this->mediaGalleryExecute();
        $this->mediaGalleryExecute("pub/media/catalog/product");
        $this->mediaGalleryExecute("pub/media/gallery");
    }

    public function deleteTmpImages(): void
    {
        $images = $this->getMediaFiles('pub');
        foreach ($images as $key) {
            if (substr_count($key, '/') == 1 && Utils::isImage($key, true)) {
                $this->s3client->deleteObject([
                    'Bucket' => Utils::Bucket,
                    'Key' => $key
                ]);
            }
        }
    }

    public function moveProductImages(): void
    {
        $images = $this->getMediaFiles('pub/pub');
        foreach ($images as $key) {
            if (Utils::isImage($key, true)) {
                $to = str_replace('pub/pub/', 'pub/', $key);
                Logger::writeLog("Copy from $key to $to");
                $this->s3client->copyObject([
                    'Bucket' => Utils::Bucket,
                    'CopySource' => Utils::Bucket . '/' . $key,
                    'Key' => $to
                ]);
                $this->s3client->deleteObject([
                    'Bucket' => Utils::Bucket,
                    'Key' => $key
                ]);
            }
        }
    }

    public function getMediaFiles(string $prefix): array
    {
        $contents = $this->s3client->listObjectsV2([
            'Bucket' => Utils::Bucket,
            'Prefix' => $prefix
        ]);

        $dirs = [];
        $dirs [] = $prefix;

        if ($contents['Contents'] != null) {
            foreach ($contents['Contents'] as $content) {
                if (str_ends_with($content['Key'], '/')) {
                    $dirs[] = $content['Key'];
                }
            }
        }

        $images = [];

        foreach ($dirs as $key => $dir) {
            $result = $this->s3client->listObjectsV2([
                'Bucket' => Utils::Bucket,
                'Prefix' => $dir
            ]);

            if ($result['Contents'] != null) {
                foreach ($result['Contents'] as $content) {
                    $path = $content['Key'];
                    $images[] = $path;
                }
            }

            $images = array_merge($images, array_unique($images));
        }

        return array_unique($images);
    }

    public function mediaGalleryExecute(string $path = "pub/media"): void
    {
        try {
            $this->isTest = false;

            $mediaUrl = Utils::getMediaUrl();
            $path = str_replace($mediaUrl, '', $path);
            $images = $this->getMediaFiles($path);

            $paths = Utils::getMediaGalleryAssetPaths();

            foreach ($images as $key) {
                $path = str_replace('pub/media/', '', $key);
                if (!in_array($path, $paths)) {
                    $fullPath = $mediaUrl . $path;
                    $path_parts = pathinfo($fullPath);
                    if (array_key_exists('extension', $path_parts)) {
                        $extension = $path_parts['extension'];
                        if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp']) &&
                            !str_contains($key, 'cache')) {
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
        } catch (Exception $e) {
            Logger::writeLog($e);
        }
    }

    private function insertToMediaGalleryAsset($path): void
    {
        if ($this->isTest) {
            return;
        }

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
        try {
            $info = getimagesize($fullPath);
            $mime = $info['mime'];
            $width = $info[0];
            $height = $info[1];
            $size = get_headers($fullPath, true)["Content-Length"];
            $hash = hash_hmac_file('sha256', $fullPath, 'secret');
            $insertSql = 'INSERT INTO media_gallery_asset(path, title, description, source, hash, content_type, width, height, size)
                SELECT "' . $path . '","' . $title . '",null , "Local", "' . $hash . '","' . $mime . '",' . $width . ',' . $height . ',' . $size;

            $this->connection->query($insertSql);
        } catch (\Http\Client\Exception) {

        }
    }
}
