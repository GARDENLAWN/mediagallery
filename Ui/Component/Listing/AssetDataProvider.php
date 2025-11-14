<?php
namespace GardenLawn\MediaGallery\Ui\Component\Listing;

use GardenLawn\MediaGallery\Model\ResourceModel\Asset\CollectionFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;

class AssetDataProvider extends AbstractDataProvider
{
    protected $collection;
    protected $request;

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

    public function getData()
    {
        $galleryId = $this->request->getParam('id');
        if ($galleryId) {
            $this->collection->addFieldToFilter('mediagallery_id', $galleryId);
        } else {
            // No gallery selected, return empty collection
            $this->collection->addFieldToFilter('mediagallery_id', -1);
        }
        return $this->collection->toArray();
    }
}
