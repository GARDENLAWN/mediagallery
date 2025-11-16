<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

interface AssetLinkSearchResultsInterface extends SearchResultsInterface
{
    /**
     * Get asset links list.
     *
     * @return \GardenLawn\MediaGallery\Api\Data\AssetLinkInterface[]
     */
    public function getItems(): array;

    /**
     * Set asset links list.
     *
     * @param \GardenLawn\MediaGallery\Api\Data\AssetLinkInterface[] $items
     * @return $this
     */
    public function setItems(array $items): self;
}
