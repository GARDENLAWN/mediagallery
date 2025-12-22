<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Model;

use GardenLawn\MediaGallery\Api\Data\AssetLinkInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;

class AssetLink extends AbstractModel implements AssetLinkInterface
{
    /**
     * Initialize resource model
     *
     * @return void
     * @throws LocalizedException
     */
    protected function _construct(): void
    {
        $this->_init(ResourceModel\AssetLink::class);
    }

    /**
     * Get gallery ID
     *
     * @return int
     */
    public function getId(): int
    {
        return (int)$this->getData(self::ID);
    }

    /**
     * Set gallery ID
     *
     * @param $id
     * @return AssetLinkInterface
     */
    public function setId($id): AssetLinkInterface
    {
        return $this->setData(self::ID, $id);
    }

    /**
     * Get gallery ID
     *
     * @return int
     */
    public function getGalleryId(): int
    {
        return (int)$this->getData(self::GALLERY_ID);
    }

    /**
     * Set gallery ID
     *
     * @param int $galleryId
     * @return AssetLinkInterface
     */
    public function setGalleryId(int $galleryId): AssetLinkInterface
    {
        return $this->setData(self::GALLERY_ID, $galleryId);
    }

    /**
     * Get asset ID
     *
     * @return int
     */
    public function getAssetId(): int
    {
        return (int)$this->getData(self::ASSET_ID);
    }

    /**
     * Set asset ID
     *
     * @param int $assetId
     * @return AssetLinkInterface
     */
    public function setAssetId(int $assetId): AssetLinkInterface
    {
        return $this->setData(self::ASSET_ID, $assetId);
    }

    /**
     * Get alt text
     *
     * @return string|null
     */
    public function getAlt(): ?string
    {
        return $this->getData(self::ALT);
    }

    /**
     * Set alt text
     *
     * @param string|null $alt
     * @return AssetLinkInterface
     */
    public function setAlt(?string $alt): AssetLinkInterface
    {
        return $this->setData(self::ALT, $alt);
    }

    /**
     * Get sort order
     *
     * @return int
     */
    public function getSortOrder(): int
    {
        return (int)$this->getData(self::SORT_ORDER);
    }

    /**
     * Set sort order
     *
     * @param int $sortOrder
     * @return AssetLinkInterface
     */
    public function setSortOrder(int $sortOrder): AssetLinkInterface
    {
        return $this->setData(self::SORT_ORDER, $sortOrder);
    }

    /**
     * Is enabled
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return (bool)$this->getData(self::ENABLED);
    }

    /**
     * Set enabled
     *
     * @param bool $enabled
     * @return AssetLinkInterface
     */
    public function setEnabled(bool $enabled): AssetLinkInterface
    {
        return $this->setData(self::ENABLED, $enabled);
    }
}
