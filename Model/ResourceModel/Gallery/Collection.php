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
}
