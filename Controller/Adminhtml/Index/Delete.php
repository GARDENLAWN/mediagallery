<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use GardenLawn\MediaGallery\Api\GalleryRepositoryInterface;
use Magento\Framework\Controller\ResultInterface;

class Delete extends Action
{
    protected JsonFactory $resultJsonFactory;
    protected GalleryRepositoryInterface $galleryRepository;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        GalleryRepositoryInterface $galleryRepository
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->galleryRepository = $galleryRepository;
    }

    public function execute(): ResultInterface
    {
        $result = $this->resultJsonFactory->create();
        $galleryId = (int)$this->getRequest()->getParam('id');

        if (!$this->getRequest()->isPost() || !$galleryId) {
            return $result->setData(['error' => true, 'message' => __('Invalid request.')]);
        }

        try {
            $this->galleryRepository->deleteById($galleryId);
            $this->messageManager->addSuccessMessage(__('Gallery has been deleted.'));

            // The JS component will listen for this event
            return $result->setData(['error' => false, 'message' => __('Gallery has been deleted.'), 'reloadTree' => true]);
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('An error occurred while deleting the gallery: %1', $e->getMessage()));
            return $result->setData(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('GardenLawn_MediaGallery::gallery_delete');
    }
}
