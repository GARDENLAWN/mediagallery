<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Model;

use Magento\MediaGalleryApi\Api\CreateAssetInterface;
use Magento\MediaGalleryApi\Api\Data\AssetInterfaceFactory;
use GardenLawn\MediaGallery\Api\GalleryRepositoryInterface;
use GardenLawn\MediaGallery\Model\ResourceModel\AssetLink\CollectionFactory as AssetLinkCollectionFactory;
use Exception;

class AssetManager
{
    private S3Adapter $s3Adapter;
    private GalleryRepositoryInterface $galleryRepository;
    private CreateAssetInterface $createAsset;
    private AssetInterfaceFactory $assetFactory;
    private AssetLinkFactory $assetLinkFactory;
    private ResourceModel\AssetLink $assetLinkResource;

    public function __construct(
        S3Adapter $s3Adapter,
        GalleryRepositoryInterface $galleryRepository,
        CreateAssetInterface $createAsset,
        AssetInterfaceFactory $assetFactory,
        AssetLinkFactory $assetLinkFactory,
        ResourceModel\AssetLink $assetLinkResource
    ) {
        $this->s3Adapter = $s3Adapter;
        $this->galleryRepository = $galleryRepository;
        $this->createAsset = $createAsset;
        $this->assetFactory = $assetFactory;
        $this->assetLinkFactory = $assetLinkFactory;
        $this->assetLinkResource = $assetLinkResource;
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

        // 2. Upload file to S3
        $destinationPath = rtrim($galleryPath, '/') . '/' . $fileData['name'];
        $this->s3Adapter->uploadFile($fileData['tmp_name'], $destinationPath);

        // 3. Create entry in media_gallery_asset using Magento's service
        $asset = $this->assetFactory->create();
        $asset->setPath($destinationPath);
        $asset->setTitle($fileData['name']);
        $asset->setSource('aws-s3'); // Or your custom source
        $newAsset = $this->createAsset->execute($asset);

        // 4. Create link in gardenlawn_mediagallery_asset_link
        $assetLink = $this->assetLinkFactory->create();
        $assetLink->setData([
            'gallery_id' => $galleryId,
            'asset_id' => $newAsset->getId(),
            'enabled' => 1,
            // You can add logic for sort_order here if needed
        ]);
        $this->assetLinkResource->save($assetLink);

        return [
            'id' => $newAsset->getId(),
            'path' => $newAsset->getPath(),
            'name' => $fileData['name']
        ];
    }
}
