<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Block\Adminhtml\Gallery;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use GardenLawn\MediaGallery\Model\ResourceModel\Gallery\CollectionFactory as GalleryCollectionFactory;
use GardenLawn\MediaGallery\Model\ResourceModel\AssetLink\CollectionFactory as AssetLinkCollectionFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\UrlInterface;

class Tiles extends Template
{
    /**
     * @var GalleryCollectionFactory
     */
    protected GalleryCollectionFactory $galleryCollectionFactory;

    /**
     * @var AssetLinkCollectionFactory
     */
    protected AssetLinkCollectionFactory $assetLinkCollectionFactory;

    /**
     * @var StoreManagerInterface
     */
    protected StoreManagerInterface $storeManager;

    /**
     * @param Context $context
     * @param GalleryCollectionFactory $galleryCollectionFactory
     * @param AssetLinkCollectionFactory $assetLinkCollectionFactory
     * @param StoreManagerInterface $storeManager
     * @param array $data
     */
    public function __construct(
        Context                    $context,
        GalleryCollectionFactory   $galleryCollectionFactory,
        AssetLinkCollectionFactory $assetLinkCollectionFactory,
        StoreManagerInterface      $storeManager,
        array                      $data = []
    )
    {
        $this->galleryCollectionFactory = $galleryCollectionFactory;
        $this->assetLinkCollectionFactory = $assetLinkCollectionFactory;
        $this->storeManager = $storeManager;
        parent::__construct($context, $data);
    }

    /**
     * Get all galleries with their first asset path.
     *
     * @return array
     * @throws NoSuchEntityException
     */
    public function getGalleriesWithThumbnails(): array
    {
        $galleries = $this->galleryCollectionFactory->create()
            ->addFieldToFilter('main_table.enabled', 1) // Corrected: specified table alias
            ->setOrder('sortorder', 'ASC');

        $galleriesWithThumbs = [];

        foreach ($galleries as $gallery) {
            $assetLinkCollection = $this->assetLinkCollectionFactory->create();
            $assetLinkCollection->addFieldToFilter('gallery_id', $gallery->getId())
                ->setOrder('sort_order', 'ASC')
                ->setPageSize(1);

            $firstAsset = $assetLinkCollection->getFirstItem();
            $path = $firstAsset->getData('path');

            $galleriesWithThumbs[] = [
                'id' => $gallery->getId(),
                'name' => $gallery->getName(),
                'thumbnail' => $path ? $this->getMediaUrl() . $path : $this->getPlaceholderImage(),
                'edit_url' => $this->getEditUrl((int)$gallery->getId())
            ];
        }

        return $galleriesWithThumbs;
    }

    /**
     * Get media base URL.
     *
     * @return string
     * @throws NoSuchEntityException
     */
    public function getMediaUrl(): string
    {
        return $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
    }

    /**
     * Get placeholder image URL.
     *
     * @return string
     */
    public function getPlaceholderImage(): string
    {
        return $this->getViewFileUrl('Magento_Catalog/images/product/placeholder/thumbnail.jpg');
    }

    /**
     * Get URL for editing a gallery.
     *
     * @param int $galleryId
     * @return string
     */
    public function getEditUrl(int $galleryId): string
    {
        return $this->getUrl('gardenlawn_mediagallery/index/edit', ['id' => $galleryId]);
    }

    /**
     * Get URL for adding a new gallery.
     *
     * @return string
     */
    public function getAddNewGalleryUrl(): string
    {
        return $this->getUrl('gardenlawn_mediagallery/index/new');
    }
}
