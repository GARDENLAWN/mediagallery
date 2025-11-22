<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Model;

use GardenLawn\MediaGallery\Model\ResourceModel\Gallery\CollectionFactory;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Magento\Framework\Registry;
use Magento\Framework\App\RequestInterface;

class DataProvider extends AbstractDataProvider
{
    protected array $loadedData = [];
    protected $collection;
    private Registry $registry;
    private RequestInterface $request;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        Registry $registry,
        RequestInterface $request,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        $this->registry = $registry;
        $this->request = $request;
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

        // Get the ID from the request (e.g., from the URL parameter 'id')
        $id = $this->request->getParam($this->getRequestFieldName());

        if ($id) {
            // Filter the collection to load only the gallery being edited
            $this->collection->addFieldToFilter($this->primaryFieldName, $id);
        } else {
            // This block handles the case where no ID is in the request,
            // typically for a new entity.
            // Check for a pre-populated model from the registry (e.g., from a controller)
            $model = $this->registry->registry('gardenlawn_mediagallery_gallery');
            if ($model && !$model->getId()) {
                // If it's a new model from registry, return its data with a dummy key.
                // UI forms often expect an array keyed by ID, so 0 is a common placeholder for new entities.
                $this->loadedData[0] = $model->getData();
                return $this->loadedData;
            }
        }

        // Load items from the (potentially filtered) collection
        $items = $this->collection->getItems();
        /** @var Gallery $gallery */
        foreach ($items as $gallery) {
            $this->loadedData[$gallery->getId()] = $gallery->getData();
        }

        return $this->loadedData;
    }
}
