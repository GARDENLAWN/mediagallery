<?php
namespace GardenLawn\MediaGallery\Model;

use Magento\Framework\Model\AbstractModel;

class Gallery extends AbstractModel
{
    protected function _construct()
    {
        $this->_init(\GardenLawn\MediaGallery\Model\ResourceModel\Gallery::class);
    }
}
