<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Controller\Adminhtml\AssetLink;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use GardenLawn\MediaGallery\Model\AssetLinkFactory;
use GardenLawn\MediaGallery\Model\ResourceModel\AssetLink as AssetLinkResource;
use Magento\Framework\Controller\Result\Json;

class ToggleStatus extends Action
{
    public const ADMIN_RESOURCE = 'GardenLawn_MediaGallery::gallery_save';

    private JsonFactory $resultJsonFactory;
    private AssetLinkFactory $assetLinkFactory;
    private AssetLinkResource $assetLinkResource;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        AssetLinkFactory $assetLinkFactory,
        AssetLinkResource $assetLinkResource
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->assetLinkFactory = $assetLinkFactory;
        $this->assetLinkResource = $assetLinkResource;
    }

    public function execute(): Json
    {
        $result = $this->resultJsonFactory->create();
        $assetLinkId = (int)$this->getRequest()->getParam('id');

        if (!$this->getRequest()->isPost() || !$assetLinkId) {
            return $result->setData(['error' => true, 'message' => __('Invalid request.')]);
        }

        try {
            $assetLink = $this->assetLinkFactory->create();
            $this->assetLinkResource->load($assetLink, $assetLinkId);

            if (!$assetLink->getId()) {
                throw new \Exception('Asset link not found.');
            }

            $newStatus = !(bool)$assetLink->getEnabled();
            $assetLink->setEnabled($newStatus);
            $this->assetLinkResource->save($assetLink);

            return $result->setData(['error' => false, 'newStatus' => (int)$newStatus]);
        } catch (\Exception $e) {
            return $result->setData(['error' => true, 'message' => $e->getMessage()]);
        }
    }
}
