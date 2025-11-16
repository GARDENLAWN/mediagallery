<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Block\Adminhtml\AssetLink\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class BackButton extends GenericButton implements ButtonProviderInterface
{
    /**
     * Get button data
     *
     * @return array
     */
    public function getButtonData(): array
    {
        $galleryId = $this->registry->registry('gardenlawn_mediagallery_asset_link')->getGalleryId();
        return [
            'label' => __('Back'),
            'on_click' => sprintf("location.href = '%s';", $this->getUrl('gardenlawn_mediagallery/index/edit', ['id' => $galleryId])),
            'class' => 'back',
            'sort_order' => 10
        ];
    }
}
