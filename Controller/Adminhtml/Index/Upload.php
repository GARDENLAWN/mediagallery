<?php
namespace GardenLawn\MediaGallery\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\MediaStorage\Model\File\UploaderFactory;
use GardenLawn\MediaGallery\Model\AssetFactory;
use Psr\Log\LoggerInterface;
use Magento\Framework\Filesystem; // Dodano Filesystem
use Magento\Framework\Filesystem\DirectoryList; // Dodano DirectoryList
use Magento\Framework\Filesystem\Io\File; // Dodano File IO

class Upload extends Action
{
    protected JsonFactory $resultJsonFactory;
    protected UploaderFactory $uploaderFactory;
    protected AssetFactory $assetFactory;
    protected LoggerInterface $logger;
    protected Filesystem $filesystem; // Dodano Filesystem
    protected File $fileIo; // Dodano File IO

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        UploaderFactory $uploaderFactory,
        AssetFactory $assetFactory,
        LoggerInterface $logger,
        Filesystem $filesystem, // Wstrzykujemy Filesystem
        File $fileIo // Wstrzykujemy File IO
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->uploaderFactory = $uploaderFactory;
        $this->assetFactory = $assetFactory;
        $this->logger = $logger;
        $this->filesystem = $filesystem; // Przypisujemy Filesystem
        $this->fileIo = $fileIo; // Przypisujemy File IO
    }

    public function execute()
    {
        $result = [];
        try {
            $uploader = $this->uploaderFactory->create(['fileId' => 'image']);
            $uploader->setAllowedExtensions(['jpg', 'jpeg', 'gif', 'png']);
            $uploader->setAllowRenameFiles(true);
            $uploader->setFilesDispersion(true);
            $uploader->setMaxFileSize(5 * 1024 * 1024); // Limit do 5MB
            $uploader->setAllowMimeTypes(true); // Włącz walidację MIME type

            $tmpResult = $uploader->saveFileToTmpDir();

            // Utwórz docelowy katalog w mediach
            $mediaDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
            $targetPath = 'gardenlawn/mediagallery/' . date('Y/m');
            $mediaDirectory->create($targetPath);

            // Pełna ścieżka do pliku tymczasowego
            $tmpFilePath = $mediaDirectory->getAbsolutePath($uploader->getTmpDir() . $tmpResult['file']);
            // Pełna ścieżka do docelowego pliku
            $finalFilePath = $mediaDirectory->getAbsolutePath($targetPath . $tmpResult['file']);
            // Ścieżka względna do katalogu mediów
            $assetPath = $targetPath . $tmpResult['file'];

            // Przenieś plik z katalogu tymczasowego do docelowego
            $this->fileIo->mv($tmpFilePath, $finalFilePath);

            $asset = $this->assetFactory->create();
            $asset->setPath($assetPath);
            $asset->setTitle($tmpResult['name']);
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
            $result['file'] = $assetPath; // Dodaj finalną ścieżkę pliku do wyniku

            $this->logger->info(sprintf('MediaGallery: Successfully uploaded asset "%s" (ID: %d, Path: %s).', $tmpResult['name'], $asset->getId(), $assetPath));

        } catch (\Exception $e) {
            $result = ['error' => $e->getMessage(), 'errorcode' => $e->getCode()];
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->logger->error(sprintf('MediaGallery: Error uploading asset: %s', $e->getMessage()), ['exception' => $e]);
        }
        return $this->resultJsonFactory->create()->setData($result);
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('GardenLawn_MediaGallery::asset_upload');
    }
}
