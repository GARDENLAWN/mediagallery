<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Block;

use GardenLawn\MediaGallery\Model\GalleryFactory;
use GardenLawn\MediaGalleryAsset\Model\ResourceModel\Asset\CollectionFactory as AssetCollectionFactory;
use GardenLawn\MediaGalleryAsset\Model\Asset\Uploader;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class Gallery extends Template
{
    /**
     * @var GalleryFactory
     */
    protected GalleryFactory $galleryFactory;

    /**
     * @var AssetCollectionFactory
     */
    protected AssetCollectionFactory $assetCollectionFactory;

    /**
     * @var Uploader
     */
    protected Uploader $uploader;

    /**
     * @param Context $context
     * @param GalleryFactory $galleryFactory
     * @param AssetCollectionFactory $assetCollectionFactory
     * @param Uploader $uploader
     * @param array $data
     */
    public function __construct(
        Context $context,
        GalleryFactory $galleryFactory,
        AssetCollectionFactory $assetCollectionFactory,
        Uploader $uploader,
        array $data = []
    ) {
        $this->galleryFactory = $galleryFactory;
        $this->assetCollectionFactory = $assetCollectionFactory;
        $this->uploader = $uploader;
        parent::__construct($context, $data);
    }

    /**
     * Get gallery by ID.
     *
     * @param int $galleryId
     * @return \GardenLawn\MediaGallery\Model\Gallery|null
     */
    public function getGallery(int $galleryId): ?\GardenLawn\MediaGallery\Model\Gallery
    {
        $gallery = $this->galleryFactory->create();
        $this->galleryFactory->create()->getResource()->load($gallery, $galleryId);
        return $gallery->getId() ? $gallery : null;
    }

    /**
     * Get assets for a given gallery.
     *
     * @param int $galleryId
     * @return \GardenLawn\MediaGalleryAsset\Model\ResourceModel\Asset\Collection
     */
    public function getGalleryAssets(int $galleryId): \GardenLawn\MediaGalleryAsset\Model\ResourceModel\Asset\Collection
    {
        $collection = $this->assetCollectionFactory->create()
            ->addFieldToFilter('mediagallery_id', $galleryId)
            ->addFieldToFilter('enabled', 1)
            ->setOrder('sortorder', 'ASC');
        return $collection;
    }

    /**
     * Get media URL for a given file path.
     *
     * @param string $filePath
     * @return string
     */
    public function getMediaUrl(string $filePath): string
    {
        return $this->uploader->getMediaUrl($filePath);
    }
}
