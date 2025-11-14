<?php
namespace GardenLawn\MediaGallery\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\MediaStorage\Model\File\UploaderFactory; // Corrected UploaderFactory
use Magento\MediaGalleryApi\Api\Data\AssetInterfaceFactory;
use Magento\MediaGalleryApi\Api\AssetRepositoryInterface;

class Upload extends Action
{
    protected JsonFactory $resultJsonFactory;
    protected UploaderFactory $uploaderFactory; // Changed to UploaderFactory
    protected AssetInterfaceFactory $assetFactory;
    protected AssetRepositoryInterface $assetRepository;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        UploaderFactory $uploaderFactory, // Injected UploaderFactory
        AssetInterfaceFactory $assetFactory,
        AssetRepositoryInterface $assetRepository
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->uploaderFactory = $uploaderFactory; // Assigned UploaderFactory
        $this->assetFactory = $assetFactory;
        $this->assetRepository = $assetRepository;
    }

    public function execute()
    {
        try {
            $uploader = $this->uploaderFactory->create(['fileId' => 'image']); // Create Uploader instance
            $uploader->setAllowedExtensions(['jpg', 'jpeg', 'gif', 'png']);
            $uploader->setAllowRenameFiles(true);
            $uploader->setFilesDispersion(true);

            $result = $uploader->saveFileToTmpDir(); // Save file to tmp directory

            // The path returned by saveFileToTmpDir is relative to the media directory
            // We need to ensure it's correctly stored as an asset path.
            // Magento's MediaGalleryApi expects paths relative to the media base directory.
            $assetPath = 'tmp' . $result['file']; // Path in tmp folder

            $asset = $this->assetFactory->create();
            $asset->setPath($assetPath); // Set the path for the asset
            $asset->setTitle($result['name']); // Use original file name as title
            $this->assetRepository->save($asset);

            // Return data in a format expected by the UI component
            $result['cookie'] = [
                'name' => session_name(),
                'value' => $this->_getSession()->getSessionId(),
                'lifetime' => $this->_getSession()->getCookieLifetime(),
                'path' => $this->_getSession()->getCookiePath(),
                'domain' => $this->_getSession()->getCookieDomain(),
            ];
            // Add asset ID and full URL for the frontend component
            $result['asset_id'] = $asset->getId();
            $result['url'] = $this->_urlBuilder->getBaseUrl(['_type' => \Magento\Framework\UrlInterface::URL_TYPE_MEDIA]) . $assetPath;


        } catch (\Exception $e) {
            $result = ['error' => $e->getMessage(), 'errorcode' => $e->getCode()];
            $this->messageManager->addErrorMessage($e->getMessage());
        }
        return $this->resultJsonFactory->create()->setData($result);
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('GardenLawn_MediaGallery::gallery_save');
    }
}
