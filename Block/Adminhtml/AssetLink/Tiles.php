<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Block\Adminhtml\AssetLink;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use GardenLawn\MediaGallery\Model\ResourceModel\AssetLink\CollectionFactory;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Framework\Serialize\Serializer\Json;

class Tiles extends Template
{
    protected CollectionFactory $assetLinkCollectionFactory;
    protected Registry $registry;
    private Json $jsonSerializer;

    public function __construct(
        Context $context,
        CollectionFactory $assetLinkCollectionFactory,
        Registry $registry,
        Json $jsonSerializer,
        array $data = []
    ) {
        $this->assetLinkCollectionFactory = $assetLinkCollectionFactory;
        $this->registry = $registry;
        $this->jsonSerializer = $jsonSerializer;
        parent::__construct($context, $data);
    }

    public function getCurrentGalleryId(): ?int
    {
        $gallery = $this->registry->registry('gardenlawn_mediagallery_gallery');
        return $gallery ? (int)$gallery->getId() : null;
    }

    public function getAssetLinksData(): array
    {
        $galleryId = $this->getCurrentGalleryId();
        if (!$galleryId) {
            return [];
        }

        $collection = $this->assetLinkCollectionFactory->create();
        $collection->addFieldToFilter('main_table.gallery_id', $galleryId)
            ->join(
                ['mga' => $collection->getTable('media_gallery_asset')],
                'main_table.asset_id = mga.id',
                ['path', 'title']
            )
            ->setOrder('main_table.sort_order', 'ASC');

        $assetsData = [];
        $mediaUrl = $this->_storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);

        foreach ($collection as $assetLink) {
            $assetsData[] = [
                'id' => $assetLink->getId(),
                'asset_id' => $assetLink->getAssetId(),
                'title' => $assetLink->getData('title'),
                'enabled' => (bool)$assetLink->getEnabled(),
                'sort_order' => $assetLink->getSortOrder(),
                'thumbnail' => $assetLink->getData('path') ? $mediaUrl . $assetLink->getData('path') : '',
                'edit_url' => '#' // Define edit URL for asset link later
            ];
        }

        return $assetsData;
    }

    public function getJsConfig(): string
    {
        // Configuration for assetLinkTileComponent.js
        return $this->jsonSerializer->serialize([
            // Add any needed URLs here, e.g., for delete, toggle status
        ]);
    }

    public function getAddNewAssetLinkUrl(int $galleryId): string
    {
        // This can be a new controller to select existing assets, for now, it's a placeholder
        return '#';
    }
}
