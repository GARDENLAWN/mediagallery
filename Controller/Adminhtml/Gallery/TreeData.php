<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Controller\Adminhtml\Gallery;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Layout;
use Magento\Framework\View\LayoutFactory;
use GardenLawn\MediaGallery\Block\Adminhtml\Gallery\Tree;

class TreeData extends Action
{
    public const string ADMIN_RESOURCE = 'GardenLawn_MediaGallery::gallery';

    private JsonFactory $resultJsonFactory;
    private LayoutFactory $layoutFactory;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        LayoutFactory $layoutFactory
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->layoutFactory = $layoutFactory;
    }

    public function execute(): Json|ResultInterface|ResponseInterface
    {
        /** @var Layout $layout */
        $layout = $this->layoutFactory->create();

        /** @var Tree $treeBlock */
        $treeBlock = $layout->createBlock(Tree::class);

        $data = json_decode($treeBlock->getTreeJson(), true);

        $resultJson = $this->resultJsonFactory->create();
        return $resultJson->setData($data);
    }
}
