<?php
namespace GardenLawn\MediaGallery\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\View\Result\PageFactory;
use GardenLawn\MediaGallery\Model\GalleryFactory;
use Magento\Framework\Registry;

class Edit extends Action
{
    protected PageFactory $resultPageFactory;
    protected GalleryFactory $galleryFactory;
    protected Registry $registry;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        GalleryFactory $galleryFactory,
        Registry $registry
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->galleryFactory = $galleryFactory;
        $this->registry = $registry;
        parent::__construct($context);
    }

    public function execute()
    {
        $id = $this->getRequest()->getParam('id');
        $model = $this->galleryFactory->create();

        if ($id) {
            $model->load($id);
            if (!$model->getId()) {
                $this->messageManager->addErrorMessage(__('This gallery no longer exists.'));
                /** @var Redirect $resultRedirect */
                $resultRedirect = $this->resultRedirectFactory->create();
                return $resultRedirect->setPath('*/*/');
            }
        }

        $this->registry->register('current_gallery', $model);

        /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('GardenLawn_MediaGallery::items');
        $resultPage->getConfig()->getTitle()->prepend($model->getId() ? __('Edit Gallery "%1"', $model->getName()) : __('New Gallery'));

        return $resultPage;
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('GardenLawn_MediaGallery::gallery_save');
    }
}
