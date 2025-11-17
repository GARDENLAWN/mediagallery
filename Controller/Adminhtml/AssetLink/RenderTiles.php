<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Controller\Adminhtml\AssetLink;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Registry;
use Magento\Framework\View\LayoutFactory;
use GardenLawn\MediaGallery\Block\Adminhtml\AssetLink\Tiles;
use Psr\Log\LoggerInterface;

class RenderTiles extends Action implements HttpGetActionInterface
{
    public const string ADMIN_RESOURCE = 'GardenLawn_MediaGallery::items';

    /**
     * @var RawFactory
     */
    private RawFactory $resultRawFactory;

    /**
     * @var LayoutFactory
     */
    private LayoutFactory $layoutFactory;

    /**
     * @var Registry
     */
    private Registry $registry;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param Context $context
     * @param RawFactory $resultRawFactory
     * @param LayoutFactory $layoutFactory
     * @param Registry $registry
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        RawFactory $resultRawFactory,
        LayoutFactory $layoutFactory,
        Registry $registry,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->resultRawFactory = $resultRawFactory;
        $this->layoutFactory = $layoutFactory;
        $this->registry = $registry;
        $this->logger = $logger;
    }

    /**
     * Render asset link tiles.
     *
     * @return Raw
     */
    public function execute(): Raw
    {
        $galleryId = (int)$this->getRequest()->getParam('gallery_id');
        $this->logger->info('RenderTiles Controller: Received gallery_id: ' . $galleryId);

        if ($galleryId) {
            // Unregister first to prevent issues with multiple calls in same request
            if ($this->registry->registry('gardenlawn_mediagallery_gallery_id_for_tiles')) {
                $this->registry->unregister('gardenlawn_mediagallery_gallery_id_for_tiles');
                $this->logger->info('RenderTiles Controller: Unregistered existing gallery_id_for_tiles.');
            }
            $this->registry->register('gardenlawn_mediagallery_gallery_id_for_tiles', $galleryId);
            $this->logger->info('RenderTiles Controller: Registered gallery_id: ' . $galleryId . ' for tiles block.');
        } else {
            $this->logger->warning('RenderTiles Controller: No gallery_id received. Tiles will be empty.');
        }

        $resultRaw = $this->resultRawFactory->create();

        try {
            $this->logger->info('RenderTiles Controller: Attempting to create AssetLink Tiles block.');
            /** @var Tiles $block */
            $block = $this->layoutFactory->create()->createBlock(
                Tiles::class,
                'gardenlawn.gallery.assetlink.tiles',
                ['data' => ['template' => 'GardenLawn_MediaGallery::gallery/assetlink_tiles.phtml']]
            );
            $this->logger->info('RenderTiles Controller: AssetLink Tiles block created successfully.');

            $html = $block->toHtml();
            $this->logger->info('RenderTiles Controller: Block toHtml() returned content length: ' . strlen($html));
            if (strlen($html) < 50) { // Log small HTML content for debugging
                $this->logger->info('RenderTiles Controller: Partial HTML content: ' . $html);
            }

            $resultRaw->setContents($html);
        } catch (\Exception $e) {
            $this->logger->error('RenderTiles Controller: Error rendering asset link tiles: ' . $e->getMessage(), ['exception' => $e]);
            $resultRaw->setContents('Error: ' . $e->getMessage() . ' Check var/log/system.log for details.');
        }

        return $resultRaw;
    }
}
