<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Controller\Adminhtml\Index;

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use GardenLawn\MediaGallery\Api\GalleryRepositoryInterface;
use GardenLawn\MediaGallery\Model\GalleryFactory;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

class Save extends Action
{
    /**
     * @var GalleryRepositoryInterface
     */
    private GalleryRepositoryInterface $galleryRepository;

    /**
     * @var GalleryFactory
     */
    private GalleryFactory $galleryFactory;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param Context $context
     * @param GalleryRepositoryInterface $galleryRepository
     * @param GalleryFactory $galleryFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        GalleryRepositoryInterface $galleryRepository,
        GalleryFactory $galleryFactory,
        LoggerInterface $logger
    ) {
        $this->galleryRepository = $galleryRepository;
        $this->galleryFactory = $galleryFactory;
        $this->logger = $logger;
        parent::__construct($context);
    }

    /**
     * @return Redirect
     * @throws NoSuchEntityException
     */
    public function execute(): Redirect
    {
        $data = $this->getRequest()->getPostValue();
        $resultRedirect = $this->resultRedirectFactory->create();
        if ($data) {
            $id = $this->getRequest()->getParam('id');
            if ($id) {
                $model = $this->galleryRepository->getById((int)$id);
            } else {
                unset($data['id']);
                $model = $this->galleryFactory->create();
            }

            $model->setData($data);

            try {
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
                return $resultRedirect->setPath('*/*/edit', ['id' => $this->getRequest()->getParam('id')]);
            }
        }
        return $resultRedirect->setPath('*/*/');
    }
}
