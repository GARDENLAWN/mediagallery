<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Ui\Component\Listing\AssetLink;

use GardenLawn\MediaGallery\Model\ResourceModel\AssetLink\CollectionFactory;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\ReportingInterface;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider as UiDataProvider;

class DataProvider extends UiDataProvider
{
    /**
     * @var RequestInterface
     */
    protected $request; // Removed type declaration here

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param ReportingInterface $reporting
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param RequestInterface $request
     * @param FilterBuilder $filterBuilder
     * @param CollectionFactory $collectionFactory
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        ReportingInterface $reporting,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        RequestInterface $request,
        FilterBuilder $filterBuilder,
        CollectionFactory $collectionFactory,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct(
            $name,
            $primaryFieldName,
            $requestFieldName,
            $reporting,
            $searchCriteriaBuilder,
            $request,
            $filterBuilder,
            $meta,
            $data
        );
        $this->request = $request;
        $this->collection = $collectionFactory->create();
    }

    /**
     * Add filters to the collection.
     *
     * @return void
     */
    protected function _renderFiltersBefore(): void
    {
        $galleryId = $this->request->getParam('id');
        if ($galleryId) {
            $this->collection->addFieldToFilter('gallery_id', $galleryId);
        }
        parent::_renderFiltersBefore();
    }
}
