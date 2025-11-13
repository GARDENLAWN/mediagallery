<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Controller\Adminhtml\Gallery;

use GardenLawn\MediaGallery\Model\ResourceModel\Gallery as GalleryResource;
use GardenLawn\MediaGalleryAsset\Model\ResourceModel\Asset\CollectionFactory as AssetCollectionFactory;
use GardenLawn\MediaGalleryAsset\Model\ResourceModel\Asset as AssetResource;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;

class Delete extends Action
{
    public const ADMIN_RESOURCE = 'GardenLawn_MediaGallery::gallery_delete';

    /**
     * @var GalleryResource
     */
    protected GalleryResource $galleryResource;

    /**
     * @var AssetCollectionFactory
     */
    protected AssetCollectionFactory $assetCollectionFactory;

    /**
     * @var AssetResource
     */
    protected AssetResource $assetResource;

    /**
     * @param Context $context
     * @param GalleryResource $galleryResource
     * @param AssetCollectionFactory $assetCollectionFactory
     * @param AssetResource $assetResource
     */
    public function __construct(
        Context $context,
        GalleryResource $galleryResource,
        AssetCollectionFactory $assetCollectionFactory,
        AssetResource $assetResource
    ) {
        $this->galleryResource = $galleryResource;
        $this->assetCollectionFactory = $assetCollectionFactory;
        $this->assetResource = $assetResource;
        parent::__construct($context);
    }

    /**
     * Delete action
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $id = (int)$this->getRequest()->getParam('id');
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        if ($id) {
            try {
                $model = $this->galleryResource->load(
                    $this->galleryResource->create(), // Create a new model instance for loading
                    $id
                );

                if (!$model->getId()) {
                    $this->messageManager->addErrorMessage(__('We can\'t find a gallery to delete.'));
                    return $resultRedirect->setPath('*/*/');
                }

                // Delete associated assets first
                $assets = $this->assetCollectionFactory->create()
                    ->addFieldToFilter('mediagallery_id', $id);

                foreach ($assets as $asset) {
                    $this->assetResource->delete($asset); // This will trigger _afterDelete in AssetResource
                }

                $this->galleryResource->delete($model);
                $this->messageManager->addSuccessMessage(__('You deleted the gallery.'));
                return $resultRedirect->setPath('*/*/');
            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
                return $resultRedirect->setPath('*/*/edit', ['id' => $id]);
            } catch (\Exception $e) {
                $this->messageManager->addExceptionMessage($e, __('Something went wrong while deleting the gallery.'));
                return $resultRedirect->setPath('*/*/edit', ['id' => $id]);
            }
        }
        $this->messageManager->addErrorMessage(__('We can\'t find a gallery to delete.'));
        return $resultRedirect->setPath('*/*/');
    }
}
