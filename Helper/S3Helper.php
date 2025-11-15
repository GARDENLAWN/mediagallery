<?php
namespace GardenLawn\MediaGallery\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\AwsS3\Model\Storage\S3 as S3Storage;

class S3Helper extends AbstractHelper
{
    protected $s3Storage;

    public function __construct(
        Context $context,
        S3Storage $s3Storage
    ) {
        $this->s3Storage = $s3Storage;
        parent::__construct($context);
    }

    public function createFolder(string $folderName): bool
    {
        // In S3, folders are created implicitly by creating an object with a trailing slash.
        $path = rtrim($folderName, '/') . '/';
        return $this->s3Storage->getStorage()->getAdapter()->write($path, '');
    }

    public function renameFolder(string $oldName, string $newName): bool
    {
        // S3 doesn't have a direct rename operation. We need to copy and delete.
        $oldPath = rtrim($oldName, '/') . '/';
        $newPath = rtrim($newName, '/') . '/';

        $objects = $this->s3Storage->getStorage()->getAdapter()->listObjects(['prefix' => $oldPath]);

        foreach ($objects as $object) {
            $newKey = str_replace($oldPath, $newPath, $object['Key']);
            $this->s3Storage->getStorage()->getAdapter()->copy($object['Key'], $newKey);
            $this->s3Storage->getStorage()->getAdapter()->delete($object['Key']);
        }
        return true;
    }

    public function deleteFolder(string $folderName): bool
    {
        $path = rtrim($folderName, '/') . '/';
        return $this->s3Storage->getStorage()->getAdapter()->deleteDirectory($path);
    }
}
