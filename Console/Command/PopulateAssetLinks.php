<?php
namespace GardenLawn\MediaGallery\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\App\ResourceConnection;
use GardenLawn\MediaGallery\Model\ResourceModel\Gallery\CollectionFactory as GalleryCollectionFactory;
use Psr\Log\LoggerInterface;
use Magento\Framework\DB\Expression; // Dodano

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
        // $gardenLawnMediaGalleryTable jest nieużywana, więc ją usuwamy.

        $connection->beginTransaction();
        try {
            // Select all galleries
            $galleries = $this->galleryCollectionFactory->create();
            $totalLinksInserted = 0;

            // Optymalizacja: Pobierz wszystkie maksymalne sort_order dla wszystkich galerii w jednym zapytaniu
            $selectMaxSortOrders = $connection->select()
                ->from(
                    $linkTable,
                    ['gallery_id', new Expression('MAX(sort_order)')]
                )
                ->group('gallery_id');
            $maxSortOrders = $connection->fetchPairs($selectMaxSortOrders); // Zwraca [gallery_id => max_sort_order]

            foreach ($galleries as $gallery) {
                $galleryId = $gallery->getId();
                $galleryName = $gallery->getName();

                if (empty($galleryName)) {
                    $output->writeln(sprintf('<comment>Skipping gallery ID %d: Name is empty.</comment>', $galleryId));
                    $this->logger->warning(sprintf('MediaGallery CLI: Skipping gallery ID %d because its name is empty.', $galleryId));
                    continue;
                }

                // Użyj pre-pobranej wartości, domyślnie 0 jeśli brak wpisów dla tej galerii
                $maxSortOrder = $maxSortOrders[$galleryId] ?? 0;
                $currentSortOrder = $maxSortOrder + 1;

                // Find assets that match the gallery name prefix and are not yet linked
                $query = $connection->select()
                    ->from(['mga' => $mediaGalleryAssetTable], ['id', 'path']) // Pobieramy też path do logowania
                    ->where('mga.path LIKE ?', $galleryName . '/%')
                    ->joinLeft(
                        ['gmal' => $linkTable],
                        'gmal.asset_id = mga.id AND gmal.gallery_id = ' . $galleryId,
                        []
                    )
                    ->where('gmal.asset_id IS NULL');

                $assetsToLink = $connection->fetchAll($query); // Pobieramy wszystkie dane, nie tylko kolumnę ID

                if (!empty($assetsToLink)) {
                    $linksToInsert = [];
                    foreach ($assetsToLink as $asset) {
                        // Dodatkowa walidacja: upewnij się, że asset_id jest liczbą
                        if (!is_numeric($asset['id'])) {
                            $this->logger->warning(sprintf('MediaGallery CLI: Skipping asset with invalid ID "%s" (path: %s) for gallery ID %d.', $asset['id'], $asset['path'], $galleryId));
                            continue;
                        }
                        $linksToInsert[] = [
                            'gallery_id' => $galleryId,
                            'asset_id' => (int)$asset['id'],
                            'sort_order' => $currentSortOrder++,
                            'enabled' => 1
                        ];
                    }

                    if (!empty($linksToInsert)) {
                        $connection->insertMultiple($linkTable, $linksToInsert);
                        $insertedCount = count($linksToInsert);
                        $totalLinksInserted += $insertedCount;
                        $output->writeln(sprintf('<info>  Linked %d assets to gallery "%s" (ID: %d).</info>', $insertedCount, $galleryName, $galleryId));
                        $this->logger->info(sprintf('MediaGallery CLI: Linked %d assets to gallery "%s" (ID: %d).', $insertedCount, $galleryName, $galleryId));
                    } else {
                        $output->writeln(sprintf('<comment>  No valid assets to link for gallery "%s" (ID: %d) after validation.</comment>', $galleryName, $galleryId));
                        $this->logger->info(sprintf('MediaGallery CLI: No valid assets to link for gallery "%s" (ID: %d) after validation.', $galleryName, $galleryId));
                    }
                } else {
                    $output->writeln(sprintf('<comment>  No new assets to link for gallery "%s" (ID: %d).</comment>', $galleryName, $galleryId));
                    $this->logger->info(sprintf('MediaGallery CLI: No new assets to link for gallery "%s" (ID: %d).', $galleryName, $galleryId));
                }
            }

            $connection->commit();
            $output->writeln(sprintf('<info>Successfully populated asset links. Total new links inserted: %d</info>', $totalLinksInserted));
            $this->logger->info(sprintf('MediaGallery CLI: Asset link population script finished. Total new links inserted: %d', $totalLinksInserted));
            return \Magento\Framework\Console\Cli::RETURN_SUCCESS;

        } catch (\Exception $e) {
            $connection->rollBack();
            $output->writeln('<error>An error occurred: ' . $e->getMessage() . '</error>');
            $this->logger->critical('MediaGallery CLI: Error in asset link population script: ' . $e->getMessage(), ['exception' => $e]);
            return \Magento\Framework\Console\Cli::RETURN_FAILURE;
        }
    }
}
