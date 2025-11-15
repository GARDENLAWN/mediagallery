<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Model;

use GardenLawn\MediaGallery\Api\Data\GalleryInterface;
use GardenLawn\MediaGallery\Api\GalleryRepositoryInterface;
use GardenLawn\MediaGallery\Model\ResourceModel\Gallery as GalleryResource;
use GardenLawn\MediaGallery\Model\ResourceModel\Gallery\CollectionFactory;
use Magento\Framework\Exception\NoSuchEntityException;

class GalleryRepository implements GalleryRepositoryInterface
{
    /**
     * @var GalleryFactory
     */
    private $galleryFactory;

    /**
     * @var GalleryResource
     */
    private $galleryResource;

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @param GalleryFactory $galleryFactory
     * @param GalleryResource $galleryResource
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        GalleryFactory $galleryFactory,
        GalleryResource $galleryResource,
        CollectionFactory $collectionFactory
    ) {
        $this->galleryFactory = $galleryFactory;
        $this->galleryResource = $galleryResource;
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * @inheritDoc
     */
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
     * @inheritDoc
     */
    public function save(GalleryInterface $gallery): GalleryInterface
    {
        $this->galleryResource->save($gallery);
        return $gallery;
    }

    /**
     * @inheritDoc
     */
    public function delete(GalleryInterface $gallery): bool
    {
        $this->galleryResource->delete($gallery);
        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteById(int $id): bool
    {
        $gallery = $this->getById($id);
        return $this->delete($gallery);
    }
}
