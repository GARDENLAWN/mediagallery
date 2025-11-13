<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Controller\Adminhtml\Gallery;

use GardenLawn\MediaGallery\Model\Gallery;
use GardenLawn\MediaGallery\Model\GalleryFactory;
use GardenLawn\MediaGallery\Model\ResourceModel\Gallery as GalleryResource;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\PageFactory;

class Edit extends Action
{
    public const ADMIN_RESOURCE = 'GardenLawn_MediaGallery::gallery_list';

    /**
     * @var PageFactory
     */
    protected PageFactory $resultPageFactory;

    /**
     * @var GalleryFactory
     */
    protected GalleryFactory $galleryFactory;

    /**
     * @var GalleryResource
     */
    protected GalleryResource $galleryResource;

    /**
     * @var Registry
     */
    protected Registry $registry;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param GalleryFactory $galleryFactory
     * @param GalleryResource $galleryResource
     * @param Registry $registry
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        GalleryFactory $galleryFactory,
        GalleryResource $galleryResource,
        Registry $registry
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->galleryFactory = $galleryFactory;
        $this->galleryResource = $galleryResource;
        $this->registry = $registry;
    }

    /**
     * Edit action
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $id = (int)$this->getRequest()->getParam('id');
        /** @var Gallery $model */
        $model = $this->galleryFactory->create();

        if ($id) {
            $this->galleryResource->load($model, $id);
            if (!$model->getId()) {
                $this->messageManager->addErrorMessage(__('This gallery no longer exists.'));
                /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
                $resultRedirect = $this->resultRedirectFactory->create();
                return $resultRedirect->setPath('*/*/');
            }
        }

        $this->registry->register('gardenlawn_mediagallery_gallery', $model);

        /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('GardenLawn_MediaGallery::mediagallery');
        $resultPage->addBreadcrumb(__('Media Gallery'), __('Media Gallery'));
        $resultPage->addBreadcrumb(__('Manage Galleries'), __('Manage Galleries'));
        $resultPage->getConfig()->getTitle()->prepend($model->getId() ? __('Edit Gallery "%1"', $model->getName()) : __('New Gallery'));

        return $resultPage;
    }
}
