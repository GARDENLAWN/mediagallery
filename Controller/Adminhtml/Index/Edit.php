<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\PageFactory;
use GardenLawn\MediaGallery\Api\GalleryRepositoryInterface;
use GardenLawn\MediaGallery\Model\GalleryFactory;
use Magento\Framework\Registry;

class Edit extends Action
{
    protected PageFactory $resultPageFactory;
    private GalleryRepositoryInterface $galleryRepository;
    private GalleryFactory $galleryFactory;
    private Registry $registry;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        GalleryRepositoryInterface $galleryRepository,
        GalleryFactory $galleryFactory,
        Registry $registry
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->galleryRepository = $galleryRepository;
        $this->galleryFactory = $galleryFactory;
        $this->registry = $registry;
        parent::__construct($context);
    }

    /**
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute(): \Magento\Framework\View\Result\Page
    {
        $id = $this->getRequest()->getParam('id');

        if ($id) {
            try {
                $model = $this->galleryRepository->getById((int)$id);
            } catch (NoSuchEntityException) {
                $this->messageManager->addErrorMessage(__('This gallery no longer exists.'));
                $this->_redirect('*/*/');
                return $this->resultPageFactory->create();
            }
        } else {
            $model = $this->galleryFactory->create();
        }

        $this->registry->register('gardenlawn_mediagallery_gallery', $model);

        /** @var Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('GardenLawn_MediaGallery::items');
        $title = $model->getId() ? __('Edit Gallery "%1"', $model->getPath()) : __('New Gallery');
        $resultPage->getConfig()->getTitle()->prepend($title);

        return $resultPage;
    }
}
