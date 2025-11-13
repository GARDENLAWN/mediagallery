<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Controller\Adminhtml\Gallery;

use GardenLawn\MediaGallery\Model\ResourceModel\Gallery\CollectionFactory;
use GardenLawn\MediaGallery\Model\ResourceModel\Gallery as GalleryResource;
use GardenLawn\MediaGalleryAsset\Model\ResourceModel\Asset\CollectionFactory as AssetCollectionFactory;
use GardenLawn\MediaGalleryAsset\Model\ResourceModel\Asset as AssetResource;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Ui\Component\MassAction\Filter;

class MassDelete extends Action
{
    public const ADMIN_RESOURCE = 'GardenLawn_MediaGallery::gallery_delete';

    /**
     * @var Filter
     */
    protected Filter $filter;

    /**
     * @var CollectionFactory
     */
    protected CollectionFactory $collectionFactory;

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
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param GalleryResource $galleryResource
     * @param AssetCollectionFactory $assetCollectionFactory
     * @param AssetResource $assetResource
     */
    public function __construct(
        Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory,
        GalleryResource $galleryResource,
        AssetCollectionFactory $assetCollectionFactory,
        AssetResource $assetResource
    ) {
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->galleryResource = $galleryResource;
        $this->assetCollectionFactory = $assetCollectionFactory;
        $this->assetResource = $assetResource;
        parent::__construct($context);
    }

    /**
     * Execute action for mass delete
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $deleted = 0;
            foreach ($collection->getItems() as $gallery) {
                // Delete associated assets first
                $assets = $this->assetCollectionFactory->create()
                    ->addFieldToFilter('mediagallery_id', $gallery->getId());

                foreach ($assets as $asset) {
                    $this->assetResource->delete($asset); // This will trigger _afterDelete in AssetResource
                }

                $this->galleryResource->delete($gallery);
                $deleted++;
            }
            $this->messageManager->addSuccessMessage(__('A total of %1 record(s) have been deleted.', $deleted));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('Something went wrong while deleting the gallery(s).'));
        }

        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        return $resultRedirect->setPath('*/*/');
    }
}
