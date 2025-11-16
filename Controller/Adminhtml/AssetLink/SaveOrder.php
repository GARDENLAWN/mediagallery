<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Controller\Adminhtml\AssetLink;

use GardenLawn\MediaGallery\Api\AssetLinkRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class SaveOrder extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'GardenLawn_MediaGallery::asset_link_save';

    /**
     * @var JsonFactory
     */
    private JsonFactory $resultJsonFactory;

    /**
     * @var AssetLinkRepositoryInterface
     */
    private AssetLinkRepositoryInterface $assetLinkRepository;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param AssetLinkRepositoryInterface $assetLinkRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        AssetLinkRepositoryInterface $assetLinkRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->assetLinkRepository = $assetLinkRepository;
        $this->logger = $logger;
    }

    /**
     * Save order for asset links.
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute(): \Magento\Framework\Controller\Result\Json
    {
        $result = $this->resultJsonFactory->create();
        $orderData = $this->getRequest()->getParam('order'); // Array of {id: ..., sort_order: ...}
        $galleryId = (int)$this->getRequest()->getParam('gallery_id');

        if (!is_array($orderData) || empty($orderData) || !$galleryId) {
            return $result->setData(['error' => true, 'message' => __('Invalid data provided.')]);
        }

        try {
            foreach ($orderData as $item) {
                $assetLinkId = (int)$item['id'];
                $sortOrder = (int)$item['sort_order'];

                $assetLink = $this->assetLinkRepository->getById($assetLinkId);
                // Ensure the asset link belongs to the current gallery
                if ((int)$assetLink->getGalleryId() !== $galleryId) {
                    continue; // Skip if it doesn't belong to this gallery
                }
                $assetLink->setSortOrder($sortOrder);
                $this->assetLinkRepository->save($assetLink);
            }
            return $result->setData(['success' => true, 'message' => __('Asset link order saved.')]);
        } catch (LocalizedException $e) {
            $this->logger->error($e->getMessage());
            return $result->setData(['error' => true, 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            $this->logger->critical($e);
            return $result->setData(['error' => true, 'message' => __('Something went wrong while saving the order.')]);
        }
    }
}
