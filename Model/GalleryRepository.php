<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Model;

use Exception;
use GardenLawn\MediaGallery\Api\Data\GalleryInterface;
use GardenLawn\MediaGallery\Api\GalleryRepositoryInterface;
use GardenLawn\MediaGallery\Model\ResourceModel\Gallery as GalleryResource;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\NoSuchEntityException;

class GalleryRepository implements GalleryRepositoryInterface
{
    /**
     * @var GalleryFactory
     */
    private GalleryFactory $galleryFactory;

    /**
     * @var GalleryResource
     */
    private GalleryResource $galleryResource;

    /**
     * @param GalleryFactory $galleryFactory
     * @param GalleryResource $galleryResource
     */
    public function __construct(
        GalleryFactory $galleryFactory,
        GalleryResource $galleryResource
    ) {
        $this->galleryFactory = $galleryFactory;
        $this->galleryResource = $galleryResource;
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
     * @throws AlreadyExistsException
     */
    public function save(GalleryInterface $gallery): GalleryInterface
    {
        $this->galleryResource->save($gallery);
        return $gallery;
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function delete(GalleryInterface $gallery): bool
    {
        $this->galleryResource->delete($gallery);
        return true;
    }

    /**
     * @inheritDoc
     * @throws NoSuchEntityException
     * @throws Exception
     */
    public function deleteById(int $id): bool
    {
        $gallery = $this->getById($id);
        return $this->delete($gallery);
    }
}
