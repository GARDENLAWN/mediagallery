<?php
namespace GardenLawn\MediaGallery\Console\Command;

use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

class DeduplicateAssets extends Command
{
    const DRY_RUN_OPTION = 'dry-run';

    protected ResourceConnection $resourceConnection;
    protected LoggerInterface $logger;

    public function __construct(
        ResourceConnection $resourceConnection,
        LoggerInterface    $logger,
        string             $name = null
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->logger = $logger;
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('gardenlawn:mediagallery:deduplicate-assets')
            ->setDescription('Finds and removes duplicate assets from the media_gallery_asset table.')
            ->addOption(
                self::DRY_RUN_OPTION,
                null,
                InputOption::VALUE_NONE,
                'Do not modify the database, only show what would be done.'
            );
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $isDryRun = $input->getOption(self::DRY_RUN_OPTION);
        $mode = $isDryRun ? '<comment>[DRY RUN]</comment> ' : '';
        $output->writeln($mode . '<info>Starting asset deduplication process...</info>');

        $connection = $this->resourceConnection->getConnection();
        $assetTable = $connection->getTableName('media_gallery_asset');
        $linkTable = $connection->getTableName('gardenlawn_mediagallery_asset_link');

        try {
            // 1. Find all paths that have duplicates
            $selectDuplicates = $connection->select()
                ->from($assetTable, ['path', 'count' => 'COUNT(id)'])
                ->group('path')
                ->having('count > 1');
            $duplicatePaths = $connection->fetchCol($selectDuplicates);

            if (empty($duplicatePaths)) {
                $output->writeln('<info>No duplicate asset paths found. The database is clean.</info>');
                return Command::SUCCESS;
            }

            $output->writeln(sprintf('<comment>Found %d paths with duplicates.</comment>', count($duplicatePaths)));
            $totalIdsToDelete = 0;

            if (!$isDryRun) $connection->beginTransaction();

            foreach ($duplicatePaths as $path) {
                // 2. For each path, get all associated IDs, ordered from lowest to highest
                $selectIds = $connection->select()
                    ->from($assetTable, 'id')
                    ->where('path = ?', $path)
                    ->order('id ASC');
                $allIds = $connection->fetchCol($selectIds);

                // The first ID is the one we keep, the rest are to be deleted
                $idToKeep = array_shift($allIds);
                $idsToDelete = $allIds;
                $totalIdsToDelete += count($idsToDelete);

                $output->writeln(sprintf('  - Path "%s": Keeping ID %d, removing %d duplicates (IDs: %s).', $path, $idToKeep, count($idsToDelete), implode(', ', $idsToDelete)));

                if (!$isDryRun) {
                    // 3. Update the link table to point all links to the ID we are keeping
                    $connection->update(
                        $linkTable,
                        ['asset_id' => $idToKeep],
                        ['asset_id IN (?)' => $allIds] // Update links that pointed to the duplicates
                    );

                    // 4. Delete the duplicate records from the asset table
                    $connection->delete($assetTable, ['id IN (?)' => $idsToDelete]);
                }
            }

            if (!$isDryRun) $connection->commit();

            $output->writeln(sprintf($mode . '<info>Deduplication process finished. Total records removed: %d</info>', $totalIdsToDelete));
            return Command::SUCCESS;

        } catch (Exception $e) {
            if (!$isDryRun && $connection->isTransactionActive()) {
                $connection->rollBack();
            }
            $output->writeln('<error>An error occurred: ' . $e->getMessage() . '</error>');
            $this->logger->critical('Deduplication Error: ' . $e->getMessage(), ['exception' => $e]);
            return Command::FAILURE;
        }
    }
}
