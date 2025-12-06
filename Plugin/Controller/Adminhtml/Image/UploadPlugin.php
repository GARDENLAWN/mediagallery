<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Plugin\Controller\Adminhtml\Image;

use Exception;
use Magento\MediaGalleryUi\Controller\Adminhtml\Image\Upload as UploadController;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\App\RequestInterface;
use GardenLawn\MediaGallery\Model\AssetLinker;
use GardenLawn\MediaGallery\Model\S3AssetSynchronizer;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\ResourceConnection;
use ReflectionClass;

class UploadPlugin
{
    private RequestInterface $request;
    private AssetLinker $assetLinker;
    private S3AssetSynchronizer $synchronizer;
    private LoggerInterface $logger;
    private ResourceConnection $resourceConnection;

    public function __construct(
        RequestInterface $request,
        AssetLinker $assetLinker,
        S3AssetSynchronizer $synchronizer,
        LoggerInterface $logger,
        ResourceConnection $resourceConnection
    ) {
        $this->request = $request;
        $this->assetLinker = $assetLinker;
        $this->synchronizer = $synchronizer;
        $this->logger = $logger;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * After image upload, trigger sync and link for specific file types.
     *
     * @param UploadController $subject
     * @param Json $result
     * @return Json
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterExecute(UploadController $subject, Json $result): Json
    {
        try {
            // FIX: Use reflection to get data from the Json result object
            $reflection = new ReflectionClass($result);
            $property = $reflection->getProperty('json');
            $property->setAccessible(true);
            $jsonString = $property->getValue($result);
            $data = json_decode($jsonString, true);

            if (empty($data['name']) || $data['error'] !== 0) {
                return $result;
            }

            $fileName = $data['name'];
            $targetFolder = $this->request->getParam('target_folder', '/');
            // Ensure target folder from root of media gallery
            $targetFolder = ltrim($targetFolder, '/');
            $filePath = ($targetFolder ? rtrim($targetFolder, '/') . '/' : '') . $fileName;

            $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowedExtensions = ['webp', 'avif', 'svg'];

            if (in_array($extension, $allowedExtensions, true)) {
                $this->logger->info('[UploadPlugin] Modern image format uploaded, triggering sync for: ' . $filePath);

                // 1. Sync the single new asset
                $this->synchronizer->synchronizeSingle($filePath);

                // 2. Get the new asset's ID
                $connection = $this->resourceConnection->getConnection();
                $assetId = $connection->fetchOne(
                    $connection->select()
                        ->from($connection->getTableName('media_gallery_asset'), 'id')
                        ->where('path = ?', $filePath)
                );

                // 3. Link the new asset
                if ($assetId) {
                    $this->assetLinker->linkSingleAsset((int)$assetId, $filePath);
                    $this->logger->info('[UploadPlugin] Sync and link complete for: ' . $filePath);
                } else {
                    $this->logger->warning('[UploadPlugin] Could not find asset ID after sync for: ' . $filePath);
                }
            }
        } catch (Exception $e) {
            $this->logger->error(
                '[UploadPlugin] Failed to trigger post-upload tasks: ' . $e->getMessage(),
                ['exception' => $e]
            );
        }

        return $result;
    }
}
