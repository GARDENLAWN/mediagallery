<?php
namespace GardenLawn\MediaGallery\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;

class Gallery extends AbstractModel
{
    /**
     * @throws LocalizedException
     */
    protected function _construct(): void
    {
        $this->_init(ResourceModel\Gallery::class);
    }
}
