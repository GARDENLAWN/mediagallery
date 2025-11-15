<?php
namespace GardenLawn\MediaGallery\Model\ResourceModel\Asset;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'id';

    /**
     * Define resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(
            \Magento\MediaGalleryApi\Api\Data\AssetInterface::class,
            \GardenLawn\MediaGallery\Model\ResourceModel\Asset::class
        );
    }
}
