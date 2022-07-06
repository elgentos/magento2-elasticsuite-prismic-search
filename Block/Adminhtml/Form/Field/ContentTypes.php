<?php

declare(strict_types=1);

namespace Elgentos\ElasticsuitePrismicSearch\Block\Adminhtml\Form\Field;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;

/**
 * Class Ranges
 */
class ContentTypes extends AbstractFieldArray
{
    private $contentTypeRenderer;

    /**
     * Prepare rendering the new field by adding all the needed columns
     */
    // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    protected function _prepareToRender()
    {
        $this->addColumn('content_type', [
            'label' => __('Content Type'),
            'class' => 'required-entry',
            'renderer' => $this->getContentTypeRenderer()
        ]);
        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add Content Type')->getText();
    }

    /**
     * Prepare existing row data object
     *
     * @param DataObject $row
     * @throws LocalizedException
     */
    // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    protected function _prepareArrayRow(DataObject $row): void
    {
        $options = [];

        $contentType = $row->getData('content_type');
        if ($contentType !== null) {
            $options['option_' . $this->getContentTypeRenderer()->calcOptionHash($contentType)] = 'selected="selected"';
        }

        $row->setData('option_extra_attrs', $options);
    }

    private function getContentTypeRenderer()
    {
        if (!$this->contentTypeRenderer) {
            $this->contentTypeRenderer = $this->getLayout()->createBlock(
                ContentTypeColumn::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
        }
        return $this->contentTypeRenderer;
    }
}
