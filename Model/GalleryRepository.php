<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Model;

use Aws\S3\S3Client;
use Exception;
use GardenLawn\MediaGallery\Api\Data\GalleryInterface;
use GardenLawn\MediaGallery\Api\GalleryRepositoryInterface;
use GardenLawn\MediaGallery\Model\ResourceModel\Gallery as GalleryResource;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

class GalleryRepository implements GalleryRepositoryInterface
{
    private GalleryFactory $galleryFactory;
    private GalleryResource $galleryResource;
    private ?S3Client $s3Client = null;
    private DeploymentConfig $deploymentConfig;
    private string $bucket;
    private string $s3Prefix;

    public function __construct(
        GalleryFactory $galleryFactory,
        GalleryResource $galleryResource,
        DeploymentConfig $deploymentConfig
    ) {
        $this->galleryFactory = $galleryFactory;
        $this->galleryResource = $galleryResource;
        $this->deploymentConfig = $deploymentConfig;
    }

    public function getById(int $id): GalleryInterface
    {
        $gallery = $this->galleryFactory->create();
        $this->galleryResource->load($gallery, $id);
        if (!$gallery->getId()) {
            throw new NoSuchEntityException(__('Gallery with id "%1" does not exist.', $id));
        }
        return $gallery;
    }

    /**
     * @throws CouldNotSaveException
     */
    public function save(GalleryInterface $gallery): GalleryInterface
    {
        $originalPath = null;
        if ($gallery->getId()) {
            try {
                $originalGallery = $this->getById($gallery->getId());
                $originalPath = $originalGallery->getPath();
            } catch (NoSuchEntityException $e) {
                // This can happen, it's not critical.
            }
        }

        try {
            $this->galleryResource->save($gallery);
        } catch (Exception $e) {
            throw new CouldNotSaveException(__('Could not save the gallery.'), $e);
        }

        $newPath = $gallery->getPath();

        // Handle S3 operations after successful DB save
        try {
            if ($originalPath && $newPath !== $originalPath) {
                // Path has changed, move folder in S3
                $this->moveS3Folder($originalPath, $newPath);
            } elseif (!$originalPath && $newPath) {
                // New gallery, create folder in S3
                $this->createS3Folder($newPath);
            }
        } catch (Exception $e) {
            // S3 operation failed, but DB is saved. Log the error.
            // In a real-world scenario, you might want to add compensating transaction logic.
            throw new CouldNotSaveException(__('Gallery was saved to the database, but an error occurred with S3 operation: %1', $e->getMessage()), $e);
        }

        return $gallery;
    }

    /**
     * @throws CouldNotDeleteException
     */
    public function delete(GalleryInterface $gallery): bool
    {
        $pathToDelete = $gallery->getPath();
        $id = $gallery->getId();

        try {
            $this->galleryResource->delete($gallery);
        } catch (Exception $e) {
            throw new CouldNotDeleteException(__('Could not delete gallery with ID %1', $id), $e);
        }

        // Handle S3 deletion after successful DB deletion
        try {
            if ($pathToDelete) {
                $this->deleteS3Folder($pathToDelete);
            }
        } catch (Exception $e) {
            throw new CouldNotDeleteException(__('Gallery was deleted from the database, but an error occurred while deleting the S3 folder: %1', $e->getMessage()), $e);
        }

        return true;
    }

    public function deleteById(int $id): bool
    {
        $gallery = $this->getById($id);
        return $this->delete($gallery);
    }

    // --- S3 Helper Methods ---

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

    private function getFullS3Path(string $path): string
    {
        return ($this->s3Prefix ? rtrim($this->s3Prefix, '/') . '/' : '') . 'media/' . ltrim($path, '/');
    }

    private function createS3Folder(string $path): void
    {
        $s3Client = $this->getS3Client();
        $fullPath = $this->getFullS3Path($path) . '/'; // Folder keys end with a slash
        $s3Client->putObject([
            'Bucket' => $this->bucket,
            'Key' => $fullPath,
            'Body' => ''
        ]);
    }

    private function deleteS3Folder(string $path): void
    {
        $s3Client = $this->getS3Client();
        $fullPathPrefix = $this->getFullS3Path($path) . '/';
        $s3Client->deleteMatchingObjects($this->bucket, $fullPathPrefix);
    }

    private function moveS3Folder(string $oldPath, string $newPath): void
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

        $this->deleteS3Folder($oldPath);
    }
}
