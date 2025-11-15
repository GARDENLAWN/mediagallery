<?php
namespace GardenLawn\MediaGallery\Model;

use GardenLawn\MediaGallery\Model\ResourceModel\Gallery\CollectionFactory;
use Magento\Ui\DataProvider\AbstractDataProvider;
use GardenLawn\MediaGallery\Model\ResourceModel\Gallery\Collection;

class DataProvider extends AbstractDataProvider
{
    /**
     * @var Collection
     */
    protected $collection;

    /**
     * @var array
     */
    protected array $loadedData;

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

    public function getData(): array
    {
        if (isset($this->loadedData)) {
            return $this->loadedData;
        }

        $this->loadedData = [];
        $items = $this->collection->getItems();

        foreach ($items as $gallery) {
            $galleryData = $gallery->getData();

            // Load associated asset IDs
            $galleryData['links']['assets'] = $this->getLinkedAssets($gallery->getId());

            $this->loadedData[$gallery->getId()] = $galleryData;
        }

        return $this->loadedData;
    }

    private function getLinkedAssets(int $galleryId): array
    {
        $linkTable = $this->collection->getConnection()->getTableName('gardenlawn_mediagallery_asset_link');
        $select = $this->collection->getConnection()->select()
            ->from($linkTable, ['asset_id', 'sort_order'])
            ->where('gallery_id = ?', $galleryId);

        $assets = $this->collection->getConnection()->fetchAll($select);

        // The grid expects data in a specific format
        $result = [];
        foreach ($assets as $asset) {
            $result[] = [
                'id' => $asset['asset_id'],
                'sort_order' => $asset['sort_order']
            ];
        }
        return $result;
    }
}
