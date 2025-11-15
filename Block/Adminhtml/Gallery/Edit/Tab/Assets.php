<?php
namespace GardenLawn\MediaGallery\Block\Adminhtml\Gallery\Edit\Tab;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Widget\Tab\TabInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\LayoutInterface;
use Magento\Framework\Json\EncoderInterface;

class Assets extends Template implements TabInterface
{
    protected $_template = 'GardenLawn_MediaGallery::gallery/edit/tab/assets.phtml';
    protected $registry;
    protected $jsonEncoder;

    public function __construct(
        Template\Context $context,
        Registry $registry,
        EncoderInterface $jsonEncoder,
        array $data = []
    ) {
        $this->registry = $registry;
        $this->jsonEncoder = $jsonEncoder;
        parent::__construct($context, $data);
    }

    public function getTabLabel()
    {
        return __('Assets');
    }

    public function getTabTitle()
    {
        return __('Assets');
    }

    public function canShowTab()
    {
        return true;
    }

    public function isHidden()
    {
        return false;
    }

    public function getGallery()
    {
        return $this->registry->registry('current_gallery');
    }

    public function getLinkedAssetsJson(): string
    {
        $gallery = $this->getGallery();
        if (!$gallery) {
            return '[]';
        }

        // In a real implementation, you would load the asset links
        // For now, we'll return an empty array
        $assetLinks = [];

        return $this->jsonEncoder->encode($assetLinks);
    }
}
