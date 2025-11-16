<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Block\Adminhtml\AssetLink\Edit;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Exception\NoSuchEntityException;
use GardenLawn\MediaGallery\Api\AssetLinkRepositoryInterface;
use Magento\Framework\Registry;

class GenericButton
{
    /**
     * @var Context
     */
    protected Context $context;

    /**
     * @var AssetLinkRepositoryInterface
     */
    protected AssetLinkRepositoryInterface $assetLinkRepository;

    /**
     * @var Registry
     */
    protected Registry $registry;

    /**
     * @param Context $context
     * @param AssetLinkRepositoryInterface $assetLinkRepository
     * @param Registry $registry
     */
    public function __construct(
        Context $context,
        AssetLinkRepositoryInterface $assetLinkRepository,
        Registry $registry
    ) {
        $this->context = $context;
        $this->assetLinkRepository = $assetLinkRepository;
        $this->registry = $registry;
    }

    /**
     * Return AssetLink ID
     *
     * @return int|null
     */
    public function getAssetLinkId(): ?int
    {
        try {
            $assetLink = $this->registry->registry('gardenlawn_mediagallery_asset_link');
            return $assetLink->getId();
        } catch (NoSuchEntityException $e) {
            // Do nothing
        }
        return null;
    }

    /**
     * Generate url by route and parameters
     *
     * @param string $route
     * @param array $params
     * @return string
     */
    public function getUrl(string $route = '', array $params = []): string
    {
        return $this->context->getUrlBuilder()->getUrl($route, $params);
    }
}
