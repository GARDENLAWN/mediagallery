<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use GardenLawn\MediaGallery\Api\GalleryRepositoryInterface;
use Magento\Framework\Registry;

class Edit extends Action
{
    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @var GalleryRepositoryInterface
     */
    private $galleryRepository;

    /**
     * @var Registry
     */
    private $registry;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param GalleryRepositoryInterface $galleryRepository
     * @param Registry $registry
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        GalleryRepositoryInterface $galleryRepository,
        Registry $registry
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->galleryRepository = $galleryRepository;
        $this->registry = $registry;
        parent::__construct($context);
    }

    /**
     * @return \Magento\Backend\Model\View\Result\Page
     */
    public function execute()
    {
        $id = $this->getRequest()->getParam('id');
        $model = $this->galleryRepository->getById((int)$id);
        $this->registry->register('gardenlawn_mediagallery_gallery', $model);

        /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('GardenLawn_MediaGallery::items');
        $resultPage->getConfig()->getTitle()->prepend($model->getId() ? $model->getName() : __('New Gallery'));
        return $resultPage;
    }
}
