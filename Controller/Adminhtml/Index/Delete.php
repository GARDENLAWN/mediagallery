<?php
namespace GardenLawn\MediaGallery\Controller\Adminhtml\Index;

use GardenLawn\MediaGallery\Model\GalleryFactory;
use GardenLawn\MediaGallery\Helper\S3Helper;
use Magento\Backend\App\Action;
use Magento\Framework\Controller\Result\Redirect;

class Delete extends Action
{
    protected GalleryFactory $galleryFactory;
    protected S3Helper $s3Helper;

    public function __construct(
        Action\Context $context,
        GalleryFactory $galleryFactory,
        S3Helper $s3Helper
    ) {
        $this->galleryFactory = $galleryFactory;
        $this->s3Helper = $s3Helper;
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $id = $this->getRequest()->getParam('id');
        $resultRedirect = $this->resultRedirectFactory->create();
        if ($id) {
            try {
                $model = $this->galleryFactory->create();
                $model->load($id);
                $galleryName = $model->getName();

                $model->delete();

                $this->s3Helper->deleteFolder('wysiwyg/' . $galleryName);

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

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('GardenLawn_MediaGallery::gallery_delete');
    }
}
