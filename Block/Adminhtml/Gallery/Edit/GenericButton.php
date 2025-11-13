<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Block\Adminhtml\Gallery\Edit;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\UrlInterface;

class GenericButton
{
    /**
     * @var Context
     */
    protected Context $context;

    /**
     * @param Context $context
     */
    public function __construct(Context $context)
    {
        $this->context = $context;
    }

    /**
     * Generate url by route and parameters
     *
     * @param   string $route
     * @param   array $params
     * @return  string
     */
    public function getUrl(string $route = '', array $params = []): string
    {
        return $this->context->getUrlBuilder()->getUrl($route, $params);
    }

    /**
     * Get gallery ID
     *
     * @return int|null
     */
    public function getGalleryId(): ?int
    {
        return (int)$this->context->getRequest()->getParam('id');
    }
}
