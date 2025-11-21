<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Model;

use GardenLawn\MediaGallery\Model\ResourceModel\Gallery\CollectionFactory;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Magento\Framework\Registry;

class DataProvider extends AbstractDataProvider
{
    protected array $loadedData = [];
    protected $collection;
    private Registry $registry;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        Registry $registry,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        $this->registry = $registry;
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    /**
     * Get data
     *
     * @return array
     */
    public function getData(): array
    {
        if (!empty($this->loadedData)) {
            return $this->loadedData;
        }

        // Check for a pre-populated model for new entities
        $model = $this->registry->registry('gardenlawn_mediagallery_gallery');
        if ($model && !$model->getId()) {
            $this->loadedData[$model->getId()] = $model->getData();
            return $this->loadedData;
        }

        // Standard logic for existing entities
        $items = $this->collection->getItems();
        /** @var Gallery $gallery */
        foreach ($items as $gallery) {
            $this->loadedData[$gallery->getId()] = $gallery->getData();
        }

        return $this->loadedData;
    }
}
