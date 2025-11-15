<?php
namespace GardenLawn\MediaGallery\Ui\Component\Listing;

use Magento\Ui\DataProvider\AbstractDataProvider;
use GardenLawn\MediaGallery\Model\ResourceModel\Gallery\CollectionFactory;

class DataProvider extends AbstractDataProvider
{
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
}
