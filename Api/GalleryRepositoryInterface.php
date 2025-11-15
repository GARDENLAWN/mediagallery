<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Api;

use GardenLawn\MediaGallery\Api\Data\GalleryInterface;
use Magento\Framework\Exception\NoSuchEntityException;

interface GalleryRepositoryInterface
{
    /**
     * @param int $id
     * @return GalleryInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $id): GalleryInterface;

    /**
     * @param GalleryInterface $gallery
     * @return GalleryInterface
     */
    public function save(GalleryInterface $gallery): GalleryInterface;

    /**
     * @param GalleryInterface $gallery
     * @return bool
     */
    public function delete(GalleryInterface $gallery): bool;

    /**
     * @param int $id
     * @return bool
     */
    public function deleteById(int $id): bool;
}
