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
use Psr\Log\LoggerInterface;

class Tiles extends Template
{
    protected AssetLinkCollectionFactory $assetLinkCollectionFactory;
    protected StoreManagerInterface $storeManager;
    private Json $jsonSerializer;
    private Registry $registry; // Keep registry for other potential uses, but not for gallery ID here
    private LoggerInterface $logger;

    public function __construct(
        Context $context,
        AssetLinkCollectionFactory $assetLinkCollectionFactory,
        StoreManagerInterface $storeManager,
        Json $jsonSerializer,
        Registry $registry,
        LoggerInterface $logger,
        array $data = []
    ) {
        $this->assetLinkCollectionFactory = $assetLinkCollectionFactory;
        $this->storeManager = $storeManager;
        $this->jsonSerializer = $jsonSerializer;
        $this->registry = $registry;
        $this->logger = $logger;
        parent::__construct($context, $data);
    }

    /**
     * Get current gallery ID from the request.
     *
     * @return int|null
     */
    public function getCurrentGalleryId(): ?int
    {
        $galleryId = (int)$this->getRequest()->getParam('id'); // Get 'id' from the request parameters
        $this->logger->info('AssetLink Tiles Block: Retrieved gallery ID from request: ' . ($galleryId ?? 'null'));
        return $galleryId ?: null; // Return null if ID is 0
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
            $this->logger->warning('AssetLink Tiles Block: No gallery ID found, returning empty data.');
            return [];
        }

        $assetLinks = $this->assetLinkCollectionFactory->create();
        $assetLinks->addFieldToFilter('gallery_id', $galleryId)
            ->setOrder('sort_order', 'ASC');

        $this->logger->info('AssetLink Tiles Block: Collection SQL: ' . $assetLinks->getSelect()->__toString());
        $this->logger->info('AssetLink Tiles Block: Collection size for gallery ' . $galleryId . ': ' . $assetLinks->getSize());

        $assetLinksData = [];

        foreach ($assetLinks as $assetLink) {
            $path = $assetLink->getData('path');
            $assetLinksData[] = [
                'id' => (int)$assetLink->getId(),
                'gallery_id' => (int)$assetLink->getGalleryId(),
                'asset_id' => (int)$assetLink->getAssetId(),
                'title' => $assetLink->getData('title') ?: $assetLink->getData('path'),
                'enabled' => (bool)$assetLink->getEnabled(),
                'sort_order' => (int)$assetLink->getSortOrder(),
                'thumbnail' => $path ? $this->getMediaUrl() . $path : $this->getPlaceholderImage(),
                'edit_url' => $this->getEditUrl((int)$assetLink->getId(), (int)$assetLink->getGalleryId()),
                'delete_url' => $this->getDeleteUrl((int)$assetLink->getId(), (int)$assetLink->getGalleryId())
            ];
        }

        $this->logger->info('AssetLink Tiles Block: Prepared ' . count($assetLinksData) . ' asset links for rendering.');

        return $assetLinksData;
    }

    public function getMediaUrl(): string
    {
        try {
            return $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
        } catch (\Exception $e) {
            $this->logger->error('AssetLink Tiles Block: Error getting media URL: ' . $e->getMessage());
            return '';
        }
    }

    public function getPlaceholderImage(): string
    {
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
        return $this->getUrl('gardenlawn_mediagallery_assetlink/assetlink/saveorder');
    }

    public function getToggleStatusUrl(): string
    {
        return $this->getUrl('gardenlawn_mediagallery_assetlink/assetlink/togglestatus');
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
            'deleteUrl' => $this->getDeleteUrl(0, 0),
            'formKey' => $this->getFormKey(),
            'currentGalleryId' => $this->getCurrentGalleryId()
        ];
        $this->logger->info('AssetLink Tiles Block: Generated JS config: ' . $this->jsonSerializer->serialize($config));
        return $this->jsonSerializer->serialize($config);
    }
}
