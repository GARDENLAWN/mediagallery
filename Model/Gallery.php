<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Model;

use Magento\Framework\Model\AbstractModel;

class Gallery extends AbstractModel
{
    /**
     * Cache tag
     */
    public const CACHE_TAG = 'gardenlawn_mediagallery';

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(\GardenLawn\MediaGallery\Model\ResourceModel\Gallery::class);
    }

    /**
     * Get identities
     *
     * @return array
     */
    public function getIdentities(): array
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }
}
