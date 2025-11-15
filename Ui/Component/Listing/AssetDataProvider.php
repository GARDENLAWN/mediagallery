<?php
namespace GardenLawn\MediaGallery\Ui\Component\Listing;

use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider as UiDataProvider;
use Magento\MediaGalleryApi\Api\SearchAssetsInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\RequestInterface;
use GardenLawn\MediaGallery\Model\GalleryFactory;

class AssetDataProvider extends UiDataProvider
{
    protected $searchAssets;
    protected $searchCriteriaBuilder;
    protected $request;
    protected $galleryFactory;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        SearchAssetsInterface $searchAssets,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        RequestInterface $request,
        GalleryFactory $galleryFactory,
        array $meta = [],
        array $data = []
    ) {
        $this->searchAssets = $searchAssets;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->request = $request;
        $this->galleryFactory = $galleryFactory;
        // The data provider requires a collection instance, but we will be using the service contract to fetch data.
        // We can create a dummy collection here to satisfy the parent constructor.
        $this->collection = new \Magento\Framework\Data\Collection\EntityFactory($this->request);
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    public function getData()
    {
        $galleryId = $this->request->getParam('current_id');
        if (!$galleryId) {
            return [
                'totalRecords' => 0,
                'items' => [],
            ];
        }

        $gallery = $this->galleryFactory->create()->load($galleryId);
        if (!$gallery->getId()) {
            return [
                'totalRecords' => 0,
                'items' => [],
            ];
        }

        $folderPath = 'wysiwyg/' . $gallery->getName();

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('folder', $folderPath)
            ->create();

        $assets = $this->searchAssets->execute($searchCriteria);

        $items = [];
        foreach ($assets->getItems() as $asset) {
            $items[] = [
                'id' => $asset->getId(),
                'path' => $asset->getPath(),
                'title' => $asset->getTitle(),
            ];
        }

        return [
            'totalRecords' => $assets->getTotalCount(),
            'items' => $items,
        ];
    }
}
