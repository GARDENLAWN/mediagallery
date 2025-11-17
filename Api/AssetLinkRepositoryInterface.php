<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Api;

use GardenLawn\MediaGallery\Api\Data\AssetLinkInterface;
use GardenLawn\MediaGallery\Api\Data\AssetLinkSearchResultsInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

interface AssetLinkRepositoryInterface
{
    /**
     * Save AssetLink.
     *
     * @param AssetLinkInterface $assetLink
     * @return AssetLinkInterface
     * @throws LocalizedException
     */
    public function save(AssetLinkInterface $assetLink): AssetLinkInterface;

    /**
     * Retrieve AssetLink.
     *
     * @param int $assetLinkId
     * @return AssetLinkInterface
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getById(int $assetLinkId): AssetLinkInterface;

    /**
     * Retrieve AssetLink matching the specified criteria.
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return AssetLinkSearchResultsInterface
     * @throws LocalizedException
     */
    public function getList(SearchCriteriaInterface $searchCriteria): AssetLinkSearchResultsInterface;

    /**
     * Delete AssetLink.
     *
     * @param AssetLinkInterface $assetLink
     * @return bool true on success
     * @throws LocalizedException
     */
    public function delete(AssetLinkInterface $assetLink): bool;

    /**
     * Delete AssetLink by ID.
     *
     * @param int $assetLinkId
     * @return bool true on success
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function deleteById(int $assetLinkId): bool;
}
