<?php
namespace GardenLawn\MediaGallery\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\App\ResourceConnection;
use GardenLawn\MediaGallery\Model\ResourceModel\Gallery\CollectionFactory as GalleryCollectionFactory;
use Psr\Log\LoggerInterface;

class PopulateAssetLinks extends Command
{
    protected ResourceConnection $resourceConnection;
    protected GalleryCollectionFactory $galleryCollectionFactory;
    protected LoggerInterface $logger;

    public function __construct(
        ResourceConnection $resourceConnection,
        GalleryCollectionFactory $galleryCollectionFactory,
        LoggerInterface $logger,
        string $name = null
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->galleryCollectionFactory = $galleryCollectionFactory;
        $this->logger = $logger;
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('gardenlawn:mediagallery:populate-asset-links')
            ->setDescription('Populates gardenlawn_mediagallery_asset_link table based on existing galleries and assets.');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Starting population of asset links...</info>');
        $this->logger->info('Starting GardenLawn MediaGallery asset link population script.');

        $connection = $this->resourceConnection->getConnection();
        $linkTable = $connection->getTableName('gardenlawn_mediagallery_asset_link');
        $mediaGalleryAssetTable = $connection->getTableName('media_gallery_asset');
        $gardenLawnMediaGalleryTable = $connection->getTableName('gardenlawn_mediagallery');

        $connection->beginTransaction();
        try {
            // Select all galleries
            $galleries = $this->galleryCollectionFactory->create();
            $totalLinksInserted = 0;

            foreach ($galleries as $gallery) {
                $galleryId = $gallery->getId();
                $galleryName = $gallery->getName();

                if (empty($galleryName)) {
                    $output->writeln(sprintf('<comment>Skipping gallery ID %d: Name is empty.</comment>', $galleryId));
                    continue;
                }

                // Find the maximum sort_order for the current gallery
                $maxSortOrder = (int)$connection->fetchOne(
                    $connection->select()
                        ->from($linkTable, new \Zend_Db_Expr('MAX(sort_order)'))
                        ->where('gallery_id = ?', $galleryId)
                );
                $currentSortOrder = $maxSortOrder + 1;

                // Find assets that match the gallery name prefix and are not yet linked
                $query = $connection->select()
                    ->from(['mga' => $mediaGalleryAssetTable], ['id'])
                    ->where('mga.path LIKE ?', $galleryName . '/%')
                    ->joinLeft(
                        ['gmal' => $linkTable],
                        'gmal.asset_id = mga.id AND gmal.gallery_id = ' . $galleryId,
                        []
                    )
                    ->where('gmal.asset_id IS NULL');

                $assetIdsToLink = $connection->fetchCol($query);

                if (!empty($assetIdsToLink)) {
                    $linksToInsert = [];
                    foreach ($assetIdsToLink as $assetId) {
                        $linksToInsert[] = [
                            'gallery_id' => $galleryId,
                            'asset_id' => $assetId,
                            'sort_order' => $currentSortOrder++, // Inkrementacja sort_order
                            'enabled' => 1      // Default enabled status
                        ];
                    }
                    $connection->insertMultiple($linkTable, $linksToInsert);
                    $insertedCount = count($linksToInsert);
                    $totalLinksInserted += $insertedCount;
                    $output->writeln(sprintf('<info>  Linked %d assets to gallery "%s" (ID: %d).</info>', $insertedCount, $galleryName, $galleryId));
                } else {
                    $output->writeln(sprintf('<comment>  No new assets to link for gallery "%s" (ID: %d).</comment>', $galleryName, $galleryId));
                }
            }

            $connection->commit();
            $output->writeln(sprintf('<info>Successfully populated asset links. Total new links inserted: %d</info>', $totalLinksInserted));
            $this->logger->info(sprintf('GardenLawn MediaGallery asset link population script finished. Total new links inserted: %d', $totalLinksInserted));
            return \Magento\Framework\Console\Cli::RETURN_SUCCESS;

        } catch (\Exception $e) {
            $connection->rollBack();
            $output->writeln('<error>An error occurred: ' . $e->getMessage() . '</error>');
            $this->logger->critical('Error in GardenLawn MediaGallery asset link population script: ' . $e->getMessage(), ['exception' => $e]);
            return \Magento\Framework\Console\Cli::RETURN_FAILURE;
        }
    }
}
