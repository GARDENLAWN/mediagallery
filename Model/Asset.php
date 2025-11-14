<?php
namespace GardenLawn\MediaGallery\Model;

use Magento\Framework\Model\AbstractModel;

class Asset extends AbstractModel
{
    protected function _construct()
    {
        $this->_init(\GardenLawn\MediaGallery\Model\ResourceModel\Asset::class);
    }
}
