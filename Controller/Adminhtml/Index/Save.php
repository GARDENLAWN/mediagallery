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
use Psr\Log\LoggerInterface;

class Save extends Action
{
    protected GalleryFactory $galleryFactory;
    protected DataPersistorInterface $dataPersistor;
    protected ResourceConnection $resourceConnection;
    protected LoggerInterface $logger;

    public function __construct(
        Context $context,
        GalleryFactory $galleryFactory,
        DataPersistorInterface $dataPersistor,
        ResourceConnection $resourceConnection,
        LoggerInterface $logger
    ) {
        $this->galleryFactory = $galleryFactory;
        $this->dataPersistor = $dataPersistor;
        $this->resourceConnection = $resourceConnection;
        $this->logger = $logger;
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
                $this->logger->warning(sprintf('MediaGallery: Attempted to save non-existent gallery with ID %s.', $id));
                return $resultRedirect->setPath('*/*/');
            }

            // Podstawowa walidacja danych
            if (empty($data['name'])) {
                $this->messageManager->addErrorMessage(__('Gallery name cannot be empty.'));
                $this->dataPersistor->set('mediagallery_gallery', $data);
                return $resultRedirect->setPath('*/*/edit', ['id' => $id]);
            }

            $model->setData($data);

            try {
                $model->save();
                $this->logger->info(sprintf('MediaGallery: Gallery "%s" (ID: %d) saved successfully.', $model->getName(), $model->getId()));

                $this->saveImages($model->getId(), $data['images'] ?? []);
                $this->messageManager->addSuccessMessage(__('You saved the gallery.'));
                $this->dataPersistor->clear('mediagallery_gallery');

                if ($this->getRequest()->getParam('back')) {
                    return $resultRedirect->setPath('*/*/edit', ['id' => $model->getId()]);
                }
                return $resultRedirect->setPath('*/*/');
            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
                $this->logger->error(sprintf('MediaGallery: Localized error saving gallery (ID: %s): %s', $id, $e->getMessage()), ['exception' => $e]);
            } catch (\Exception $e) {
                $this->messageManager->addExceptionMessage($e, __('Something went wrong while saving the gallery.'));
                $this->logger->critical(sprintf('MediaGallery: Critical error saving gallery (ID: %s): %s', $id, $e->getMessage()), ['exception' => $e]);
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

        $connection->beginTransaction();
        try {
            $connection->delete($linkTable, ['gallery_id = ?' => $galleryId]);
            $this->logger->info(sprintf('MediaGallery: Deleted existing asset links for gallery ID %d.', $galleryId));

            $imageLinks = [];
            $assetIdsToValidate = [];
            foreach ($images as $image) {
                if (isset($image['asset_id'])) {
                    $assetIdsToValidate[] = (int)$image['asset_id'];
                }
            }

            $validAssetIds = [];
            if (!empty($assetIdsToValidate)) {
                $selectValidAssets = $connection->select()
                    ->from($assetTable, ['id'])
                    ->where('id IN (?)', array_unique($assetIdsToValidate));
                $validAssetIds = $connection->fetchCol($selectValidAssets);
                $validAssetIds = array_flip($validAssetIds); // Flip for O(1) lookup
            }

            foreach ($images as $image) {
                // Walidacja danych obrazu przed zapisem
                if (!isset($image['file']) || !isset($image['asset_id'])) {
                    $this->logger->warning(sprintf('MediaGallery: Skipping malformed image data for gallery ID %d: %s', $galleryId, json_encode($image)));
                    continue;
                }

                // Sprawdzenie, czy asset_id faktycznie istnieje w media_gallery_asset (zoptymalizowane)
                if (isset($validAssetIds[(int)$image['asset_id']])) {
                    $imageLinks[] = [
                        'gallery_id' => $galleryId,
                        'asset_id' => (int)$image['asset_id'],
                        'sort_order' => (int)($image['position'] ?? 0),
                        'enabled' => (bool)($image['enabled'] ?? true)
                    ];
                } else {
                    $this->logger->warning(sprintf('MediaGallery: Skipping asset link for gallery ID %d. Asset ID %d (file: %s) does not exist in media_gallery_asset or is invalid.', $galleryId, $image['asset_id'], $image['file']));
                }
            }

            if (!empty($imageLinks)) {
                $connection->insertMultiple($linkTable, $imageLinks);
                $this->logger->info(sprintf('MediaGallery: Inserted %d new asset links for gallery ID %d.', count($imageLinks), $galleryId));
            } else {
                $this->logger->info(sprintf('MediaGallery: No new asset links to insert for gallery ID %d.', $galleryId));
            }
            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollBack();
            $this->logger->critical(sprintf('MediaGallery: Critical error saving asset links for gallery ID %d: %s', $galleryId, $e->getMessage()), ['exception' => $e]);
            throw $e; // Ponowne rzucenie wyjątku, aby został obsłużony w execute()
        }
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('GardenLawn_MediaGallery::gallery_save');
    }
}
