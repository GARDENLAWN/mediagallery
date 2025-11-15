<?php
namespace GardenLawn\MediaGallery\Ui\Component\Listing;

use Magento\Ui\DataProvider\AbstractDataProvider;
use Magento\Framework\App\RequestInterface;
use GardenLawn\MediaGallery\Model\GalleryFactory;
use Magento\MediaGalleryApi\Api\SearchAssetsInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use GardenLawn\MediaGallery\Model\ResourceModel\Asset\CollectionFactory;

class AssetDataProvider extends AbstractDataProvider
{
    protected RequestInterface $request;
    protected GalleryFactory $galleryFactory;
    protected SearchAssetsInterface $searchAssets;
    protected SearchCriteriaBuilder $searchCriteriaBuilder;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        RequestInterface $request,
        GalleryFactory $galleryFactory,
        SearchAssetsInterface $searchAssets,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collectionFactory->create();
        $this->request = $request;
        $this->galleryFactory = $galleryFactory;
        $this->searchAssets = $searchAssets;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    public function getData(): array
    {
        // This provider is used for the asset selection grid, which should show all available assets,
        // not just those in the current gallery's folder.
        // The filtering by folder was incorrect. The grid should allow searching all assets.
        // If you want to pre-filter by the gallery folder, that would be a different requirement.

        // We will add all request filters to the collection.
        foreach ($this->getAdditionalSearchCriteria() as $field => $value) {
            $this->getCollection()->addFieldToFilter($field, $value);
        }

        return $this->getCollection()->toArray();
    }

    private function getAdditionalSearchCriteria(): array
    {
        $filters = $this->request->getParam('filters', []);
        $searchCriteria = [];
        if (is_array($filters)) {
            foreach ($filters as $field => $value) {
                if (is_string($value) && !empty(trim($value))) {
                    $searchCriteria[$field] = ['like' => '%' . trim($value) . '%'];
                }
            }
        }
        return $searchCriteria;
    }
}
