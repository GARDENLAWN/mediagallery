<?php
namespace GardenLawn\MediaGallery\Ui\Component\Listing;

use GardenLawn\MediaGallery\Model\ResourceModel\Asset\CollectionFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;

class AssetDataProvider extends AbstractDataProvider
{
    protected RequestInterface $request;
    protected $loadedData;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        RequestInterface $request,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        $this->request = $request;
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    public function getData(): array
    {
        if (isset($this->loadedData)) {
            return $this->loadedData;
        }

        $galleryId = $this->request->getParam('id');
        $this->collection->clear(); // Clear previous filters and orders

        if ($galleryId) {
            $this->collection->addFieldToFilter('mediagallery_id', $galleryId);
        } else {
            // No gallery selected, effectively return empty collection
            $this->collection->addFieldToFilter('mediagallery_id', -1);
        }

        $this->loadedData = $this->collection->toArray();
        return $this->loadedData;
    }
}
