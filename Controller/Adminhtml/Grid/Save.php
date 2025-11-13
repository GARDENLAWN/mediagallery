<?php

namespace GardenLawn\MediaGallery\Controller\Adminhtml\Grid;

use Exception;
use GardenLawn\MediaGallery\Model\GridFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;

class Save extends Action
{
    /**
     * @var GridFactory
     */
    var GridFactory $gridFactory;

    public function __construct(
        Context               $context,
        GridFactory           $gridFactory
    )
    {
        parent::__construct($context);
        $this->gridFactory = $gridFactory;
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function execute(): void
    {
        $data = $this->getRequest()->getPostValue();
        if (!$data) {
            $this->_redirect('mediagallery/grid/addrow');
            return;
        }
        try {
            $rowData = $this->gridFactory->create();
            $rowData->setData($data);
            if (isset($data['id'])) {
                $rowData->setMediaGalleryId($data['id']);
            }
            $rowData->save();
            $data['id'] = $rowData->getMediaGalleryId();

            $this->messageManager->addSuccess(__('Row data has been successfully saved.'));
        } catch (Exception $e) {
            $this->messageManager->addError(__($e->getMessage()));
        }
        $this->_redirect(isset($data['id']) ? 'mediagallery/grid/addrow/id/' . $data['id'] : 'mediagallery/grid/index');
    }

    /**
     * @return bool
     */
    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('GardenLawn_MediaGallery::save');
    }
}
