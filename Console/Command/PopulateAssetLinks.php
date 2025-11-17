<?php

namespace GardenLawn\MediaGallery\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\App\ResourceConnection;
use GardenLawn\MediaGallery\Model\ResourceModel\Gallery\CollectionFactory as GalleryCollectionFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputOption;

class PopulateAssetLinks extends Command
{
    const string DRY_RUN_OPTION = 'dry-run';

    protected ResourceConnection $resourceConnection;
    protected GalleryCollectionFactory $galleryCollectionFactory;
    protected LoggerInterface $logger;

    public function __construct(
        ResourceConnection       $resourceConnection,
        GalleryCollectionFactory $galleryCollectionFactory,
        LoggerInterface          $logger,
        string                   $name = null
    )
    {
        $this->resourceConnection = $resourceConnection;
        $this->galleryCollectionFactory = $galleryCollectionFactory;
        $this->logger = $logger;
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('gardenlawn:mediagallery:populate-all')
            ->setDescription('Creates galleries from folder paths and links assets to them.')
            ->addOption(
                self::DRY_RUN_OPTION,
                null,
                InputOption::VALUE_NONE,
                'Do not actually modify the database, just show what would be done.'
            );

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $isDryRun = $input->getOption(self::DRY_RUN_OPTION);
        $mode = $isDryRun ? '<comment>[DRY RUN]</comment> ' : '';

        $connection = $this->resourceConnection->getConnection();
        if (!$isDryRun) {
            $connection->beginTransaction();
        }

        try {
            // Step 1: Populate Galleries from Paths
            $this->populateGalleriesFromPaths($input, $output);

            // Step 2: Populate Asset Links
            $this->populateAssetLinks($input, $output);

            if (!$isDryRun) {
                $connection->commit();
            }
            $output->writeln($mode . '<info>Population script finished successfully.</info>');
            return \Magento\Framework\Console\Cli::RETURN_SUCCESS;

        } catch (\Exception $e) {
            if (!$isDryRun) {
                $connection->rollBack();
            }
            $output->writeln('<error>An error occurred: ' . $e->getMessage() . '</error>');
            $this->logger->critical('MediaGallery CLI Error: ' . $e->getMessage(), ['exception' => $e]);
            return \Magento\Framework\Console\Cli::RETURN_FAILURE;
        }
    }

    private function populateGalleriesFromPaths(InputInterface $input, OutputInterface $output): void
    {
        $isDryRun = $input->getOption(self::DRY_RUN_OPTION);
        $mode = $isDryRun ? '<comment>[DRY RUN]</comment> ' : '';
        $output->writeln($mode . '<info>Step 1: Populating galleries from asset paths...</info>');

        $connection = $this->resourceConnection->getConnection();
        $mediaGalleryAssetTable = $connection->getTableName('media_gallery_asset');
        $galleryTable = $connection->getTableName('gardenlawn_mediagallery');

        // 1. Get all asset paths
        $selectPaths = $connection->select()->from($mediaGalleryAssetTable, ['path']);
        $allAssetPaths = $connection->fetchCol($selectPaths);

        // 2. Extract all unique directory paths
        $directoryPaths = [];
        foreach ($allAssetPaths as $assetPath) {
            $pathParts = explode('/', dirname($assetPath));
            $currentPath = '';
            foreach ($pathParts as $part) {
                if (empty($part) || $part === '.') continue;
                $currentPath .= (empty($currentPath) ? '' : '/') . $part;
                $directoryPaths[$currentPath] = true;
            }
        }
        $uniqueDirectoryPaths = array_keys($directoryPaths);
        ksort($uniqueDirectoryPaths); // Sort paths for logical insertion order

        // 3. Get existing gallery names
        $selectExisting = $connection->select()->from($galleryTable, ['name']);
        $existingGalleryNames = $connection->fetchCol($selectExisting);
        $existingGalleryNames = array_flip($existingGalleryNames); // Use as a hash map for faster lookups

        // 4. Find and insert new galleries
        $galleriesToInsert = [];
        foreach ($uniqueDirectoryPaths as $path) {
            if (!isset($existingGalleryNames[$path])) {
                $galleriesToInsert[] = ['name' => $path, 'enabled' => 1, 'sort_order' => 0];
            }
        }

        if (empty($galleriesToInsert)) {
            $output->writeln($mode . '<comment>  No new galleries to create from folder paths.</comment>');
            return;
        }

        $output->writeln(sprintf($mode . '<info>  Found %d new galleries to create.</info>', count($galleriesToInsert)));
        foreach ($galleriesToInsert as $galleryData) {
            $output->writeln(sprintf($mode . '  - Will create gallery: "%s"', $galleryData['name']));
        }

        if (!$isDryRun) {
            $connection->insertMultiple($galleryTable, $galleriesToInsert);
            $output->writeln(sprintf('<info>  Successfully inserted %d new galleries.</info>', count($galleriesToInsert)));
        }
    }

