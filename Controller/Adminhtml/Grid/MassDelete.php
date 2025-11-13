<?php

namespace GardenLawn\MediaGallery\Controller\Adminhtml\Grid;

use GardenLawn\MediaGallery\Model\ResourceModel\Grid\CollectionFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Ui\Component\MassAction\Filter;

class MassDelete extends Action
{
    /**
     * Massactions filter.​_
     * @var Filter
     */
    protected Filter $_filter;

    /**
     * @var CollectionFactory
     */
    protected CollectionFactory $_collectionFactory;

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        Context           $context,
        Filter            $filter,
        CollectionFactory $collectionFactory
    )
    {

        $this->_filter = $filter;
        $this->_collectionFactory = $collectionFactory;
        parent::__construct($context);
    }

    /**
     * @return Redirect
     * @throws LocalizedException
     */
    public function execute(): Redirect
    {
        $collection = $this->_filter->getCollection($this->_collectionFactory->create());
        $recordDeleted = 0;
        foreach ($collection->getItems() as $record) {
            $record->setId($record->getMediaGalleryId());
            $record->delete();
            $recordDeleted++;
        }
        $this->messageManager->addSuccess(__('A total of %1 record(s) have been deleted.', $recordDeleted));

        return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath('*/*/index');
    }

    /**
     * Check Category Map recode delete Permission.
     * @return bool
     */
    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('GardenLawn_MediaGallery::row_data_delete');
    }
}
