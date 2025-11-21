<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Controller\Adminhtml\Gallery;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\Result\Json;

class Upload extends Action
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    public const ADMIN_RESOURCE = 'GardenLawn_MediaGallery::gallery_save';

    public function __construct(
        Context $context
    ) {
        parent::__construct($context);
    }

    /**
     * Upload file controller action
     *
     * @return Json
     */
    public function execute(): Json
    {
        try {
            // Here we will add the logic to handle the uploaded file
            // For now, we just return a success message for testing
            $result = ['result' => 'File uploaded successfully (logic to be implemented).'];
        } catch (\Exception $e) {
            $result = ['error' => $e->getMessage(), 'errorcode' => $e->getCode()];
        }

        /** @var Json $resultJson */
        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        return $resultJson->setData($result);
    }
}
