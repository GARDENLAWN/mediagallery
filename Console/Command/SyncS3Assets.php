<?php
namespace GardenLawn\MediaGallery\Console\Command;

use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use GardenLawn\MediaGallery\Model\S3AssetSynchronizer;
use Psr\Log\LoggerInterface;

class SyncS3Assets extends Command
{
    const string DRY_RUN_OPTION = 'dry-run';

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
                'Do not modify the database, only show which assets would be added.'
            );
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $isDryRun = $input->getOption(self::DRY_RUN_OPTION);
        $mode = $isDryRun ? '<comment>[DRY RUN]</comment> ' : '';

        try {
            $output->writeln($mode . '<info>Starting S3 assets synchronization...</info>');

            $assetsToInsert = $this->synchronizer->synchronize($isDryRun);

            if (empty($assetsToInsert)) {
                $output->writeln($mode . '<comment>Database is already in sync. No new assets to add.</comment>');
            } else {
                $output->writeln(sprintf($mode . '<info>Found %d new assets to process.</info>', count($assetsToInsert)));
                if ($isDryRun) {
                    foreach ($assetsToInsert as $assetData) {
                        $output->writeln(sprintf($mode . '  - Would add asset: %s', $assetData['path']));
                    }
                } else {
                     $output->writeln(sprintf('<info>Successfully inserted %d new asset records.</info>', count($assetsToInsert)));
                }
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
