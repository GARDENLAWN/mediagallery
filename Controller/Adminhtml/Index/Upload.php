<?php
namespace GardenLawn\MediaGallery\Controller\Adminhtml\Index;

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\UrlInterface;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\MediaGalleryApi\Api\SaveAssetsInterface;
use Magento\MediaGalleryApi\Api\Data\AssetInterfaceFactory;

class Upload extends Action
{
    protected JsonFactory $resultJsonFactory;
    protected UploaderFactory $uploaderFactory;
    protected Filesystem $filesystem;
    protected StoreManagerInterface $storeManager;
    protected LoggerInterface $logger;
    protected SaveAssetsInterface $saveAsset;
    protected AssetInterfaceFactory $assetFactory;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        UploaderFactory $uploaderFactory,
        Filesystem $filesystem,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger,
        SaveAssetsInterface $saveAsset,
        AssetInterfaceFactory $assetFactory
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->uploaderFactory = $uploaderFactory;
        $this->filesystem = $filesystem;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
        $this->saveAsset = $saveAsset;
        $this->assetFactory = $assetFactory;
    }

    public function execute(): Json
    {
        try {
            $uploader = $this->uploaderFactory->create(['fileId' => 'image']);
            $uploader->setAllowedExtensions(['jpg', 'jpeg', 'gif', 'png']);
            $uploader->setAllowRenameFiles(true);
            $uploader->setFilesDispersion(true);

            $mediaDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
            $result = $uploader->save($mediaDirectory->getAbsolutePath('wysiwyg'));

            $asset = $this->assetFactory->create();
            $asset->setPath($result['file']);
            $asset->setTitle($result['name']);
            $asset->setSource('Local');
            $asset->setContentType($result['type']);
            $this->saveAsset->execute($asset);

            $mediaUrl = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);

            $result['url'] = $mediaUrl . 'wysiwyg/' . $result['file'];
            $result['asset_id'] = $asset->getId();

        } catch (Exception $e) {
            $result = ['error' => $e->getMessage(), 'errorcode' => $e->getCode()];
            $this->logger->error('Media Gallery Upload Error: ' . $e->getMessage());
        }

        return $this->resultJsonFactory->create()->setData($result);
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('GardenLawn_MediaGallery::asset_upload');
    }
}
