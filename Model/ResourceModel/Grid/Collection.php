<?php

namespace GardenLawn\MediaGallery\Model\ResourceModel\Grid;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'id';

    /**
     * Define resource model.
     */
    protected function _construct(): void
    {
        $this->_init('GardenLawn\MediaGallery\Model\Grid', 'GardenLawn\MediaGallery\Model\ResourceModel\Grid');
    }
}
