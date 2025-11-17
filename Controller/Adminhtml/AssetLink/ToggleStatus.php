<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Controller\Adminhtml\AssetLink;

use GardenLawn\MediaGallery\Api\AssetLinkRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

class ToggleStatus extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'GardenLawn_MediaGallery::asset_link_save';

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
     * Toggle status for an asset link.
     *
     * @return Json
     */
    public function execute(): Json
    {
        $result = $this->resultJsonFactory->create();
        $assetLinkId = (int)$this->getRequest()->getParam('id');
        $status = (int)$this->getRequest()->getParam('status');
        $galleryId = (int)$this->getRequest()->getParam('gallery_id');

        if (!$assetLinkId || !in_array($status, [0, 1], true) || !$galleryId) {
            return $result->setData(['error' => true, 'message' => __('Invalid data provided.')]);
        }

        try {
            $assetLink = $this->assetLinkRepository->getById($assetLinkId);
            // Ensure the asset link belongs to the current gallery
            if ($assetLink->getGalleryId() !== $galleryId) {
                throw new LocalizedException(__('The asset link does not belong to this gallery.'));
            }
            $assetLink->setEnabled((bool)$status);
            $this->assetLinkRepository->save($assetLink);
            return $result->setData(['success' => true, 'message' => __('Asset link status updated.')]);
        } catch (NoSuchEntityException $e) {
            $this->logger->error($e->getMessage());
            return $result->setData(['error' => true, 'message' => __('Asset link not found.')]);
        } catch (LocalizedException $e) {
            $this->logger->error($e->getMessage());
            return $result->setData(['error' => true, 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            $this->logger->critical($e);
            return $result->setData(['error' => true, 'message' => __('Something went wrong while updating the status.')]);
        }
    }
}
