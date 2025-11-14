<?php
namespace GardenLawn\MediaGallery\Model\ResourceModel\Asset;

use GardenLawn\MediaGallery\Model\Asset;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'id';

    protected function _construct(): void
    {
        $this->_init(
            Asset::class,
            \GardenLawn\MediaGallery\Model\ResourceModel\Asset::class
        );
    }
}
