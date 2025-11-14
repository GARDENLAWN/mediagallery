<?php
namespace GardenLawn\MediaGallery\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\MediaGallery\Model\File\Uploader;
use Magento\MediaGalleryApi\Api\Data\AssetInterfaceFactory;
use Magento\MediaGalleryApi\Api\AssetRepositoryInterface;

class Upload extends Action
{
    protected JsonFactory $resultJsonFactory;
    protected Uploader $uploader;
    protected AssetInterfaceFactory $assetFactory;
    protected AssetRepositoryInterface $assetRepository;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        Uploader $uploader,
        AssetInterfaceFactory $assetFactory,
        AssetRepositoryInterface $assetRepository
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->uploader = $uploader;
        $this->assetFactory = $assetFactory;
        $this->assetRepository = $assetRepository;
    }

    public function execute()
    {
        try {
            $result = $this->uploader->saveFileToTmpDir('image');
            $asset = $this->assetFactory->create();
            $asset->setPath($result['file']);
            $asset->setTitle($result['name']);
            $this->assetRepository->save($asset);

            $result['cookie'] = [
                'name' => session_name(),
                'value' => $this->_getSession()->getSessionId(),
                'lifetime' => $this->_getSession()->getCookieLifetime(),
                'path' => $this->_getSession()->getCookiePath(),
                'domain' => $this->_getSession()->getCookieDomain(),
            ];

        } catch (\Exception $e) {
            $result = ['error' => $e->getMessage(), 'errorcode' => $e->getCode()];
        }
        return $this->resultJsonFactory->create()->setData($result);
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('GardenLawn_MediaGallery::gallery_save');
    }
}
