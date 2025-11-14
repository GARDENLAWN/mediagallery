<?php
namespace GardenLawn\MediaGallery\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Gallery extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('gardenlawn_mediagallery', 'id');
    }
}
