<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Ui\Component\Listing\AssetLink;

use GardenLawn\MediaGallery\Model\ResourceModel\AssetLink\CollectionFactory;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\ReportingInterface;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider as UiDataProvider;
use GardenLawn\MediaGallery\Model\ResourceModel\AssetLink\Collection;

class DataProvider extends UiDataProvider
{
    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var Collection // Explicitly declare the collection property
     */
    protected Collection $collection;

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
        // Utwórz kolekcję dla providera, aby _renderFiltersBefore mógł zastosować filtry
        $this->collection = $collectionFactory->create();
    }

    /**
     * Add filters to the collection.
     *
     * @return void
     */
    protected function _renderFiltersBefore(): void
    {
        // Filtruj po powiązanej galerii przekazywanej z formularza (imports/exports)
        // W trybie externalFilterMode wartość może przyjść jako filters[gallery_id]
        $galleryId = $this->request->getParam('gallery_id');
        if ($galleryId === null || $galleryId === '') {
            $filters = $this->request->getParam('filters');
            if (is_array($filters) && isset($filters['gallery_id'])) {
                $galleryId = $filters['gallery_id'];
            }
        }

        if ($galleryId !== null && $galleryId !== '') {
            // Zabezpieczenie typu – oczekujemy ID numeryczne
            if (is_numeric($galleryId)) {
                $galleryId = (int)$galleryId;
            }
            $this->collection->addFieldToFilter('gallery_id', $galleryId);
        } else {
            // Brak kontekstu galerii – nie ładuj żadnych rekordów (zabezpieczenie przed pełnym skanem tabeli)
            $this->collection->addFieldToFilter('gallery_id', ['in' => [-1]]);
        }
    }
}
