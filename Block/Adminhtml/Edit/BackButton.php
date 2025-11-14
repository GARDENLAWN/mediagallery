<?php
namespace GardenLawn\MediaGallery\Block\Adminhtml\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class BackButton extends GenericButton implements ButtonProviderInterface
{
    public function getButtonData(): array
    {
        return [
            'label' => __('Back'),
            'on_click' => "location.href = '" . $this->getUrl('*/*/') . "'",
            'class' => 'back',
            'sort_order' => 10
        ];
    }
}
