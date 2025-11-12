<?php

namespace GardenLawn\MediaGallery\Block\Adminhtml\Grid\Edit;

use IntlDateFormatter;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Form\Generic;
use Magento\Cms\Model\Wysiwyg\Config;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;
use Magento\Store\Model\System\Store;

/**
 * Adminhtml Add New Row Form.
 */
class Form extends Generic
{
    /**
     * @var Store
     */
    protected Store $_systemStore;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param FormFactory $formFactory
     * @param Config $wysiwygConfig
     * @param array $data
     */
    public function __construct(
        Context        $context,
        Registry       $registry,
        FormFactory    $formFactory,
        Config         $wysiwygConfig,
        array          $data = []
    )
    {
        $this->_wysiwygConfig = $wysiwygConfig;
        parent::__construct($context, $registry, $formFactory, $data);
    }

    /**
     * Prepare form.
     *
     * @return $this
     * @throws LocalizedException
     */
    protected function _prepareForm(): static
    {
        $dateFormat = $this->_localeDate->getDateFormat(IntlDateFormatter::SHORT);
        $model = $this->_coreRegistry->registry('row_data');
        $form = $this->_formFactory->create(
            ['data' => [
                'id' => 'edit_form',
                'enctype' => 'multipart/form-data',
                'action' => $this->getData('action'),
                'method' => 'post'
            ]
            ]
        );

        $form->setHtmlIdPrefix('gardenlawnmediagallery_');
        if ($model->getMediaGalleryId()) {
            $fieldset = $form->addFieldset(
                'base_fieldset',
                ['legend' => __('Edit Row Data'), 'class' => 'fieldset-wide']
            );
            $fieldset->addField('id', 'hidden', ['name' => 'id']);
        } else {
            $fieldset = $form->addFieldset(
                'base_fieldset',
                ['legend' => __('Add Row Data'), 'class' => 'fieldset-wide']
            );
        }

        $fieldset->addField(
            'name',
            'text',
            [
                'name' => 'name',
                'label' => __('Name'),
                'required' => false
            ]
        );

        $fieldset->addField(
            'sortorder',
            'text',
            [
                'name' => 'sortorder',
                'label' => __('Sort Order'),
                'required' => true
            ]
        );

        $fieldset->addField(
            'enabled',
            'text',
            [
                'name' => 'enabled',
                'label' => __('Enabled'),
                'required' => false
            ]
        );

        $form->setValues($model->getData());
        $form->setUseContainer(true);
        $this->setForm($form);

        return parent::_prepareForm();
    }
}
