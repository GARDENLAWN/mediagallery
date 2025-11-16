<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Controller\Adminhtml\AssetLink;

use GardenLawn\MediaGallery\Api\AssetLinkRepositoryInterface;
use GardenLawn\MediaGallery\Api\Data\AssetLinkInterfaceFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'GardenLawn_MediaGallery::asset_link_save';

    /**
     * @var AssetLinkRepositoryInterface
     */
    private AssetLinkRepositoryInterface $assetLinkRepository;

    /**
     * @var AssetLinkInterfaceFactory
     */
    private AssetLinkInterfaceFactory $assetLinkFactory;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param Context $context
     * @param AssetLinkRepositoryInterface $assetLinkRepository
     * @param AssetLinkInterfaceFactory $assetLinkFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        AssetLinkRepositoryInterface $assetLinkRepository,
        AssetLinkInterfaceFactory $assetLinkFactory,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->assetLinkRepository = $assetLinkRepository;
        $this->assetLinkFactory = $assetLinkFactory;
        $this->logger = $logger;
    }

    /**
     * Save action
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $data = $this->getRequest()->getPostValue();
        $resultRedirect = $this->resultRedirectFactory->create();

        if ($data) {
            $id = (int)($data['id'] ?? 0);
            $galleryId = (int)($data['gallery_id'] ?? 0);

            try {
                if ($id) {
                    $assetLink = $this->assetLinkRepository->getById($id);
                } else {
                    $assetLink = $this->assetLinkFactory->create();
                }

                $assetLink->setGalleryId($galleryId);
                $assetLink->setAssetId((int)$data['asset_id']);
                $assetLink->setSortOrder((int)($data['sortorder'] ?? 0));
                $assetLink->setEnabled((bool)($data['enabled'] ?? false));

                $this->assetLinkRepository->save($assetLink);
                $this->messageManager->addSuccessMessage(__('You saved the asset link.'));
            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
                $this->logger->error($e->getMessage());
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage(__('Something went wrong while saving the asset link.'));
                $this->logger->critical($e);
            }
        }

        // Redirect back to the gallery edit page
        if (isset($galleryId) && $galleryId) {
            return $resultRedirect->setPath('*/*/edit', ['id' => $galleryId]);
        }
        return $resultRedirect->setPath('*/*/'); // Fallback to gallery listing
    }
}
