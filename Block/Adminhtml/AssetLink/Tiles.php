<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Block\Adminhtml\AssetLink;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use GardenLawn\MediaGallery\Model\ResourceModel\AssetLink\Collection as AssetLinkCollection;
use GardenLawn\MediaGallery\Model\ResourceModel\AssetLink\CollectionFactory as AssetLinkCollectionFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Registry;

class Tiles extends Template
{
    protected AssetLinkCollectionFactory $assetLinkCollectionFactory;
    protected StoreManagerInterface $storeManager;
    private Json $jsonSerializer;
    private Registry $registry;

    public function __construct(
        Context $context,
        AssetLinkCollectionFactory $assetLinkCollectionFactory,
        StoreManagerInterface $storeManager,
        Json $jsonSerializer,
        Registry $registry,
        array $data = []
    ) {
        $this->assetLinkCollectionFactory = $assetLinkCollectionFactory;
        $this->storeManager = $storeManager;
        $this->jsonSerializer = $jsonSerializer;
        $this->registry = $registry;
        parent::__construct($context, $data);
    }

    /**
     * Get current gallery ID from registry.
     *
     * @return int|null
     */
    public function getCurrentGalleryId(): ?int
    {
        $gallery = $this->registry->registry('gardenlawn_mediagallery_gallery');
        return $gallery ? (int)$gallery->getId() : null;
    }

    /**
     * Get all asset links for the current gallery with extended data for the tile view.
     *
     * @return array
     * @throws NoSuchEntityException
     */
    public function getAssetLinksData(): array
    {
        $galleryId = $this->getCurrentGalleryId();
        if (!$galleryId) {
            return [];
        }

        /** @var AssetLinkCollection $assetLinks */
        $assetLinks = $this->assetLinkCollectionFactory->create();
        $assetLinks->addFieldToFilter('gallery_id', $galleryId)
            ->setOrder('sort_order', 'ASC');

        $assetLinksData = [];

        foreach ($assetLinks as $assetLink) {
            $path = $assetLink->getData('path'); // Path is joined in AssetLinkCollection
            $assetLinksData[] = [
                'id' => (int)$assetLink->getId(),
                'gallery_id' => (int)$assetLink->getGalleryId(),
                'asset_id' => (int)$assetLink->getAssetId(),
                'title' => $assetLink->getData('title') ?: $assetLink->getData('path'), // Use path as fallback
                'enabled' => (bool)$assetLink->getEnabled(),
                'sort_order' => (int)$assetLink->getSortOrder(),
                'thumbnail' => $path ? $this->getMediaUrl() . $path : $this->getPlaceholderImage(),
                'edit_url' => $this->getEditUrl((int)$assetLink->getId(), (int)$assetLink->getGalleryId()),
                'delete_url' => $this->getDeleteUrl((int)$assetLink->getId(), (int)$assetLink->getGalleryId())
            ];
        }

        return $assetLinksData;
    }

    public function getMediaUrl(): string
    {
        return $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
    }

    public function getPlaceholderImage(): string
    {
        // Use a generic placeholder or a specific one for asset links
        return $this->getViewFileUrl('Magento_Catalog/images/product/placeholder/thumbnail.jpg');
    }

    public function getEditUrl(int $assetLinkId, int $galleryId): string
    {
        return $this->getUrl('gardenlawn_mediagallery_assetlink/assetlink/edit', ['id' => $assetLinkId, 'gallery_id' => $galleryId]);
    }

    public function getDeleteUrl(int $assetLinkId, int $galleryId): string
    {
        return $this->getUrl('gardenlawn_mediagallery_assetlink/assetlink/delete', ['id' => $assetLinkId, 'gallery_id' => $galleryId]);
    }

    public function getAddNewAssetLinkUrl(int $galleryId): string
    {
        return $this->getUrl('gardenlawn_mediagallery_assetlink/assetlink/edit', ['gallery_id' => $galleryId]);
    }

    public function getSaveOrderUrl(): string
    {
        // This URL will be used if we implement drag-and-drop sorting for asset links
        return $this->getUrl('gardenlawn_mediagallery_assetlink/assetlink/saveorder'); // Need to create this controller action
    }

    public function getToggleStatusUrl(): string
    {
        // This URL will be used if we implement toggle status for asset links
        return $this->getUrl('gardenlawn_mediagallery_assetlink/assetlink/togglestatus'); // Need to create this controller action
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
            'deleteUrl' => $this->getDeleteUrl(0, 0), // Placeholder, actual ID will be passed from JS
            'formKey' => $this->getFormKey(),
            'currentGalleryId' => $this->getCurrentGalleryId()
        ];
        return $this->jsonSerializer->serialize($config);
    }
}
