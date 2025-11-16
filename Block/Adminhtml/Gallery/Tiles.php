<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Block\Adminhtml\Gallery;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use GardenLawn\MediaGallery\Model\ResourceModel\Gallery\Collection as GalleryCollection;
use GardenLawn\MediaGallery\Model\ResourceModel\Gallery\CollectionFactory as GalleryCollectionFactory;
use GardenLawn\MediaGallery\Model\ResourceModel\AssetLink\CollectionFactory as AssetLinkCollectionFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\Serialize\Serializer\Json;

class Tiles extends Template
{
    protected GalleryCollectionFactory $galleryCollectionFactory;
    protected AssetLinkCollectionFactory $assetLinkCollectionFactory;
    protected StoreManagerInterface $storeManager;
    private Json $jsonSerializer;

    public function __construct(
        Context $context,
        GalleryCollectionFactory $galleryCollectionFactory,
        AssetLinkCollectionFactory $assetLinkCollectionFactory,
        StoreManagerInterface $storeManager,
        Json $jsonSerializer,
        array $data = []
    ) {
        $this->galleryCollectionFactory = $galleryCollectionFactory;
        $this->assetLinkCollectionFactory = $assetLinkCollectionFactory;
        $this->storeManager = $storeManager;
        $this->jsonSerializer = $jsonSerializer;
        parent::__construct($context, $data);
    }

    /**
     * Get all galleries with extended data for the tile view.
     *
     * @return array
     * @throws NoSuchEntityException
     */
    public function getGalleriesData(): array
    {
        /** @var GalleryCollection $galleries */
        $galleries = $this->galleryCollectionFactory->create()->setOrder('sortorder', 'ASC');
        $galleries->joinAssetCount();

        $galleriesData = [];

        foreach ($galleries as $gallery) {
            $assetLinkCollection = $this->assetLinkCollectionFactory->create();
            $assetLinkCollection->addFieldToFilter('gallery_id', $gallery->getId())
                ->setOrder('sortorder', 'ASC')
                ->setPageSize(1);

            $firstAsset = $assetLinkCollection->getFirstItem();
            $path = $firstAsset->getData('path');

            $galleriesData[] = [
                'id' => $gallery->getId(),
                'name' => $gallery->getName(),
                'enabled' => (bool)$gallery->getEnabled(),
                'asset_count' => (int)$gallery->getData('asset_count'),
                'thumbnail' => $path ? $this->getMediaUrl() . $path : $this->getPlaceholderImage(),
                'edit_url' => $this->getEditUrl((int)$gallery->getId())
            ];
        }

        return $galleriesData;
    }

    public function getMediaUrl(): string
    {
        return $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
    }

    public function getPlaceholderImage(): string
    {
        return $this->getViewFileUrl('Magento_Catalog/images/product/placeholder/thumbnail.jpg');
    }

    public function getEditUrl(int $galleryId): string
    {
        return $this->getUrl('gardenlawn_mediagallery/index/edit', ['id' => $galleryId]);
    }

    public function getAddNewGalleryUrl(): string
    {
        return $this->getUrl('gardenlawn_mediagallery/index/new');
    }

    public function getSaveOrderUrl(): string
    {
        return $this->getUrl('gardenlawn_mediagallery/index/saveorder');
    }

    public function getToggleStatusUrl(): string
    {
        return $this->getUrl('gardenlawn_mediagallery/index/togglestatus');
    }

    public function getDeleteUrl(): string
    {
        return $this->getUrl('gardenlawn_mediagallery/index/delete');
    }

    /**
     * Get JSON configuration for the tile view script.
     *
     * @return string
     */
    public function getJsConfig(): string
    {
        $config = [
            'saveOrderUrl' => $this->getSaveOrderUrl(),
            'toggleStatusUrl' => $this->getToggleStatusUrl(),
            'deleteUrl' => $this->getDeleteUrl(),
            'formKey' => $this->getFormKey()
        ];
        return $this->jsonSerializer->serialize($config);
    }
}
