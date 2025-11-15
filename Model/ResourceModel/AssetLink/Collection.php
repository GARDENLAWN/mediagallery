<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Model\ResourceModel\AssetLink;

use GardenLawn\MediaGallery\Model\AssetLink;
use GardenLawn\MediaGallery\Model\ResourceModel\AssetLink as AssetLinkResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * @inheritDoc
     */
    protected $_idFieldName = 'asset_id'; // Using asset_id as the primary field for the collection

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(AssetLink::class, AssetLinkResource::class);
    }
}
