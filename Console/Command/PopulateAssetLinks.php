<?php

namespace GardenLawn\MediaGallery\Console\Command;

use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\App\ResourceConnection;
use GardenLawn\MediaGallery\Model\AssetLinker;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputOption;

class PopulateAssetLinks extends Command
{
    const string DRY_RUN_OPTION = 'dry-run';

    protected ResourceConnection $resourceConnection;
    protected AssetLinker $assetLinker;
    protected LoggerInterface $logger;

    public function __construct(
        ResourceConnection $resourceConnection,
        AssetLinker        $assetLinker,
        LoggerInterface    $logger,
        string             $name = null
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->assetLinker = $assetLinker;
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
            $output->writeln($mode . '<info>Step 1: Populating galleries from asset paths...</info>');
            if (!$isDryRun) {
                $createdGalleries = $this->assetLinker->createGalleriesFromPaths();
                if (empty($createdGalleries)) {
                    $output->writeln($mode . '<comment>  No new galleries to create from folder paths.</comment>');
                } else {
                    $output->writeln(sprintf('<info>  Successfully created %d new galleries.</info>', count($createdGalleries)));
                    foreach ($createdGalleries as $galleryName) {
                        $output->writeln(sprintf('  - Created gallery: "%s"', $galleryName));
                    }
                }
            } else {
                $output->writeln($mode . '<comment>  Dry run: Skipping actual gallery creation.</comment>');
            }


            // Step 2: Populate Asset Links
            $output->writeln($mode . '<info>Step 2: Populating asset links...</info>');
            if (!$isDryRun) {
                $linkedAssets = $this->assetLinker->linkAssetsToGalleries();
                $totalLinks = 0;
                if (empty($linkedAssets)) {
                    $output->writeln($mode . '<comment>  No new assets needed to be linked.</comment>');
                } else {
                    foreach ($linkedAssets as $galleryId => $data) {
                        $output->writeln(sprintf('<info>    Linked %d assets to gallery "%s" (ID: %d).</info>', $data['count'], $data['name'], $galleryId));
                        $totalLinks += $data['count'];
                    }
                    $output->writeln(sprintf('<info>  Finished linking. Total new links inserted: %d</info>', $totalLinks));
                }
            } else {
                $output->writeln($mode . '<comment>  Dry run: Skipping actual asset linking.</comment>');
            }


            if (!$isDryRun) {
                $connection->commit();
            }
            $output->writeln($mode . '<info>Population script finished successfully.</info>');
            return Cli::RETURN_SUCCESS;

        } catch (\Exception $e) {
            if (!$isDryRun) {
                $connection->rollBack();
            }
            $output->writeln('<error>An error occurred: ' . $e->getMessage() . '</error>');
            $this->logger->critical('MediaGallery CLI Error: ' . $e->getMessage(), ['exception' => $e]);
            return Cli::RETURN_FAILURE;
        }
    }
}
