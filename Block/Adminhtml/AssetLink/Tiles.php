<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Block\Adminhtml\AssetLink;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use GardenLawn\MediaGallery\Model\ResourceModel\AssetLink\CollectionFactory;
use Magento\Framework\UrlInterface;
use Magento\Framework\Serialize\Serializer\Json;

class Tiles extends Template
{
    protected CollectionFactory $assetLinkCollectionFactory;
    private Json $jsonSerializer;

    public function __construct(
        Context $context,
        CollectionFactory $assetLinkCollectionFactory,
        Json $jsonSerializer,
        array $data = []
    ) {
        $this->assetLinkCollectionFactory = $assetLinkCollectionFactory;
        $this->jsonSerializer = $jsonSerializer;
        parent::__construct($context, $data);
    }

    /**
     * Get the current gallery ID directly from the request.
     * This is the most reliable method in this context.
     */
    public function getCurrentGalleryId(): ?int
    {
        $id = $this->getRequest()->getParam('id');
        return $id ? (int)$id : null;
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
        return $this->jsonSerializer->serialize([
            // Add any needed URLs here
        ]);
    }

    public function getAddNewAssetLinkUrl(int $galleryId): string
    {
        return '#';
    }
}
