<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Model;

use Exception;
use GardenLawn\MediaGallery\Model\S3Adapter;
use GardenLawn\MediaGallery\Api\Data\GalleryInterface;
use GardenLawn\MediaGallery\Api\GalleryRepositoryInterface;
use GardenLawn\MediaGallery\Model\ResourceModel\Gallery as GalleryResource;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

class GalleryRepository implements GalleryRepositoryInterface
{
    private GalleryFactory $galleryFactory;
    private GalleryResource $galleryResource;
    private S3Adapter $s3Adapter;

    public function __construct(
        GalleryFactory $galleryFactory,
        GalleryResource $galleryResource,
        S3Adapter $s3Adapter
    ) {
        $this->galleryFactory = $galleryFactory;
        $this->galleryResource = $galleryResource;
        $this->s3Adapter = $s3Adapter;
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
                // Use raw resource load to avoid circular dependency or infinite loops
                $originalGallery = $this->galleryFactory->create();
                $this->galleryResource->load($originalGallery, $gallery->getId());
                $originalPath = $originalGallery->getPath();
            } catch (Exception $e) {
                // Not critical if it fails
            }
        }

        try {
            $this->galleryResource->save($gallery);
        } catch (Exception $e) {
            throw new CouldNotSaveException(__('Could not save the gallery.'), $e);
        }

        $newPath = $gallery->getPath();

        try {
            if ($originalPath && $newPath !== $originalPath) {
                $this->s3Adapter->moveFolder($originalPath, $newPath);
            } elseif (!$originalPath && $newPath) {
                $this->s3Adapter->createFolder($newPath);
            }
        } catch (Exception $e) {
            throw new CouldNotSaveException(__('Gallery was saved, but an S3 error occurred: %1', $e->getMessage()), $e);
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

        try {
            if ($pathToDelete) {
                $this->s3Adapter->deleteFolder($pathToDelete);
            }
        } catch (Exception $e) {
            throw new CouldNotDeleteException(__('Gallery was deleted, but an S3 error occurred: %1', $e->getMessage()), $e);
        }

        return true;
    }

    /**
     * @throws NoSuchEntityException
     * @throws CouldNotDeleteException
     */
    public function deleteById(int $id): bool
    {
        $gallery = $this->getById($id);
        return $this->delete($gallery);
    }
}
