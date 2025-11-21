<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Controller\Adminhtml\Gallery;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\Result\Json;
use Magento\MediaStorage\Model\File\UploaderFactory;
use GardenLawn\MediaGallery\Model\AssetManager;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\RequestInterface;

class Upload extends Action
{
    public const ADMIN_RESOURCE = 'GardenLawn_MediaGallery::gallery_save';

    private UploaderFactory $uploaderFactory;
    private AssetManager $assetManager;
    private LoggerInterface $logger;
    private RequestInterface $request;

    public function __construct(
        Context $context,
        UploaderFactory $uploaderFactory,
        AssetManager $assetManager,
        LoggerInterface $logger,
        RequestInterface $request
    ) {
        parent::__construct($context);
        $this->uploaderFactory = $uploaderFactory;
        $this->assetManager = $assetManager;
        $this->logger = $logger;
        $this->request = $request;
    }

    public function execute(): Json
    {
        try {
            $uploader = $this->uploaderFactory->create(['fileId' => 'asset_uploader[0]']);
            $result = $uploader->save($uploader->getTmpDir());

            $galleryId = (int)$this->getRequest()->getParam('id');
            if (!$galleryId) {
                throw new \Magento\Framework\Exception\LocalizedException(__('Gallery ID is missing.'));
            }

            $fileData = [
                'tmp_name' => $result['path'] . '/' . $result['file'],
                'name' => $result['name']
            ];

            $this->assetManager->processUpload($fileData, $galleryId);

            // On success, we don't need to return complex data,
            // as we will just reload the page on the frontend.
            $result = ['success' => true];

        } catch (\Exception $e) {
            $this->logger->critical($e);
            $result = ['error' => $e->getMessage(), 'errorcode' => $e->getCode()];
        }

        /** @var Json $resultJson */
        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        return $resultJson->setData($result);
    }
}
