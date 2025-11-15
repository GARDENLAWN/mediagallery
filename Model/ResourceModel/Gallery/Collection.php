<?php
namespace GardenLawn\MediaGallery\Model\ResourceModel\Gallery;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'id';

    protected function _construct()
    {
        $this->_init(
            \GardenLawn\MediaGallery\Model\Gallery::class,
            \GardenLawn\MediaGallery\Model\ResourceModel\Gallery::class
        );
    }
}
