<?php
namespace GardenLawn\MediaGallery\Model\ResourceModel\Gallery\Grid;

use Magento\Framework\Data\Collection\Db\FetchStrategyInterface as FetchStrategy;
use Magento\Framework\Data\Collection\EntityFactoryInterface as EntityFactory;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;
use Psr\Log\LoggerInterface as Logger;

class Collection extends SearchResult
{
    /**
     * @param EntityFactory $entityFactory
     * @param Logger $logger
     * @param FetchStrategy $fetchStrategy
     * @param EventManager $eventManager
     * @param string $mainTable
     * @param string $resourceModel
     * @throws LocalizedException
     */
    public function __construct(
        EntityFactory $entityFactory,
        Logger $logger,
        FetchStrategy $fetchStrategy,
        EventManager $eventManager,
        $mainTable,
        $resourceModel
    ) {
        parent::__construct($entityFactory, $logger, $fetchStrategy, $eventManager, $mainTable, $resourceModel);
    }

    protected function _initSelect()
    {
        parent::_initSelect();

        // Zmieniono join, aby używał nowej tabeli łączącej gardenlawn_mediagallery_asset_link
        $this->getSelect()->joinLeft(
            ['gmal' => $this->getTable('gardenlawn_mediagallery_asset_link')],
            'main_table.id = gmal.gallery_id',
            ['asset_count' => 'COUNT(gmal.asset_id)'] // Zliczamy asset_id z tabeli łączącej
        )->group('main_table.id');

        return $this;
    }
}
