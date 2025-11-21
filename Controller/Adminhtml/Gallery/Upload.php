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
use Magento\Framework\App\RequestInterface;

class Upload extends Action
{
    public const string ADMIN_RESOURCE = 'GardenLawn_MediaGallery::gallery_save';

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
            // The uploader component in the form sends the file under the 'asset_uploader' ID.
            $uploader = $this->uploaderFactory->create(['fileId' => 'asset_uploader[0]']);
            $result = $uploader->save($uploader->getTmpDir());

            // The gallery ID is passed as a parameter from the form's data source.
            $galleryId = (int)$this->request->getParam('gallery_id');
            if (!$galleryId) {
                // Try to get it from the main request if it's not in the uploader's params
                $galleryId = (int)$this->getRequest()->getParam('id');
            }
            if (!$galleryId) {
                throw new LocalizedException(__('Gallery ID is missing.'));
            }

            $fileData = [
                'tmp_name' => $result['path'] . '/' . $result['file'],
                'name' => $result['name']
            ];

            $assetInfo = $this->assetManager->processUpload($fileData, $galleryId);

            // Prepare the response expected by the file uploader component
            $result['id'] = $assetInfo['id'];
            $result['file'] = $assetInfo['path'];
            $result['url'] = $this->getRequest()->getUri()->getScheme() . '://' . $this->getRequest()->getHttpHost() . '/media/' . $assetInfo['path'];
            $result['name'] = $assetInfo['name'];

        } catch (Exception $e) {
            $this->logger->critical($e);
            $result = ['error' => $e->getMessage(), 'errorcode' => $e->getCode()];
        }

        /** @var Json $resultJson */
        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        return $resultJson->setData($result);
    }
}
