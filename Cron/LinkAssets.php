<?php
namespace GardenLawn\MediaGallery\Cron;

use Psr\Log\LoggerInterface;
use GardenLawn\MediaGallery\Model\AssetLinker;
use Magento\Framework\App\ResourceConnection;

class LinkAssets
{
    protected LoggerInterface $logger;
    protected AssetLinker $assetLinker;
    protected ResourceConnection $resourceConnection;

    public function __construct(
        LoggerInterface $logger,
        AssetLinker $assetLinker,
        ResourceConnection $resourceConnection
    ) {
        $this->logger = $logger;
        $this->assetLinker = $assetLinker;
        $this->resourceConnection = $resourceConnection;
    }

    public function execute(): void
    {
        $this->logger->info('MediaGallery Cron: Starting asset linking job.');
        $connection = $this->resourceConnection->getConnection();
        $connection->beginTransaction();

        try {
            $linkedAssets = $this->assetLinker->linkAssetsToGalleries();
            $totalLinks = 0;

            if (empty($linkedAssets)) {
                $this->logger->info('MediaGallery Cron: No new assets needed to be linked.');
            } else {
                foreach ($linkedAssets as $galleryId => $data) {
                    $this->logger->info(sprintf('MediaGallery Cron: Linked %d assets to gallery "%s" (ID: %d).', $data['count'], $data['name'], $galleryId));
                    $totalLinks += $data['count'];
                }
            }

            $connection->commit();
            $this->logger->info(sprintf('MediaGallery Cron: Asset linking job finished successfully. Total new links: %d', $totalLinks));

        } catch (\Exception $e) {
            $connection->rollBack();
            $this->logger->critical('MediaGallery Cron: Error in asset linking job: ' . $e->getMessage(), ['exception' => $e]);
        }
    }
}
