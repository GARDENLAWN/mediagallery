<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Model\AssetLink;

use GardenLawn\MediaGallery\Model\ResourceModel\AssetLink\CollectionFactory;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;

class DataProvider extends AbstractDataProvider
{
    /**
     * @var CollectionFactory
     */
    protected $collection;

    /**
     * @var DataPersistorInterface
     */
    private DataPersistorInterface $dataPersistor;

    /**
     * @var array
     */
    private array $loadedData;

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param DataPersistorInterface $dataPersistor
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        DataPersistorInterface $dataPersistor,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        $this->dataPersistor = $dataPersistor;
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    /**
     * Get data
     *
     * @return array
     */
    public function getData(): array
    {
        if (isset($this->loadedData)) {
            return $this->loadedData;
        }
        $items = $this->collection->getItems();
        foreach ($items as $assetLink) {
            $this->loadedData[$assetLink->getId()] = $assetLink->getData();
        }

        $data = $this->dataPersistor->get('gardenlawn_mediagallery_asset_link');
        if (!empty($data)) {
            $assetLink = $this->collection->getNewEmptyItem();
            $assetLink->setData($data);
            $this->loadedData[$assetLink->getId()] = $assetLink->getData();
            $this->dataPersistor->clear('gardenlawn_mediagallery_asset_link');
        }

        return $this->loadedData;
    }
}
