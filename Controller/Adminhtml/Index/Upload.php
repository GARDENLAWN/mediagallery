<?php
namespace GardenLawn\MediaGallery\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\MediaStorage\Model\File\UploaderFactory;
use GardenLawn\MediaGallery\Model\AssetFactory;
use Psr\Log\LoggerInterface; // Dodano LoggerInterface

class Upload extends Action
{
    protected JsonFactory $resultJsonFactory;
    protected UploaderFactory $uploaderFactory;
    protected AssetFactory $assetFactory;
    protected LoggerInterface $logger; // Dodano LoggerInterface

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        UploaderFactory $uploaderFactory,
        AssetFactory $assetFactory,
        LoggerInterface $logger // Wstrzykujemy LoggerInterface
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->uploaderFactory = $uploaderFactory;
        $this->assetFactory = $assetFactory;
        $this->logger = $logger; // Przypisujemy LoggerInterface
    }

    public function execute()
    {
        try {
            $uploader = $this->uploaderFactory->create(['fileId' => 'image']);
            $uploader->setAllowedExtensions(['jpg', 'jpeg', 'gif', 'png']);
            $uploader->setAllowRenameFiles(true);
            $uploader->setFilesDispersion(true);
            $uploader->setMaxFileSize(5 * 1024 * 1024); // Limit do 5MB
            $uploader->setAllowMimeTypes(true); // Włącz walidację MIME type

            $result = $uploader->saveFileToTmpDir();

            $assetPath = 'tmp' . $result['file'];

            $asset = $this->assetFactory->create();
            $asset->setPath($assetPath);
            $asset->setTitle($result['name']);
            $asset->save();

            $result['cookie'] = [
                'name' => session_name(),
                'value' => $this->_getSession()->getSessionId(),
                'lifetime' => $this->_getSession()->getCookieLifetime(),
                'path' => $this->_getSession()->getCookiePath(),
                'domain' => $this->_getSession()->getCookieDomain(),
            ];
            $result['asset_id'] = $asset->getId();
            $result['url'] = $this->_urlBuilder->getBaseUrl(['_type' => \Magento\Framework\UrlInterface::URL_TYPE_MEDIA]) . $assetPath;

            $this->logger->info(sprintf('MediaGallery: Successfully uploaded asset "%s" (ID: %d, Path: %s).', $result['name'], $asset->getId(), $assetPath));

        } catch (\Exception $e) {
            $result = ['error' => $e->getMessage(), 'errorcode' => $e->getCode()];
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->logger->error(sprintf('MediaGallery: Error uploading asset: %s', $e->getMessage()), ['exception' => $e]);
        }
        return $this->resultJsonFactory->create()->setData($result);
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('GardenLawn_MediaGallery::asset_upload'); // Zmieniono uprawnienie
    }
}
