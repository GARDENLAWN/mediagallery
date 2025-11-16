<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Model\ResourceModel\Gallery;

use GardenLawn\MediaGallery\Model\ResourceModel\Gallery;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult as UiSearchResult;
use Psr\Log\LoggerInterface as Logger;
use Magento\Framework\Data\Collection\EntityFactoryInterface as EntityFactory;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface as FetchStrategy;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Zend_Db_Expr;

class Collection extends UiSearchResult
{
    private bool $assetCountJoined = false;

    public function __construct(
        EntityFactory $entityFactory,
        Logger $logger,
        FetchStrategy $fetchStrategy,
        EventManager $eventManager
    ) {
        parent::__construct(
            $entityFactory,
            $logger,
            $fetchStrategy,
            $eventManager,
            'gardenlawn_mediagallery',
            Gallery::class,
            'id'
        );
    }

    protected function _initSelect(): void
    {
        parent::_initSelect();
        $this->addFilterToMap('id', 'main_table.id');
        $this->addFilterToMap('enabled', 'main_table.enabled');
    }

    /**
     * Joins the asset link table to count associated assets for each gallery.
     *
     * @return $this
     */
    public function joinAssetCount(): self
    {
        if (!$this->assetCountJoined) {
            $this->getSelect()->joinLeft(
                ['asset_link' => $this->getTable('gardenlawn_mediagallery_asset_link')],
                'main_table.id = asset_link.gallery_id',
                []
            )->columns(
                ['asset_count' => new Zend_Db_Expr('COUNT(DISTINCT asset_link.asset_id)')]
            )->group('main_table.id');

            $this->assetCountJoined = true;
        }
        return $this;
    }

    /**
     * Overridden to apply the join before loading the collection.
     * This is for compatibility with the UI grid.
     */
    protected function _beforeLoad()
    {
        // This logic is for the UI grid, which uses this collection directly.
        // For our custom tile view, we call joinAssetCount() manually.
        if (!$this->assetCountJoined) {
             $this->joinAssetCount();
        }
        return parent::_beforeLoad();
    }
}
