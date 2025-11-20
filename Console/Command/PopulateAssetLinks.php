<?php

namespace GardenLawn\MediaGallery\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\App\ResourceConnection;
use GardenLawn\MediaGallery\Model\AssetLinker;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputOption;

class PopulateAssetLinks extends Command
{
    const DRY_RUN_OPTION = 'dry-run';
    const WITH_PRUNE_OPTION = 'with-prune';

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
            ->setDescription('Creates galleries from folder paths, links assets, and optionally prunes old galleries.')
            ->addOption(
                self::DRY_RUN_OPTION,
                null,
                InputOption::VALUE_NONE,
                'Do not modify the database, only show what would be done.'
            )->addOption(
                self::WITH_PRUNE_OPTION,
                null,
                InputOption::VALUE_NONE,
                'Enable pruning of galleries that no longer have corresponding asset paths.'
            );

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $isDryRun = $input->getOption(self::DRY_RUN_OPTION);
        $withPrune = $input->getOption(self::WITH_PRUNE_OPTION);
        $mode = $isDryRun ? '<comment>[DRY RUN]</comment> ' : '';

        $connection = $this->resourceConnection->getConnection();
        if (!$isDryRun) {
            $connection->beginTransaction();
        }

        try {
            // Step 1: Populate Galleries from Paths
            $output->writeln($mode . '<info>Step 1: Populating galleries from asset paths...</info>');
            $createdGalleries = $this->assetLinker->createGalleriesFromPaths();
            if (empty($createdGalleries)) {
                $output->writeln($mode . '<comment>  No new galleries to create.</comment>');
            } else {
                $output->writeln(sprintf($mode . '<info>  Created %d new galleries.</info>', count($createdGalleries)));
                if ($isDryRun) {
                    foreach ($createdGalleries as $galleryName) {
                        $output->writeln(sprintf('  - Would create gallery: "%s"', $galleryName));
                    }
                }
            }

            // Step 2: Populate Asset Links
            $output->writeln($mode . '<info>Step 2: Populating asset links...</info>');
            $linkedAssets = $this->assetLinker->linkAssetsToGalleries();
            if (empty($linkedAssets)) {
                $output->writeln($mode . '<comment>  No new assets needed to be linked.</comment>');
            } else {
                $totalLinks = 0;
                foreach ($linkedAssets as $galleryId => $data) {
                    $output->writeln(sprintf($mode . '<info>    Linked %d assets to gallery "%s" (ID: %d).</info>', $data['count'], $data['name'], $galleryId));
                    $totalLinks += $data['count'];
                }
                $output->writeln(sprintf($mode . '<info>  Finished linking. Total new links: %d</info>', $totalLinks));
            }

            // Step 3: Prune Orphaned Galleries
            if ($withPrune) {
                $output->writeln($mode . '<info>Step 3: Pruning orphaned galleries...</info>');
                $deletedGalleries = $this->assetLinker->pruneOrphanedGalleries($isDryRun);
                if (empty($deletedGalleries)) {
                    $output->writeln($mode . '<comment>  No orphaned galleries to prune.</comment>');
                } else {
                    $output->writeln(sprintf($mode . '<info>  Pruned %d orphaned galleries.</info>', count($deletedGalleries)));
                    foreach ($deletedGalleries as $galleryName) {
                        $output->writeln(sprintf('  - %s gallery: "%s"', $isDryRun ? 'Would prune' : 'Pruned', $galleryName));
                    }
                }
            }

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
}
