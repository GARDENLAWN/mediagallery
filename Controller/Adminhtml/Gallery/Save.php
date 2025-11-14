<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Controller\Adminhtml\Gallery;

use GardenLawn\MediaGallery\Model\Gallery;
use GardenLawn\MediaGallery\Model\GalleryFactory;
use GardenLawn\MediaGallery\Model\ResourceModel\Gallery as GalleryResource;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;

class Save extends Action
{
    public const ADMIN_RESOURCE = 'GardenLawn_MediaGallery::gallery_save';

    /**
     * @var DataPersistorInterface
     */
    protected DataPersistorInterface $dataPersistor;

    /**
     * @var GalleryFactory
     */
    protected GalleryFactory $galleryFactory;

    /**
     * @var GalleryResource
     */
    protected GalleryResource $galleryResource;

    /**
     * @param Context $context
     * @param DataPersistorInterface $dataPersistor
     * @param GalleryFactory $galleryFactory
     * @param GalleryResource $galleryResource
     */
    public function __construct(
        Context $context,
        DataPersistorInterface $dataPersistor,
        GalleryFactory $galleryFactory,
        GalleryResource $galleryResource
    ) {
        $this->dataPersistor = $dataPersistor;
        $this->galleryFactory = $galleryFactory;
        $this->galleryResource = $galleryResource;
        parent::__construct($context);
    }

    /**
     * Save action
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $data = $this->getRequest()->getPostValue();

        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        if ($data) {
            $id = (int)($this->getRequest()->getParam('id') ?? 0);

            /** @var Gallery $model */
            $model = $this->galleryFactory->create();

            if ($id) {
                $this->galleryResource->load($model, $id);
                if (!$model->getId()) {
                    $this->messageManager->addErrorMessage(__('This gallery no longer exists.'));
                    return $resultRedirect->setPath('*/*/');
                }
            }

            $model->setData($data);

            try {
                $this->galleryResource->save($model);
                $this->messageManager->addSuccessMessage(__('You saved the gallery.'));
                $this->dataPersistor->clear('gardenlawn_mediagallery_gallery');

                if ($this->getRequest()->getParam('back')) {
                    return $resultRedirect->setPath('*/*/edit', ['id' => $model->getId()]);
                }
                return $resultRedirect->setPath('*/*/');
            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            } catch (\Exception $e) {
                $this->messageManager->addExceptionMessage($e, __('Something went wrong while saving the gallery.'));
            }

            $this->dataPersistor->set('gardenlawn_mediagallery_gallery', $data);
            return $resultRedirect->setPath('*/*/edit', ['id' => $this->getRequest()->getParam('id')]);
        }
        return $resultRedirect->setPath('*/*/');
    }
}
