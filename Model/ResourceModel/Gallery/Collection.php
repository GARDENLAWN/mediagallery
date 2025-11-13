<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Model\ResourceModel\Gallery;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * Define resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(
            \GardenLawn\MediaGallery\Model\Gallery::class,
            \GardenLawn\MediaGallery\Model\ResourceModel\Gallery::class
        );
    }
}
