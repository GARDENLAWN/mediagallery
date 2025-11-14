<?php
namespace GardenLawn\MediaGallery\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;

class Delete extends Action
{
    protected $galleryFactory;

    public function __construct(
        Action\Context $context,
        \GardenLawn\MediaGallery\Model\GalleryFactory $galleryFactory
    ) {
        $this->galleryFactory = $galleryFactory;
        parent::__construct($context);
    }

    public function execute()
    {
        $id = $this->getRequest()->getParam('id');
        $resultRedirect = $this->resultRedirectFactory->create();
        if ($id) {
            try {
                $model = $this->galleryFactory->create();
                $model->load($id);
                $model->delete();
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

    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('GardenLawn_MediaGallery::gallery_delete');
    }
}
