<?php
namespace GardenLawn\MediaGallery\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use GardenLawn\MediaGallery\Model\GalleryFactory;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;

class Save extends Action
{
    protected GalleryFactory $galleryFactory;
    protected DataPersistorInterface $dataPersistor;
    protected ResourceConnection $resourceConnection;

    public function __construct(
        Context $context,
        GalleryFactory $galleryFactory,
        DataPersistorInterface $dataPersistor,
        ResourceConnection $resourceConnection
    ) {
        $this->galleryFactory = $galleryFactory;
        $this->dataPersistor = $dataPersistor;
        $this->resourceConnection = $resourceConnection;
        parent::__construct($context);
    }

    public function execute(): ResultInterface|ResponseInterface|Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $data = $this->getRequest()->getPostValue();
        if ($data) {
            $id = $this->getRequest()->getParam('id');
            $model = $this->galleryFactory->create()->load($id);
            if (!$model->getId() && $id) {
                $this->messageManager->addErrorMessage(__('This gallery no longer exists.'));
                return $resultRedirect->setPath('*/*/');
            }

            $model->setData($data);

            try {
                $model->save();
                $this->saveImages($model->getId(), $data['images'] ?? []);
                $this->messageManager->addSuccessMessage(__('You saved the gallery.'));
                $this->dataPersistor->clear('mediagallery_gallery');

                if ($this->getRequest()->getParam('back')) {
                    return $resultRedirect->setPath('*/*/edit', ['id' => $model->getId()]);
                }
                return $resultRedirect->setPath('*/*/');
            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            } catch (\Exception $e) {
                $this->messageManager->addExceptionMessage($e, __('Something went wrong while saving the gallery.'));
            }

            $this->dataPersistor->set('mediagallery_gallery', $data);
            return $resultRedirect->setPath('*/*/edit', ['id' => $this->getRequest()->getParam('id')]);
        }
        return $resultRedirect->setPath('*/*/');
    }

    protected function saveImages(int $galleryId, array $images): void
    {
        $connection = $this->resourceConnection->getConnection();
        $linkTable = $connection->getTableName('gardenlawn_mediagallery_asset_link');
        $assetTable = $connection->getTableName('media_gallery_asset');

        $connection->delete($linkTable, ['gallery_id = ?' => $galleryId]);

        $imageLinks = [];
        foreach ($images as $image) {
            $select = $connection->select()->from($assetTable, 'id')->where('path = ?', $image['file']);
            $assetId = $connection->fetchOne($select);
            if ($assetId) {
                $imageLinks[] = [
                    'gallery_id' => $galleryId,
                    'asset_id' => $assetId,
                    'sort_order' => $image['position'],
                    'enabled' => (bool)($image['enabled'] ?? true) // Dodano 'enabled'
                ];
            }
        }

        if (!empty($imageLinks)) {
            $connection->insertMultiple($linkTable, $imageLinks);
        }
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('GardenLawn_MediaGallery::gallery_save');
    }
}
