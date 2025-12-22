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
    public const string ADMIN_RESOURCE = 'GardenLawn_MediaGallery::asset_link_save';

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

            // If ID is not in POST data, try to get it from request param
            if (!$id) {
                $id = (int)$this->getRequest()->getParam('id');
            }

            try {
                if ($id) {
                    $assetLink = $this->assetLinkRepository->getById($id);
                } else {
                    $assetLink = $this->assetLinkFactory->create();
                }

                // Only set gallery_id and asset_id if they are present in data (for new links)
                // For existing links, we might not want to change them if they are disabled in form
                if (isset($data['gallery_id'])) {
                    $assetLink->setGalleryId((int)$data['gallery_id']);
                }
                if (isset($data['asset_id'])) {
                    $assetLink->setAssetId((int)$data['asset_id']);
                }

                $assetLink->setSortOrder((int)($data['sort_order'] ?? 0));
                $assetLink->setEnabled((bool)($data['enabled'] ?? false));

                if (isset($data['alt'])) {
                    $assetLink->setAlt($data['alt']);
                }

                $this->assetLinkRepository->save($assetLink);
                $this->messageManager->addSuccessMessage(__('You saved the asset link.'));

                // Get gallery ID for redirection
                $galleryId = $assetLink->getGalleryId();

            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
                $this->logger->error($e->getMessage());
                // If error, try to redirect back to edit form
                if ($id) {
                     return $resultRedirect->setPath('*/*/edit', ['id' => $id]);
                }
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage(__('Something went wrong while saving the asset link.'));
                $this->logger->critical($e);
                 // If error, try to redirect back to edit form
                if ($id) {
                     return $resultRedirect->setPath('*/*/edit', ['id' => $id]);
                }
            }
        }

        // Redirect back to the gallery edit page
        if (isset($galleryId) && $galleryId) {
            return $resultRedirect->setPath('gardenlawn_mediagallery/index/edit', ['id' => $galleryId]);
        }
        return $resultRedirect->setPath('gardenlawn_mediagallery/index/index'); // Fallback to gallery listing
    }
}
