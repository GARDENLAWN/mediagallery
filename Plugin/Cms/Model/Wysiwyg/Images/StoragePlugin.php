<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Plugin\Cms\Model\Wysiwyg\Images;

use Magento\Cms\Model\Wysiwyg\Images\Storage;
use GardenLawn\MediaGallery\Model\AssetLinker;
use GardenLawn\MediaGallery\Model\S3AssetSynchronizer;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\ResourceConnection;

class StoragePlugin
{
    private AssetLinker $assetLinker;
    private S3AssetSynchronizer $synchronizer;
    private LoggerInterface $logger;
    private ResourceConnection $resourceConnection;

    public function __construct(
        AssetLinker $assetLinker,
        S3AssetSynchronizer $synchronizer,
        LoggerInterface $logger,
        ResourceConnection $resourceConnection
    ) {
        $this->assetLinker = $assetLinker;
        $this->synchronizer = $synchronizer;
        $this->logger = $logger;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * After get allowed extensions
     *
     * @param Storage $subject
     * @param array $result
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetAllowedExtensions(Storage $subject, array $result): array
    {
        $newExtensions = ['webp', 'avif', 'svg'];
        return array_merge($result, $newExtensions);
    }

    /**
     * After file upload, trigger sync and link for specific file types.
     *
     * @param Storage $subject
     * @param array $result
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterUploadFile(Storage $subject, array $result): array
    {
        try {
            // Check if upload was successful and we have the necessary data
            if (empty($result['file']) || empty($result['path'])) {
                return $result;
            }

            $fileName = $result['file'];
            $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowedExtensions = ['webp', 'avif', 'svg'];

            if (in_array($extension, $allowedExtensions, true)) {
                // Correctly construct the relative file path
                $directoryUrl = $result['path'];
                $urlPath = parse_url($directoryUrl, PHP_URL_PATH) ?: '';
                $mediaIdentifier = '/media/';
                $mediaPos = strpos($urlPath, $mediaIdentifier);

                if ($mediaPos === false) {
                    $this->logger->warning('[StoragePlugin] Could not determine media relative path from URL: ' . $directoryUrl);
                    return $result;
                }

                $relativeDir = substr($urlPath, $mediaPos + strlen($mediaIdentifier));
                $filePath = rtrim($relativeDir, '/') . '/' . $fileName;

                $this->logger->info('[StoragePlugin] Modern image format uploaded, triggering sync for: ' . $filePath);

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
                    $this->logger->info('[StoragePlugin] Sync and link complete for: ' . $filePath);
                } else {
                    $this->logger->warning('[StoragePlugin] Could not find asset ID after sync for: ' . $filePath);
                }
            }
        } catch (\Exception $e) {
            $this->logger->error(
                '[StoragePlugin] Failed to trigger post-upload tasks: ' . $e->getMessage(),
                ['exception' => $e]
            );
        }

        return $result;
    }
}
