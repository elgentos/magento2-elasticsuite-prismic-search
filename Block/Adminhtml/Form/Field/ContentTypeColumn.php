<?php

declare(strict_types=1);

namespace Elgentos\ElasticsuitePrismicSearch\Block\Adminhtml\Form\Field;

use Elgentos\PrismicIO\Model\Source\ContentTypes;
use Magento\Framework\View\Element\Html\Select;
use Magento\Framework\View\Element\Context;

class ContentTypeColumn extends Select
{
    private ContentTypes $contentTypes;

    public function __construct(Context $context, ContentTypes $contentTypes, array $data = [])
    {
        parent::__construct($context, $data);
        $this->contentTypes = $contentTypes;
    }

    public function setInputName(string $value): ContentTypeColumn
    {
        return $this->setName($value);
    }

    public function setInputId(string $value): ContentTypeColumn
    {
        return $this->setId($value);
    }

    /**
     * Render block HTML
     *
     * @return string
     */
    // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    public function _toHtml(): string
    {
        if (!$this->getOptions()) {
            $this->setOptions($this->getSourceOptions());
        }
        return parent::_toHtml();
    }

    private function getSourceOptions(): array
    {
        return $this->contentTypes->toOptionArray();
    }
}
