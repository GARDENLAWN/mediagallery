<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use GardenLawn\MediaGallery\Api\GalleryRepositoryInterface;
use Magento\Framework\Controller\Result\Redirect;
use Psr\Log\LoggerInterface;

class Delete extends Action
{
    /**
     * @var GalleryRepositoryInterface
     */
    private GalleryRepositoryInterface $galleryRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param Context $context
     * @param GalleryRepositoryInterface $galleryRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        GalleryRepositoryInterface $galleryRepository,
        LoggerInterface $logger
    ) {
        $this->galleryRepository = $galleryRepository;
        $this->logger = $logger;
        parent::__construct($context);
    }

    /**
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $id = $this->getRequest()->getParam('id');
        $resultRedirect = $this->resultRedirectFactory->create();
        if ($id) {
            try {
                $this->galleryRepository->deleteById((int)$id);
                $this->messageManager->addSuccessMessage(__('The gallery has been deleted.'));
                $this->logger->info('Gallery deleted', ['gallery_id' => $id]);
                return $resultRedirect->setPath('*/*/');
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
                $this->logger->error('Error deleting gallery', ['gallery_id' => $id, 'exception' => $e]);
                return $resultRedirect->setPath('*/*/edit', ['id' => $id]);
            }
        }
        $this->messageManager->addErrorMessage(__('We can\'t find a gallery to delete.'));
        $this->logger->warning('Attempt to delete a gallery without an ID');
        return $resultRedirect->setPath('*/*/');
    }
}
