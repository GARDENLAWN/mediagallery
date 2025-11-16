<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use GardenLawn\MediaGallery\Api\GalleryRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;

class ToggleStatus extends Action
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

    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $galleryId = (int)$this->getRequest()->getParam('id');
        $status = $this->getRequest()->getParam('status');

        if (!$this->getRequest()->isPost() || !$galleryId || $status === null) {
            return $result->setData(['error' => true, 'message' => __('Invalid request.')]);
        }

        try {
            $gallery = $this->galleryRepository->getById($galleryId);
            $gallery->setEnabled((bool)$status);
            $this->galleryRepository->save($gallery);
            return $result->setData(['error' => false, 'message' => __('Status has been updated.')]);
        } catch (LocalizedException $e) {
            return $result->setData(['error' => true, 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            return $result->setData(['error' => true, 'message' => __('An error occurred while updating the status.')]);
        }
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('GardenLawn_MediaGallery::gallery_save');
    }
}