    private function populateAssetLinks(InputInterface $input, OutputInterface $output): void
    {
        $isDryRun = $input->getOption(self::DRY_RUN_OPTION);
        $mode = $isDryRun ? '<comment>[DRY RUN]</comment> ' : '';
        $output->writeln($mode . '<info>Step 2: Populating asset links...</info>');

        $connection = $this->resourceConnection->getConnection();
        $linkTable = $connection->getTableName('gardenlawn_mediagallery_asset_link');
        $mediaGalleryAssetTable = $connection->getTableName('media_gallery_asset');

        // Select all galleries
        $galleries = $this->galleryCollectionFactory->create();
        $totalGalleries = $galleries->getSize();
        $output->writeln(sprintf('<info>  Found %d galleries to process for linking.</info>', $totalGalleries));

        $totalLinksInserted = 0;

        $selectMaxSortOrders = $connection->select()->from($linkTable, ['gallery_id', 'MAX(sort_order)'])->group('gallery_id');
        $maxSortOrders = $connection->fetchPairs($selectMaxSortOrders);

        foreach ($galleries as $gallery) {
            $galleryId = $gallery->getId();
            $galleryName = $gallery->getName();

            if (empty($galleryName)) {
                $output->writeln(sprintf($mode . '<comment>  Skipping gallery ID %d: Name is empty.</comment>', $galleryId));
                continue;
            }

            $maxSortOrder = $maxSortOrders[$galleryId] ?? 0;
            $currentSortOrder = $maxSortOrder + 1;

            $query = $connection->select()
                ->from(['mga' => $mediaGalleryAssetTable], ['id', 'path'])
                ->where('mga.path LIKE ?', $galleryName . '/%')
                ->joinLeft(
                    ['gmal' => $linkTable],
                    'gmal.asset_id = mga.id AND gmal.gallery_id = ' . $galleryId,
                    []
                )
                ->where('gmal.asset_id IS NULL');

            $assetsToLink = $connection->fetchAll($query);

            if (!empty($assetsToLink)) {
                $linksToInsert = [];
                foreach ($assetsToLink as $asset) {
                    if (!is_numeric($asset['id'])) continue;
                    $linksToInsert[] = [
                        'gallery_id' => $galleryId,
                        'asset_id' => (int)$asset['id'],
                        'sort_order' => $currentSortOrder++,
                        'enabled' => 1
                    ];
                }

                if (!empty($linksToInsert)) {
                    $insertedCount = count($linksToInsert);
                    if (!$isDryRun) {
                        $connection->insertMultiple($linkTable, $linksToInsert);
                    }
                    $totalLinksInserted += $insertedCount;
                    $output->writeln(sprintf($mode . '<info>    Linked %d assets to gallery "%s" (ID: %d).</info>', $insertedCount, $galleryName, $galleryId));
                }
            }
        }

        if ($totalLinksInserted > 0) {
            $output->writeln(sprintf($mode . '<info>  Finished linking. Total new links inserted: %d</info>', $totalLinksInserted));
        } else {
            $output->writeln($mode . '<comment>  No new assets needed to be linked.</comment>');
        }
    }
}
