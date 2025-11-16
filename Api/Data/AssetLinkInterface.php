<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Api\Data;

interface AssetLinkInterface
{
    public const ID = 'id';
    public const GALLERY_ID = 'gallery_id';
    public const ASSET_ID = 'asset_id';
    public const SORT_ORDER = 'sort_order';
    public const ENABLED = 'enabled';

    /**
     * Get ID
     *
     * @return int|null
     */
    public function getId();

    /**
     * Set ID
     *
     * @param int $id
     * @return AssetLinkInterface
     */
    public function setId($id): AssetLinkInterface;

    /**
     * Get gallery ID
     *
     * @return int
     */
    public function getGalleryId(): int;

    /**
     * Set gallery ID
     *
     * @param int $galleryId
     * @return AssetLinkInterface
     */
    public function setGalleryId(int $galleryId): AssetLinkInterface;

    /**
     * Get asset ID
     *
     * @return int
     */
    public function getAssetId(): int;

    /**
     * Set asset ID
     *
     * @param int $assetId
     * @return AssetLinkInterface
     */
    public function setAssetId(int $assetId): AssetLinkInterface;

    /**
     * Get sort order
     *
     * @return int
     */
    public function getSortOrder(): int;

    /**
     * Set sort order
     *
     * @param int $sortOrder
     * @return AssetLinkInterface
     */
    public function setSortOrder(int $sortOrder): AssetLinkInterface;

    /**
     * Is enabled
     *
     * @return bool
     */
    public function isEnabled(): bool;

    /**
     * Set enabled
     *
     * @param bool $enabled
     * @return AssetLinkInterface
     */
    public function setEnabled(bool $enabled): AssetLinkInterface;
}
