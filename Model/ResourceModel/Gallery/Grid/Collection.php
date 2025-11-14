<?php
namespace GardenLawn\MediaGallery\Model\ResourceModel\Gallery\Grid;

use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;

class Collection extends SearchResult
{
    protected function _initSelect()
    {
        parent::_initSelect();

        $this->getSelect()->joinLeft(
            ['mga' => $this->getTable('media_gallery_asset')],
            'main_table.id = mga.mediagallery_id',
            ['asset_count' => 'COUNT(mga.id)']
        )->group('main_table.id');

        return $this;
    }
}
