<?php

namespace GardenLawn\MediaGallery\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\DeploymentConfig;
use Magento\AwsS3\Model\S3ClientFactory;
use Aws\S3\S3Client;
use Psr\Log\LoggerInterface;

class SyncS3Assets extends Command
{
    const string DRY_RUN_OPTION = 'dry-run';
    const DEPLOYMENT_CONFIG_S3_BUCKET = 'remote_storage/driver_options/bucket';
    const DEPLOYMENT_CONFIG_S3_PREFIX = 'remote_storage/driver_options/prefix';

    protected ResourceConnection $resourceConnection;
    protected S3ClientFactory $s3ClientFactory;
    protected DeploymentConfig $deploymentConfig;
    protected LoggerInterface $logger;
    private ?S3Client $s3Client = null;

    public function __construct(
        ResourceConnection $resourceConnection,
        S3ClientFactory    $s3ClientFactory,
        DeploymentConfig   $deploymentConfig,
        LoggerInterface    $logger,
        string             $name = null
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->s3ClientFactory = $s3ClientFactory;
        $this->deploymentConfig = $deploymentConfig;
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

            $bucket = $this->deploymentConfig->get(self::DEPLOYMENT_CONFIG_S3_BUCKET);
            $prefix = $this->deploymentConfig->get(self::DEPLOYMENT_CONFIG_S3_PREFIX, '');
            if (empty($bucket)) {
                $output->writeln('<error>S3 bucket name is not configured in env.php.</error>');
                return \Magento\Framework\Console\Cli::RETURN_FAILURE;
            }
            $output->writeln(sprintf('<info>  Target Bucket: %s, Prefix: %s</info>', $bucket, $prefix ?: '(none)'));

            $s3FilePaths = $this->getAllS3FilePaths($bucket, $prefix, $output);
            $output->writeln(sprintf('<info>  Found %d files in S3.</info>', count($s3FilePaths)));

            $dbAssetPaths = $this->getExistingDbAssetPaths();
            $output->writeln(sprintf('<info>  Found %d assets in media_gallery_asset table.</info>', count($dbAssetPaths)));

            $assetsToInsert = $this->findNewAssets($s3FilePaths, $dbAssetPaths);

            if (empty($assetsToInsert)) {
                $output->writeln($mode . '<comment>  Database is already in sync. No new assets to add.</comment>');
                $output->writeln($mode . '<info>Synchronization finished successfully.</info>');
                return \Magento\Framework\Console\Cli::RETURN_SUCCESS;
            }

            $output->writeln(sprintf($mode . '<info>  Found %d new assets to insert into the database.</info>', count($assetsToInsert)));

            if (!$isDryRun) {
                $connection = $this->resourceConnection->getConnection();
                $tableName = $connection->getTableName('media_gallery_asset');
                $insertedCount = $connection->insertMultiple($tableName, $assetsToInsert);
                $output->writeln(sprintf('<info>  Successfully inserted %d new asset records.</info>', $insertedCount));
            } else {
                foreach ($assetsToInsert as $assetData) {
                    $output->writeln(sprintf($mode . '  - Would add asset: %s', $assetData['path']));
                }
            }

            $output->writeln($mode . '<info>Synchronization finished successfully.</info>');
            return \Magento\Framework\Console\Cli::RETURN_SUCCESS;

        } catch (\Exception $e) {
            $output->writeln('<error>An error occurred: ' . $e->getMessage() . '</error>');
            $this->logger->critical('S3 Sync CLI Error: ' . $e->getMessage(), ['exception' => $e]);
            return \Magento\Framework\Console\Cli::RETURN_FAILURE;
        }
    }

    private function getS3Client(): S3Client
    {
        if ($this->s3Client === null) {
            $this->s3Client = $this->s3ClientFactory->create();
        }
        return $this->s3Client;
    }

    private function getAllS3FilePaths(string $bucket, string $prefix, OutputInterface $output): array
    {
        $s3Client = $this->getS3Client();
        $allPaths = [];
        $continuationToken = null;

        do {
            $params = [
                'Bucket' => $bucket,
                'Prefix' => $prefix,
            ];
            if ($continuationToken) {
                $params['ContinuationToken'] = $continuationToken;
            }

            $result = $s3Client->listObjectsV2($params);
            $contents = $result->get('Contents');

            if (is_array($contents)) {
                foreach ($contents as $object) {
                    // Ignore directories
                    if (substr($object['Key'], -1) !== '/') {
                        // Remove base prefix from path if it exists
                        $path = $prefix ? preg_replace('/^' . preg_quote($prefix, '/') . '\/?/', '', $object['Key']) : $object['Key'];
                        $allPaths[] = $path;
                    }
                }
            }
            $continuationToken = $result->get('NextContinuationToken');
        } while ($continuationToken);

        return $allPaths;
    }

    private function getExistingDbAssetPaths(): array
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $connection->getTableName('media_gallery_asset');
        $select = $connection->select()->from($tableName, ['path']);
        return array_flip($connection->fetchCol($select));
    }

    private function findNewAssets(array $s3FilePaths, array $dbAssetPaths): array
    {
        $newAssets = [];
        foreach ($s3FilePaths as $s3Path) {
            if (!isset($dbAssetPaths[$s3Path])) {
                $newAssets[] = $this->prepareAssetData($s3Path);
            }
        }
        return $newAssets;
    }

    private function prepareAssetData(string $path): array
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $filename = pathinfo($path, PATHINFO_BASENAME);

        $mediaType = 'image'; // Default
        if (in_array($extension, ['mp4', 'mov', 'avi', 'webm'])) {
            $mediaType = 'video';
        }

        $contentType = 'application/octet-stream'; // Default
        $mimeTypes = [
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
            'gif' => 'image/gif', 'webp' => 'image/webp', 'svg' => 'image/svg+xml',
            'mp4' => 'video/mp4', 'webm' => 'video/webm'
        ];
        if (isset($mimeTypes[$extension])) {
            $contentType = $mimeTypes[$extension];
        }

        return [
            'path' => $path,
            'title' => $filename,
            'source' => 'aws-s3',
            'content_type' => $contentType,
            'media_type' => $mediaType,
        ];
    }
}
