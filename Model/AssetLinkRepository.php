<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Model;

use GardenLawn\MediaGallery\Api\AssetLinkRepositoryInterface;
use GardenLawn\MediaGallery\Api\Data\AssetLinkInterface;
use GardenLawn\MediaGallery\Api\Data\AssetLinkInterfaceFactory;
use GardenLawn\MediaGallery\Api\Data\AssetLinkSearchResultsInterface;
use GardenLawn\MediaGallery\Api\Data\AssetLinkSearchResultsInterfaceFactory;
use GardenLawn\MediaGallery\Model\ResourceModel\AssetLink as AssetLinkResource;
use GardenLawn\MediaGallery\Model\ResourceModel\AssetLink\CollectionFactory as AssetLinkCollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

class AssetLinkRepository implements AssetLinkRepositoryInterface
{
    /**
     * @var AssetLinkResource
     */
    private AssetLinkResource $resource;

    /**
     * @var AssetLinkInterfaceFactory
     */
    private AssetLinkInterfaceFactory $assetLinkFactory;

    /**
     * @var AssetLinkCollectionFactory
     */
    private AssetLinkCollectionFactory $assetLinkCollectionFactory;

    /**
     * @var AssetLinkSearchResultsInterfaceFactory
     */
    private AssetLinkSearchResultsInterfaceFactory $searchResultsFactory;

    /**
     * @var CollectionProcessorInterface
     */
    private CollectionProcessorInterface $collectionProcessor;

    /**
     * @param AssetLinkResource $resource
     * @param AssetLinkInterfaceFactory $assetLinkFactory
     * @param AssetLinkCollectionFactory $assetLinkCollectionFactory
     * @param AssetLinkSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(
        AssetLinkResource $resource,
        AssetLinkInterfaceFactory $assetLinkFactory,
        AssetLinkCollectionFactory $assetLinkCollectionFactory,
        AssetLinkSearchResultsInterfaceFactory $searchResultsFactory,
        CollectionProcessorInterface $collectionProcessor
    ) {
        $this->resource = $resource;
        $this->assetLinkFactory = $assetLinkFactory;
        $this->assetLinkCollectionFactory = $assetLinkCollectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->collectionProcessor = $collectionProcessor;
    }

    /**
     * @inheritDoc
     */
    public function save(AssetLinkInterface $assetLink): AssetLinkInterface
    {
        try {
            $this->resource->save($assetLink);
        } catch (\Exception $exception) {
            throw new LocalizedException(
                __('Could not save the asset link: %1', $exception->getMessage()),
                $exception
            );
        }
        return $assetLink;
    }

    /**
     * @inheritDoc
     */
    public function getById(int $assetLinkId): AssetLinkInterface
    {
        $assetLink = $this->assetLinkFactory->create();
        $this->resource->load($assetLink, $assetLinkId);
        if (!$assetLink->getId()) {
            throw new NoSuchEntityException(__('Asset Link with id "%1" does not exist.', $assetLinkId));
        }
        return $assetLink;
    }

    /**
     * @inheritDoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): AssetLinkSearchResultsInterface
    {
        $collection = $this->assetLinkCollectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());
        return $searchResults;
    }

    /**
     * @inheritDoc
     */
    public function delete(AssetLinkInterface $assetLink): bool
    {
        try {
            $this->resource->delete($assetLink);
        } catch (\Exception $exception) {
            throw new LocalizedException(
                __('Could not delete the asset link: %1', $exception->getMessage()),
                $exception
            );
        }
        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteById(int $assetLinkId): bool
    {
        return $this->delete($this->getById($assetLinkId));
    }
}
