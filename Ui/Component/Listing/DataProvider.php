<?php
namespace GardenLawn\MediaGallery\Ui\Component\Listing;

use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider as UiDataProvider;
use GardenLawn\MediaGallery\Model\ResourceModel\Gallery\CollectionFactory;

class DataProvider extends UiDataProvider
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
