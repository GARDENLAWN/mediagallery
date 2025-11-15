<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use GardenLawn\MediaGallery\Api\GalleryRepositoryInterface;
use Magento\Framework\Controller\Result\Redirect;

class Delete extends Action
{
    /**
     * @var GalleryRepositoryInterface
     */
    private GalleryRepositoryInterface $galleryRepository;

    /**
     * @param Context $context
     * @param GalleryRepositoryInterface $galleryRepository
     */
    public function __construct(
        Context $context,
        GalleryRepositoryInterface $galleryRepository
    ) {
        $this->galleryRepository = $galleryRepository;
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
                return $resultRedirect->setPath('*/*/');
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
                return $resultRedirect->setPath('*/*/edit', ['id' => $id]);
            }
        }
        $this->messageManager->addErrorMessage(__('We can\'t find a gallery to delete.'));
        return $resultRedirect->setPath('*/*/');
    }
}
