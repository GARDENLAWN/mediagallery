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

/**
 * Grid collection that is compatible with Magento UI DataProvider
 * by implementing SearchResultInterface via UiSearchResult base class.
 */
class Collection extends UiSearchResult
{
    /**
     * Collection for UI grid. Provide required constructor args to UiSearchResult
     * to ensure main table is set and prevent empty table name SQL errors.
     * @throws LocalizedException
     */
    public function __construct(
        EntityFactory $entityFactory,
        Logger $logger,
        FetchStrategy $fetchStrategy,
        EventManager $eventManager
    ) {
        // Pass main table, resource model and identifier to parent constructor
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

    /**
     * Initialize select object
     *
     * @return void
     */
    protected function _initSelect(): void
    {
        parent::_initSelect();
        $this->getSelect()->joinLeft(
            ['asset_link' => $this->getTable('gardenlawn_mediagallery_asset_link')],
            'main_table.id = asset_link.gallery_id',
            []
        )->columns(
            [
                'asset_count' => new Zend_Db_Expr('COUNT(asset_link.asset_id)')
            ]
        )->group('main_table.id');

        // Map 'id' to 'main_table.id' to resolve ambiguity in WHERE clauses
        $this->addFilterToMap('id', 'main_table.id');
    }
}
