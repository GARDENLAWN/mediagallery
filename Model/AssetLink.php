<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;

class AssetLink extends AbstractModel
{
    /**
     * Initialize resource model
     *
     * @return void
     * @throws LocalizedException
     */
    protected function _construct(): void
    {
        $this->_init(ResourceModel\AssetLink::class);
    }
}
