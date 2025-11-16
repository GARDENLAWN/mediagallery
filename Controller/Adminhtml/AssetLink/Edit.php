<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Controller\Adminhtml\AssetLink;

use GardenLawn\MediaGallery\Api\AssetLinkRepositoryInterface;
use GardenLawn\MediaGallery\Api\Data\AssetLinkInterfaceFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Edit extends Action
{
    public const ADMIN_RESOURCE = 'GardenLawn_MediaGallery::asset_link_save';

    /**
     * @var PageFactory
     */
    protected PageFactory $resultPageFactory;

    /**
     * @var AssetLinkRepositoryInterface
     */
    private AssetLinkRepositoryInterface $assetLinkRepository;

    /**
     * @var AssetLinkInterfaceFactory
     */
    private AssetLinkInterfaceFactory $assetLinkFactory;

    /**
     * @var Registry
     */
    private Registry $registry;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param AssetLinkRepositoryInterface $assetLinkRepository
     * @param AssetLinkInterfaceFactory $assetLinkFactory
     * @param Registry $registry
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        AssetLinkRepositoryInterface $assetLinkRepository,
        AssetLinkInterfaceFactory $assetLinkFactory,
        Registry $registry
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->assetLinkRepository = $assetLinkRepository;
        $this->assetLinkFactory = $assetLinkFactory;
        $this->registry = $registry;
        parent::__construct($context);
    }

    /**
     * Edit AssetLink action
     *
     * @return Page
     */
    public function execute(): Page
    {
        $id = (int)$this->getRequest()->getParam('id');
        $galleryId = (int)$this->getRequest()->getParam('gallery_id');
        $assetLink = $this->assetLinkFactory->create();

        if ($id) {
            try {
                $assetLink = $this->assetLinkRepository->getById($id);
            } catch (NoSuchEntityException $e) {
                $this->messageManager->addErrorMessage(__('This asset link no longer exists.'));
                /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
                $resultRedirect = $this->resultRedirectFactory->create();
                return $resultRedirect->setPath('*/*/'); // Redirect to gallery listing
            }
        } elseif ($galleryId) {
            $assetLink->setGalleryId($galleryId);
        }

        $this->registry->register('gardenlawn_mediagallery_asset_link', $assetLink);

        /** @var Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('GardenLawn_MediaGallery::items');
        $resultPage->getConfig()->getTitle()->prepend(
            $assetLink->getId() ? __('Edit Asset Link %1', $assetLink->getId()) : __('New Asset Link')
        );

        return $resultPage;
    }
}
