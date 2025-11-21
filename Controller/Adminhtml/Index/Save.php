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
use Psr\Log\LoggerInterface;

class Save extends Action
{
    private GalleryRepositoryInterface $galleryRepository;
    private GalleryFactory $galleryFactory;
    private LoggerInterface $logger;
    private GalleryCollectionFactory $galleryCollectionFactory;

    public function __construct(
        Context $context,
        GalleryRepositoryInterface $galleryRepository,
        GalleryFactory $galleryFactory,
        LoggerInterface $logger,
        GalleryCollectionFactory $galleryCollectionFactory
    ) {
        $this->galleryRepository = $galleryRepository;
        $this->galleryFactory = $galleryFactory;
        $this->logger = $logger;
        $this->galleryCollectionFactory = $galleryCollectionFactory;
        parent::__construct($context);
    }

    /**
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $data = $this->getRequest()->getPostValue();
        $resultRedirect = $this->resultRedirectFactory->create();

        if (!$data) {
            return $resultRedirect->setPath('*/*/');
        }

        $id = $this->getRequest()->getParam('id');
        $path = $data['path'] ?? null;

        // --- Validation Logic using Collection ---
        if ($path) {
            $collection = $this->galleryCollectionFactory->create();
            $collection->addFieldToFilter('path', $path);

            if ($collection->getSize() > 0) {
                $isDuplicate = false;
                if ($id) { // Editing an existing gallery
                    // It's a duplicate if a gallery with this path exists AND it has a different ID.
                    $existingGallery = $collection->getFirstItem();
                    if ($existingGallery->getId() != $id) {
                        $isDuplicate = true;
                    }
                } else { // Creating a new gallery
                    $isDuplicate = true;
                }

                if ($isDuplicate) {
                    $this->messageManager->addErrorMessage(__('A gallery with the path "%1" already exists.', $path));
                    $this->_getSession()->setFormData($data); // Keep entered data
                    if ($id) {
                        return $resultRedirect->setPath('*/*/edit', ['id' => $id]);
                    }
                    // For a new gallery, redirect back to the 'new' action with the path pre-filled
                    $encodedPath = base64_encode($path);
                    return $resultRedirect->setPath('*/*/new', ['path' => $encodedPath]);
                }
            }
        }
        // --- End Validation Logic ---

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

            if ($this->getRequest()->getParam('back')) {
                return $resultRedirect->setPath('*/*/edit', ['id' => $model->getId(), '_current' => true]);
            }
            return $resultRedirect->setPath('*/*/');

        } catch (Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->logger->error('Error saving gallery', ['exception' => $e]);
            $this->_getSession()->setFormData($data);
            return $resultRedirect->setPath('*/*/edit', ['id' => $this->getRequest()->getParam('id')]);
        }
    }
}
