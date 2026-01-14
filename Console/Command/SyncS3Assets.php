<?php
namespace GardenLawn\MediaGallery\Console\Command;

use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use GardenLawn\MediaGallery\Model\S3AssetSynchronizer;
use Psr\Log\LoggerInterface;

class SyncS3Assets extends Command
{
    const string DRY_RUN_OPTION = 'dry-run';
    const string WITH_DELETE_OPTION = 'with-delete';
    const string FORCE_UPDATE_OPTION = 'force-update';

    protected S3AssetSynchronizer $synchronizer;
    protected LoggerInterface $logger;

    public function __construct(
        S3AssetSynchronizer $synchronizer,
        LoggerInterface     $logger,
        string              $name = null
    ) {
        $this->synchronizer = $synchronizer;
        $this->logger = $logger;
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('gardenlawn:mediagallery:sync-s3')
            ->setDescription('Synchronizes AWS S3 assets with the media_gallery_asset table.')
            ->addOption(
                self::DRY_RUN_OPTION,
                null,
                InputOption::VALUE_NONE,
                'Do not modify the database, only show what would be done.'
            )->addOption(
                self::WITH_DELETE_OPTION,
                null,
                InputOption::VALUE_NONE,
                'Enable deletion of database assets that are no longer in S3.'
            )->addOption(
                self::FORCE_UPDATE_OPTION,
                null,
                InputOption::VALUE_NONE,
                'Force update of existing assets if hash/width/height is missing or hash has changed.'
            );
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $isDryRun = $input->getOption(self::DRY_RUN_OPTION);
        $withDelete = $input->getOption(self::WITH_DELETE_OPTION);
        $forceUpdate = $input->getOption(self::FORCE_UPDATE_OPTION);
        $mode = $isDryRun ? '<comment>[DRY RUN]</comment> ' : '';

        try {
            $output->writeln($mode . '<info>Starting S3 assets synchronization...</info>');
            if ($withDelete) $output->writeln($mode . '<comment>Deletion is enabled.</comment>');
            if ($forceUpdate) $output->writeln($mode . '<comment>Force update is enabled.</comment>');

            $result = $this->synchronizer->synchronize($isDryRun, $withDelete, $forceUpdate);
            $assetsToInsert = $result['inserted'];
            $assetsToUpdate = $result['updated'];
            $assetsToDelete = $result['deleted'];

            $hasChanges = false;

            // Handle insertions
            if (empty($assetsToInsert)) {
                $output->writeln($mode . '<info>No new assets to insert.</info>');
            } else {
                $hasChanges = true;
                $output->writeln(sprintf($mode . '<info>Found %d new assets to insert.</info>', count($assetsToInsert)));
                if ($isDryRun) {
                    foreach ($assetsToInsert as $assetData) $output->writeln(sprintf('  - Would add: %s', $assetData['path']));
                } else {
                    $output->writeln(sprintf('<info>Successfully inserted %d new asset records.</info>', count($assetsToInsert)));
                }
            }

            // Handle updates
            if ($forceUpdate) {
                if (empty($assetsToUpdate)) {
                    $output->writeln($mode . '<info>No existing assets need an update.</info>');
                } else {
                    $hasChanges = true;
                    $output->writeln(sprintf($mode . '<info>Found %d existing assets to update.</info>', count($assetsToUpdate)));
                    if ($isDryRun) {
                        foreach ($assetsToUpdate as $assetData) $output->writeln(sprintf('  - Would update: %s', $assetData['path']));
                    } else {
                        $output->writeln(sprintf('<info>Successfully updated %d existing asset records.</info>', count($assetsToUpdate)));
                    }
                }
            }

            // Handle deletions
            if ($withDelete) {
                if (empty($assetsToDelete)) {
                    $output->writeln($mode . '<info>No orphaned assets to delete.</info>');
                } else {
                    $hasChanges = true;
                    $output->writeln(sprintf($mode . '<info>Found %d orphaned assets to delete.</info>', count($assetsToDelete)));
                    if ($isDryRun) {
                        foreach ($assetsToDelete as $path) $output->writeln(sprintf('  - Would delete: %s', $path));
                    } else {
                        $output->writeln(sprintf('<info>Successfully deleted %d orphaned asset records.</info>', count($assetsToDelete)));
                    }
                }
            }

            if (!$hasChanges) {
                $output->writeln($mode . '<comment>Database is already in sync.</comment>');
            }

            $output->writeln($mode . '<info>Synchronization finished successfully.</info>');
            return Cli::RETURN_SUCCESS;

        } catch (\Exception $e) {
            $output->writeln('<error>An error occurred: ' . $e->getMessage() . '</error>');
            $this->logger->critical('S3 Sync CLI Error: ' . $e->getMessage(), ['exception' => $e]);
            return Cli::RETURN_FAILURE;
        }
    }
}
