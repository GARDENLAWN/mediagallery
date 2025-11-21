<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Ui\Component\DataProvider;

use Magento\Ui\DataProvider\AbstractDataProvider;
use GardenLawn\MediaGallery\Model\ResourceModel\AssetLink\CollectionFactory;
use GardenLawn\MediaGallery\Model\ResourceModel\AssetLink\Collection;

class AssetLink extends AbstractDataProvider
{
    /**
     * @var Collection
     */
    protected $collection;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    /**
     * Get data
     *
     * This method is overridden to add a JOIN to the collection before the parent
     * applies filters and sorting. This is the robust way to handle this.
     *
     * @return array
     */
    public function getData(): array
    {
        // Defensively check if the join has already been added to prevent errors.
        $select = $this->getCollection()->getSelect();
        $from = $select->getPart(\Zend_Db_Select::FROM);

        if (!isset($from['mga'])) {
            $this->getCollection()->join(
                ['mga' => $this->getCollection()->getTable('media_gallery_asset')],
                'main_table.asset_id = mga.id',
                ['path', 'title']
            );
        }

        // Let the parent class handle the filtering, sorting, and data retrieval.
        // It will correctly apply the 'gallery_id' filter from the SearchCriteria.
        return parent::getData();
    }
}
