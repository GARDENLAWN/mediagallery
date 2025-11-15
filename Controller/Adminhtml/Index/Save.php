<?php
namespace GardenLawn\MediaGallery\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use GardenLawn\MediaGallery\Model\GalleryFactory;
use GardenLawn\MediaGallery\Helper\S3Helper;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class Save extends Action
{
    protected GalleryFactory $galleryFactory;
    protected DataPersistorInterface $dataPersistor;
    protected ResourceConnection $resourceConnection;
    protected LoggerInterface $logger;
    protected S3Helper $s3Helper;

    public function __construct(
        Context $context,
        GalleryFactory $galleryFactory,
        DataPersistorInterface $dataPersistor,
        ResourceConnection $resourceConnection,
        LoggerInterface $logger,
        S3Helper $s3Helper
    ) {
        $this->galleryFactory = $galleryFactory;
        $this->dataPersistor = $dataPersistor;
        $this->resourceConnection = $resourceConnection;
        $this->logger = $logger;
        $this->s3Helper = $s3Helper;
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $data = $this->getRequest()->getPostValue();
        if ($data) {
            $id = $this->getRequest()->getParam('id');
            $model = $this->galleryFactory->create()->load($id);
            $originalName = $model->getName();

            if (!$model->getId() && $id) {
                $this->messageManager->addErrorMessage(__('This gallery no longer exists.'));
                return $resultRedirect->setPath('*/*/');
            }

            $model->setData($data);

            try {
                // S3 operations
                if (!$id) { // New gallery
                    $this->s3Helper->createFolder('wysiwyg/' . $model->getName());
                } elseif ($originalName !== $model->getName()) { // Rename gallery
                    $this->s3Helper->renameFolder('wysiwyg/' . $originalName, 'wysiwyg/' . $model->getName());
                }

                $model->save();

                // Handle linked assets
                $linkedAssets = $this->getRequest()->getPost('links');
                if (isset($linkedAssets['assets']) && is_array($linkedAssets['assets'])) {
                    $this->saveAssetLinks($model->getId(), $linkedAssets['assets']);
                }

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

    protected function saveAssetLinks(int $galleryId, array $assets): void
    {
        $connection = $this->resourceConnection->getConnection();
        $linkTable = $connection->getTableName('gardenlawn_mediagallery_asset_link');

        $connection->beginTransaction();
        try {
            $connection->delete($linkTable, ['gallery_id = ?' => $galleryId]);
            $linksToInsert = [];
            foreach ($assets as $asset) {
                if (isset($asset['id'])) {
                    $linksToInsert[] = [
                        'gallery_id' => $galleryId,
                        'asset_id'   => (int)$asset['id'],
                        'sort_order' => (int)($asset['sort_order'] ?? 0),
                        'enabled'    => 1,
                    ];
                }
            }
            if (!empty($linksToInsert)) {
                $connection->insertMultiple($linkTable, $linksToInsert);
            }
            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollBack();
            $this->logger->critical('Error saving asset links for gallery ID ' . $galleryId . ': ' . $e->getMessage());
            throw new LocalizedException(__('Could not save asset links.'));
        }
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('GardenLawn_MediaGallery::gallery_save');
    }
}
