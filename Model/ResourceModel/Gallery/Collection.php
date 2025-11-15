<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Model\ResourceModel\Gallery;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use GardenLawn\MediaGallery\Model\Gallery;
use GardenLawn\MediaGallery\Model\ResourceModel\Gallery as GalleryResource;

class Collection extends AbstractCollection
{
    /**
     * @inheritDoc
     */
    protected $_idFieldName = 'id';

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(Gallery::class, GalleryResource::class);
    }
}
