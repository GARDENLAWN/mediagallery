<?php
namespace GardenLawn\MediaGallery\Model\ResourceModel\Gallery;

use GardenLawn\MediaGallery\Model\Gallery;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'id';

    protected function _construct(): void
    {
        $this->_init(
            Gallery::class,
            \GardenLawn\MediaGallery\Model\ResourceModel\Gallery::class
        );
    }
}
