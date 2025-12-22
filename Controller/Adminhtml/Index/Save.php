<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Controller\Adminhtml\Index;

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use GardenLawn\MediaGallery\Api\GalleryRepositoryInterface;
use GardenLawn\MediaGallery\Model\GalleryFactory;
use GardenLawn\MediaGallery\Model\ResourceModel\Gallery\CollectionFactory as GalleryCollectionFactory;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Controller\ResultInterface;
use Psr\Log\LoggerInterface;

class Save extends Action
{
    private GalleryRepositoryInterface $galleryRepository;
    private GalleryFactory $galleryFactory;
    private LoggerInterface $logger;
    private GalleryCollectionFactory $galleryCollectionFactory;
    private PageFactory $resultPageFactory;

    public function __construct(
        Context $context,
        GalleryRepositoryInterface $galleryRepository,
        GalleryFactory $galleryFactory,
        LoggerInterface $logger,
        GalleryCollectionFactory $galleryCollectionFactory,
        PageFactory $resultPageFactory
    ) {
        $this->galleryRepository = $galleryRepository;
        $this->galleryFactory = $galleryFactory;
        $this->logger = $logger;
        $this->galleryCollectionFactory = $galleryCollectionFactory;
        $this->resultPageFactory = $resultPageFactory;
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $data = $this->getRequest()->getPostValue();
        $resultRedirect = $this->resultRedirectFactory->create();

        if (!$data) {
            return $resultRedirect->setPath('*/*/');
        }

        $id = $this->getRequest()->getParam('id');
        $path = $data['path'] ?? null;

        if ($path) {
            $collection = $this->galleryCollectionFactory->create();
            $collection->addFieldToFilter('path', $path);
            if ($collection->getSize() > 0) {
                $isDuplicate = false;
                if ($id) {
                    $existingGallery = $collection->getFirstItem();
                    if ($existingGallery->getId() != $id) {
                        $isDuplicate = true;
                    }
                } else {
                    $isDuplicate = true;
                }

                if ($isDuplicate) {
                    $this->messageManager->addErrorMessage(__('A gallery with the path "%1" already exists.', $path));
                    $this->_getSession()->setFormData($data);
                    if ($id) {
                        return $resultRedirect->setPath('*/*/edit', ['id' => $id]);
                    }
                    $encodedPath = base64_encode($path);
                    return $resultRedirect->setPath('*/*/new', ['path' => $encodedPath]);
                }
            }
        }

        try {
            if ($id) {
                $model = $this->galleryRepository->getById((int)$id);
            } else {
                unset($data['id']);
                $model = $this->galleryFactory->create();
            }

            $model->setData($data);
            $this->galleryRepository->save($model);

            $this->messageManager->addSuccessMessage(__('You saved the gallery.'));
            $this->logger->info('Gallery saved', ['gallery_id' => $model->getId()]);

            // Always redirect to edit page (Save acts as Save and Continue)
            return $resultRedirect->setPath('*/*/edit', ['id' => $model->getId(), '_current' => true]);

        } catch (Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->logger->error('Error saving gallery', ['exception' => $e]);
            $this->_getSession()->setFormData($data);
            return $resultRedirect->setPath('*/*/edit', ['id' => $this->getRequest()->getParam('id')]);
        }
    }
}
