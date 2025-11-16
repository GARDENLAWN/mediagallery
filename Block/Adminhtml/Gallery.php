<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Block\Adminhtml;

use Magento\Backend\Block\Widget\Grid\Container;

class Gallery extends Container
{
    protected function _construct(): void
    {
        $this->_controller = 'adminhtml_gallery';
        $this->_blockGroup = 'GardenLawn_MediaGallery';
        $this->_headerText = __('Galleries');
        $this->_addButtonLabel = __('Add New Gallery');
        parent::_construct();
    }
}
