<?php
namespace GardenLawn\MediaGallery\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\MediaStorage\Model\File\UploaderFactory;
use GardenLawn\MediaGallery\Model\AssetFactory;
use Psr\Log\LoggerInterface;
// Usunięto: use Magento\Framework\Filesystem;
// Usunięto: use Magento\Framework\Filesystem\DirectoryList;
// Usunięto: use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Controller\Result\Json;
use Magento\MediaStorage\Model\File\Uploader; // Dodano dla type hintingu

class Upload extends Action
{
    protected JsonFactory $resultJsonFactory;
    protected UploaderFactory $uploaderFactory;
    protected AssetFactory $assetFactory;
    protected LoggerInterface $logger;
    // Usunięto: protected Filesystem $filesystem;
    // Usunięto: protected File $fileIo;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        UploaderFactory $uploaderFactory,
        AssetFactory $assetFactory,
        LoggerInterface $logger
        // Usunięto: Filesystem $filesystem,
        // Usunięto: File $fileIo
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->uploaderFactory = $uploaderFactory;
        $this->assetFactory = $assetFactory;
        $this->logger = $logger;
        // Usunięto: $this->filesystem = $filesystem;
        // Usunięto: $this->fileIo = $fileIo;
    }

    public function execute(): Json
    {
        $result = [];
        try {
            /** @var Uploader $uploader */ // Type hinting
            $uploader = $this->uploaderFactory->create(['fileId' => 'image']);
            $uploader->setAllowedExtensions(['jpg', 'jpeg', 'gif', 'png']);
            $uploader->setAllowRenameFiles(true);
            $uploader->setFilesDispersion(true);
            $uploader->setMaxFileSize(5 * 1024 * 1024); // Limit do 5MB
            $uploader->setAllowMimeTypes(true);
            $uploader->setAllowedMimeTypes(['image/jpeg', 'image/png', 'image/gif']);

            // Ścieżka docelowa w magazynie mediów (np. w bucketcie S3 lub lokalnym katalogu mediów)
            $targetPath = 'gardenlawn/mediagallery/' . date('Y/m');

            // saveFileToTmpDir() zapisuje plik do tymczasowego katalogu lokalnego.
            $tmpResult = $uploader->saveFileToTmpDir();

            // moveFileFromTmp() przenosi plik z katalogu tymczasowego do docelowego magazynu.
            // Magento's Uploader jest zaprojektowany do obsługi różnych adapterów magazynowania (lokalny, S3 itp.)
            // w zależności od konfiguracji. Zwraca ścieżkę względną do bazowego katalogu mediów.
            $assetPath = $uploader->moveFileFromTmp($tmpResult['file'], $targetPath);

            $asset = $this->assetFactory->create();
            $asset->setPath($assetPath);
            $asset->setTitle($tmpResult['name']); // Tytuł to oryginalna nazwa pliku
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
