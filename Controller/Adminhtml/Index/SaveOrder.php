<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use GardenLawn\MediaGallery\Api\GalleryRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;

class SaveOrder extends Action
{
    /**
     * @var JsonFactory
     */
    protected JsonFactory $resultJsonFactory;

    /**
     * @var GalleryRepositoryInterface
     */
    protected GalleryRepositoryInterface $galleryRepository;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param GalleryRepositoryInterface $galleryRepository
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        GalleryRepositoryInterface $galleryRepository
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->galleryRepository = $galleryRepository;
    }

    /**
     * @return Json
     */
    public function execute(): Json
    {
        $result = $this->resultJsonFactory->create();
        $orderData = $this->getRequest()->getParam('order', []);

        if (!$this->getRequest()->isPost() || empty($orderData)) {
            return $result->setData(['error' => true, 'message' => __('Invalid request.')]);
        }

        try {
            foreach ($orderData as $item) {
                $gallery = $this->galleryRepository->getById((int)$item['id']);
                $gallery->setSortorder((int)$item['sortorder']);
                $this->galleryRepository->save($gallery);
            }
            return $result->setData(['error' => false, 'message' => __('Order has been saved.')]);
        } catch (LocalizedException $e) {
            return $result->setData(['error' => true, 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            return $result->setData(['error' => true, 'message' => __('An error occurred while saving the order.')]);
        }
    }

    /**
     * Check admin permissions for this controller
     *
     * @return boolean
     */
    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('GardenLawn_MediaGallery::gallery_save');
    }
}
