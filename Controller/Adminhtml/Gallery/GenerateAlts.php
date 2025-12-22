<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Controller\Adminhtml\Gallery;

use GardenLawn\MediaGallery\Api\AssetLinkRepositoryInterface;
use GardenLawn\MediaGallery\Model\ResourceModel\AssetLink\CollectionFactory as AssetLinkCollectionFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class GenerateAlts extends Action
{
    public const ADMIN_RESOURCE = 'GardenLawn_MediaGallery::gallery_save';

    private AssetLinkCollectionFactory $assetLinkCollectionFactory;
    private AssetLinkRepositoryInterface $assetLinkRepository;
    private JsonFactory $resultJsonFactory;
    private LoggerInterface $logger;

    public function __construct(
        Context $context,
        AssetLinkCollectionFactory $assetLinkCollectionFactory,
        AssetLinkRepositoryInterface $assetLinkRepository,
        JsonFactory $resultJsonFactory,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->assetLinkCollectionFactory = $assetLinkCollectionFactory;
        $this->assetLinkRepository = $assetLinkRepository;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->logger = $logger;
    }

    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();
        $galleryId = (int)$this->getRequest()->getParam('gallery_id');
        $galleryName = (string)$this->getRequest()->getParam('gallery_name');

        if (!$galleryId || empty($galleryName)) {
            return $resultJson->setData([
                'success' => false,
                'message' => __('Invalid gallery ID or empty name.')
            ]);
        }

        try {
            $collection = $this->assetLinkCollectionFactory->create();
            $collection->addFieldToFilter('gallery_id', $galleryId);

            $count = 0;
            foreach ($collection as $assetLink) {
                $sortOrder = $assetLink->getSortOrder();
                // Format: "Gallery Name - SortOrder"
                $newAlt = sprintf('%s - %d', $galleryName, $sortOrder);

                $assetLink->setAlt($newAlt);
                $this->assetLinkRepository->save($assetLink);
                $count++;
            }

            return $resultJson->setData([
                'success' => true,
                'message' => __('Successfully generated Alt texts for %1 assets.', $count)
            ]);

        } catch (LocalizedException $e) {
            return $resultJson->setData([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return $resultJson->setData([
                'success' => false,
                'message' => __('An error occurred while generating Alt texts.')
            ]);
        }
    }
}
