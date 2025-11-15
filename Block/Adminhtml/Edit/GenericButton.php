<?php
namespace GardenLawn\MediaGallery\Block\Adminhtml\Edit;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Registry;

class GenericButton
{
    protected Context $context;
    protected Registry $registry;

    public function __construct(
        Context $context,
        Registry $registry
    ) {
        $this->context = $context;
        $this->registry = $registry;
    }

    public function getGalleryId(): ?int
    {
        // Prefer model registered by Edit controller
        $model = $this->registry->registry('current_gallery');
        if (is_object($model) && method_exists($model, 'getId')) {
            $id = (int)$model->getId();
            return $id ?: null;
        }

        // Fallback to request parameter if registry is not set
        $paramId = (int)$this->context->getRequest()->getParam('id');
        return $paramId ?: null;
    }

    public function getUrl($route = '', $params = []): string
    {
        return $this->context->getUrlBuilder()->getUrl($route, $params);
    }
}
