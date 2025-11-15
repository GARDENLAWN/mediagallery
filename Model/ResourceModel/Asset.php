<?php
namespace GardenLawn\MediaGallery\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Asset extends AbstractDb
{
    /**
     * Define main table
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init('media_gallery_asset', 'id');
    }
}
