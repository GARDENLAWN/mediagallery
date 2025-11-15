<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Ui\Component\Listing;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\ReportingInterface;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider as UiDataProvider;
use GardenLawn\MediaGallery\Model\ResourceModel\Gallery\CollectionFactory;
use GardenLawn\MediaGallery\Model\ResourceModel\Gallery\Collection;

class DataProvider extends UiDataProvider
{
    /**
     * @var Collection
     */
    protected Collection $collection;

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param ReportingInterface $reporting ,
     * @param SearchCriteriaBuilder $searchCriteriaBuilder ,
     * @param RequestInterface $request ,
     * @param FilterBuilder $filterBuilder ,
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
    )
    {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $reporting, $searchCriteriaBuilder, $request, $filterBuilder, $meta, $data);
        $this->collection = $collectionFactory->create();
    }
}
