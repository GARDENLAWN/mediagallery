<?php
namespace GardenLawn\MediaGallery\Model\ResourceModel\Asset;

use GardenLawn\MediaGallery\Model\ResourceModel\Asset;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\MediaGalleryApi\Api\Data\AssetInterface;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'id';

    /**
     * Define resource model
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(
            AssetInterface::class,
            Asset::class
        );
    }
}
