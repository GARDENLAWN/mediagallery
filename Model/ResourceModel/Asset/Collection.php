<?php
namespace GardenLawn\MediaGallery\Model\ResourceModel\Asset;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'id';

    protected function _construct()
    {
        $this->_init(
            \GardenLawn\MediaGallery\Model\Asset::class,
            \GardenLawn\MediaGallery\Model\ResourceModel\Asset::class
        );
    }
}
