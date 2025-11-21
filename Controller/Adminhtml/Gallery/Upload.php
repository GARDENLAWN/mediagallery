<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Controller\Adminhtml\Gallery;

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Exception\LocalizedException;
use Magento\MediaStorage\Model\File\UploaderFactory;
use GardenLawn\MediaGallery\Model\AssetManager;
use Psr\Log\LoggerInterface;
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;

class Upload extends Action
{
    public const string ADMIN_RESOURCE = 'GardenLawn_MediaGallery::gallery_save';

    private UploaderFactory $uploaderFactory;
    private AssetManager $assetManager;
    private LoggerInterface $logger;
    private Filesystem $filesystem;

    public function __construct(
        Context $context,
        UploaderFactory $uploaderFactory,
        AssetManager $assetManager,
        LoggerInterface $logger,
        Filesystem $filesystem
    ) {
        parent::__construct($context);
        $this->uploaderFactory = $uploaderFactory;
        $this->assetManager = $assetManager;
        $this->logger = $logger;
        $this->filesystem = $filesystem;
    }

    public function execute(): Json
    {
        try {
            $uploader = $this->uploaderFactory->create(['fileId' => 'asset_uploader[0]']);

            $tmpDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::TMP);
            $result = $uploader->save($tmpDirectory->getAbsolutePath());

            // CORRECTED: The uploader gets the gallery_id from the form's data source params
            $galleryId = (int)$this->getRequest()->getParam('gallery_id');
            if (!$galleryId) {
                throw new LocalizedException(__('Gallery ID is missing.'));
            }

            $fileData = [
                'tmp_name' => $result['path'] . '/' . $result['file'],
                'name' => $result['name']
            ];

            $this->assetManager->processUpload($fileData, $galleryId);

            $result = ['success' => true];

        } catch (Exception $e) {
            $this->logger->critical($e);
            $result = ['error' => $e->getMessage(), 'errorcode' => $e->getCode()];
        }

        /** @var Json $resultJson */
        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        return $resultJson->setData($result);
    }
}
