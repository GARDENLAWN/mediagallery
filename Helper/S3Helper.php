<?php
namespace GardenLawn\MediaGallery\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\AwsS3\Driver\AwsS3 as AwsS3Driver;
use Magento\Framework\Exception\FileSystemException;
use Psr\Log\LoggerInterface;

class S3Helper extends AbstractHelper
{
    /**
     * @var AwsS3Driver
     */
    protected AwsS3Driver $awsS3Driver;

    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    public function __construct(
        Context $context,
        AwsS3Driver $awsS3Driver
    ) {
        $this->awsS3Driver = $awsS3Driver;
        $this->logger = $context->getLogger();
        parent::__construct($context);
    }

    /**
     * Creates a directory in the S3 bucket.
     *
     * @param string $folderPath The path of the folder to create (e.g., 'wysiwyg/my-folder').
     * @return bool True on success, false on failure.
     */
    public function createFolder(string $folderPath): bool
    {
        try {
            if (!$this->awsS3Driver->isDirectory($folderPath)) {
                return $this->awsS3Driver->createDirectory($folderPath);
            }
            return true; // Directory already exists
        } catch (FileSystemException $e) {
            $this->logger->error('S3 Helper Error - Could not create folder: ' . $folderPath, ['exception' => $e]);
            return false;
        }
    }

    /**
     * Renames a directory in the S3 bucket.
     *
     * @param string $oldPath The original path of the folder.
     * @param string $newPath The new path for the folder.
     * @return bool True on success, false on failure.
     */
    public function renameFolder(string $oldPath, string $newPath): bool
    {
        try {
            return $this->awsS3Driver->rename($oldPath, $newPath);
        } catch (FileSystemException $e) {
            $this->logger->error('S3 Helper Error - Could not rename folder from ' . $oldPath . ' to ' . $newPath, ['exception' => $e]);
            return false;
        }
    }

    /**
     * Deletes a directory and its contents from the S3 bucket.
     *
     * @param string $folderPath The path of the folder to delete.
     * @return bool True on success, false on failure.
     */
    public function deleteFolder(string $folderPath): bool
    {
        try {
            return $this->awsS3Driver->deleteDirectory($folderPath);
        } catch (FileSystemException $e) {
            $this->logger->error('S3 Helper Error - Could not delete folder: ' . $folderPath, ['exception' => $e]);
            return false;
        }
    }
}
