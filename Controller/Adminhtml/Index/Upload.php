<?php
namespace GardenLawn\MediaGallery\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\MediaStorage\Model\File\UploaderFactory;
use GardenLawn\MediaGallery\Model\AssetFactory; // Używamy własnej fabryki Asset

class Upload extends Action
{
    protected JsonFactory $resultJsonFactory;
    protected UploaderFactory $uploaderFactory;
    protected AssetFactory $assetFactory; // Zmieniono na własną fabrykę Asset

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        UploaderFactory $uploaderFactory,
        AssetFactory $assetFactory // Wstrzykujemy własną fabrykę Asset
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->uploaderFactory = $uploaderFactory;
        $this->assetFactory = $assetFactory; // Przypisujemy własną fabrykę Asset
    }

    public function execute()
    {
        try {
            $uploader = $this->uploaderFactory->create(['fileId' => 'image']);
            $uploader->setAllowedExtensions(['jpg', 'jpeg', 'gif', 'png']);
            $uploader->setAllowRenameFiles(true);
            $uploader->setFilesDispersion(true);

            $result = $uploader->saveFileToTmpDir();

            // Ścieżka zwrócona przez saveFileToTmpDir jest względna do katalogu media/tmp
            // Musimy ją zapisać w bazie danych w formacie, który będzie używany do generowania URL-i
            // W przypadku S3, $result['file'] powinien być już kluczem obiektu S3,
            // ale dla spójności z lokalnym przechowywaniem, często jest to ścieżka względna do base/media
            $assetPath = 'tmp' . $result['file']; // Przykład: 'tmp/m/a/image.jpg'

            $asset = $this->assetFactory->create(); // Tworzymy instancję własnego modelu Asset
            $asset->setPath($assetPath);
            $asset->setTitle($result['name']);
            $asset->save(); // Zapisujemy model Asset do bazy danych

            $result['cookie'] = [
                'name' => session_name(),
                'value' => $this->_getSession()->getSessionId(),
                'lifetime' => $this->_getSession()->getCookieLifetime(),
                'path' => $this->_getSession()->getCookiePath(),
                'domain' => $this->_getSession()->getCookieDomain(),
            ];
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
