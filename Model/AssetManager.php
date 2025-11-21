<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Model;

use Magento\Cms\Model\Wysiwyg\Images\Storage;
use GardenLawn\MediaGallery\Api\GalleryRepositoryInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Exception\LocalizedException;
use Magento\MediaGalleryApi\Api\GetAssetsByPathsInterface; // CORRECT INTERFACE
use Exception;

class AssetManager
{
    private GalleryRepositoryInterface $galleryRepository;
    private Storage $storage;
    private Filesystem $filesystem;
    private AssetLinkFactory $assetLinkFactory;
    private ResourceModel\AssetLink $assetLinkResource;
    private GetAssetsByPathsInterface $getAssetsByPaths;

    public function __construct(
        GalleryRepositoryInterface $galleryRepository,
        Storage $storage,
        Filesystem $filesystem,
        AssetLinkFactory $assetLinkFactory,
        ResourceModel\AssetLink $assetLinkResource,
        GetAssetsByPathsInterface $getAssetsByPaths // CORRECT DEPENDENCY
    ) {
        $this->galleryRepository = $galleryRepository;
        $this->storage = $storage;
        $this->filesystem = $filesystem;
        $this->assetLinkFactory = $assetLinkFactory;
        $this->assetLinkResource = $assetLinkResource;
        $this->getAssetsByPaths = $getAssetsByPaths;
    }

    /**
     * @throws Exception
     */
    public function processUpload(array $fileData, int $galleryId): array
    {
        // 1. Get gallery path
        $gallery = $this->galleryRepository->getById($galleryId);
        $galleryPath = $gallery->getPath();
        if (!$galleryPath) {
            throw new Exception('Target gallery does not have a valid path.');
        }

        // 2. Use Magento's Storage service to upload the file.
        $mediaDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $targetPath = $mediaDirectory->getAbsolutePath($galleryPath);

        $originalFiles = $_FILES;
        $_FILES['image'] = [
            'name' => $fileData['name'],
            'type' => mime_content_type($fileData['tmp_name']),
            'tmp_name' => $fileData['tmp_name'],
            'error' => 0,
            'size' => filesize($fileData['tmp_name']),
        ];

        $result = $this->storage->uploadFile($targetPath, 'image');
        $_FILES = $originalFiles;

        if (!$result || !isset($result['file'])) {
            throw new LocalizedException(__('Could not create the media gallery asset using Storage model.'));
        }

        // 3. Find the newly created asset using the API
        $newAssetPath = $galleryPath . '/' . $result['file'];
        $assets = $this->getAssetsByPaths->execute([$newAssetPath]);
        $newAsset = reset($assets); // Get the first (and only) asset from the result

        if (!$newAsset || !$newAsset->getId()) {
            throw new LocalizedException(__('Could not find the newly created asset in the database. Path: ' . $newAssetPath));
        }

        // 4. Create link in gardenlawn_mediagallery_asset_link
        $assetLink = $this->assetLinkFactory->create();
        $assetLink->setData([
            'gallery_id' => $galleryId,
            'asset_id' => $newAsset->getId(),
            'enabled' => 1,
        ]);
        $this->assetLinkResource->save($assetLink);

        return [
            'id' => $newAsset->getId(),
            'path' => $newAsset->getPath(),
            'name' => $fileData['name']
        ];
    }
}
