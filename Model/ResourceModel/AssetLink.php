<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class AssetLink extends AbstractDb
{
    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init('gardenlawn_mediagallery_asset_link', 'id');
    }
}
