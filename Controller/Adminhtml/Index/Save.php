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
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Controller\ResultInterface;
use Psr\Log\LoggerInterface;

class Save extends Action
{
    private GalleryRepositoryInterface $galleryRepository;
    private GalleryFactory $galleryFactory;
    private LoggerInterface $logger;
    private GalleryCollectionFactory $galleryCollectionFactory;
    private RawFactory $resultRawFactory;

    public function __construct(
        Context $context,
        GalleryRepositoryInterface $galleryRepository,
        GalleryFactory $galleryFactory,
        LoggerInterface $logger,
        GalleryCollectionFactory $galleryCollectionFactory,
        RawFactory $resultRawFactory
    ) {
        $this->galleryRepository = $galleryRepository;
        $this->galleryFactory = $galleryFactory;
        $this->logger = $logger;
        $this->galleryCollectionFactory = $galleryCollectionFactory;
        $this->resultRawFactory = $resultRawFactory;
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

            // Instead of redirecting, return a script that reloads the tree and closes the window
            if (!$this->getRequest()->getParam('back')) {
                $resultRaw = $this->resultRawFactory->create();
                $resultRaw->setContents(
                    "<script>
                        window.parent.jQuery('body').trigger('gallery:tree:reload');
                        window.parent.jQuery('.modal-header .action-close').trigger('click');
                    </script>"
                );
                return $resultRaw;
            }

            return $resultRedirect->setPath('*/*/edit', ['id' => $model->getId(), '_current' => true]);

        } catch (Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->logger->error('Error saving gallery', ['exception' => $e]);
            $this->_getSession()->setFormData($data);
            return $resultRedirect->setPath('*/*/edit', ['id' => $this->getRequest()->getParam('id')]);
        }
    }
}
