<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Controller\Adminhtml\AssetLink;

use GardenLawn\MediaGallery\Api\AssetLinkRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

class Delete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'GardenLawn_MediaGallery::asset_link_delete';

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
     * @param AssetLinkRepositoryInterface $assetLinkRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        AssetLinkRepositoryInterface $assetLinkRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->assetLinkRepository = $assetLinkRepository;
        $this->logger = $logger;
    }

    /**
     * Delete action
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $id = (int)$this->getRequest()->getParam('id');
        $galleryId = (int)$this->getRequest()->getParam('gallery_id'); // Get gallery_id for redirection
        $resultRedirect = $this->resultRedirectFactory->create();

        if ($id) {
            try {
                $this->assetLinkRepository->deleteById($id);
                $this->messageManager->addSuccessMessage(__('You deleted the asset link.'));
            } catch (NoSuchEntityException $e) {
                $this->messageManager->addErrorMessage(__('This asset link no longer exists.'));
                $this->logger->error($e->getMessage());
            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
                $this->logger->error($e->getMessage());
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage(__('Something went wrong while deleting the asset link.'));
                $this->logger->critical($e);
            }
        } else {
            $this->messageManager->addErrorMessage(__('We can\'t find an asset link to delete.'));
        }

        // Redirect back to the gallery edit page
        if ($galleryId) {
            return $resultRedirect->setPath('*/*/edit', ['id' => $galleryId]);
        }
        return $resultRedirect->setPath('*/*/'); // Fallback to gallery listing
    }
}
