<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Model\ResourceModel\Gallery;

use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult as UiSearchResult;
use GardenLawn\MediaGallery\Model\Gallery;
use GardenLawn\MediaGallery\Model\ResourceModel\Gallery as GalleryResource;

/**
 * Grid collection that is compatible with Magento UI DataProvider
 * by implementing SearchResultInterface via UiSearchResult base class.
 */
class Collection extends UiSearchResult
{
    /**
     * @inheritDoc
     */
    protected $_idFieldName = 'id';

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        // Initialize model and resource; UiSearchResult will derive main table from the resource
        $this->_init(Gallery::class, GalleryResource::class);
    }
}
