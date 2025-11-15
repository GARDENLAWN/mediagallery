<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Model\ResourceModel\AssetLink;

use GardenLawn\MediaGallery\Model\ResourceModel\AssetLink as AssetLinkResource;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult as UiSearchResult;
use Psr\Log\LoggerInterface as Logger;
use Magento\Framework\Data\Collection\EntityFactoryInterface as EntityFactory;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface as FetchStrategy;
use Magento\Framework\Event\ManagerInterface as EventManager;

class Collection extends UiSearchResult
{
    /**
     * @var string
     */
    protected $_idFieldName = 'id';

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
            'gardenlawn_mediagallery_asset_link', // Main table name
            AssetLinkResource::class, // Resource Model class
            'id' // This is the primary field for the entity, but not necessarily for the collection's internal indexing
        );
    }
}
