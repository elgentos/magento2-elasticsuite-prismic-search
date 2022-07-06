<?php

declare(strict_types=1);

namespace Elgentos\ElasticsuitePrismicSearch\Block\Adminhtml\Form\Field;

use Elgentos\PrismicIO\Model\ResourceModel\Route\Collection;
use Elgentos\PrismicIO\Model\ResourceModel\Route\CollectionFactory;
use Magento\Framework\View\Element\Html\Select;
use Magento\Framework\View\Element\Context;

class ContentTypeColumn extends Select
{
    private Collection $routeCollection;

    public function __construct(
        Context $context,
        CollectionFactory $routeCollectionFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->routeCollection = $routeCollectionFactory->create();
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

    protected function _toOptionArray(
        Collection $collection,
        string $valueField = 'id',
        string $labelField = 'name',
        array $additional = []
    ): array {
        $res                 = [];
        $additional['value'] = $valueField;
        $additional['label'] = $labelField;

        foreach ($collection as $item) {
            foreach ($additional as $code => $field) {
                $data[$code] = $item->getData($field);
            }
            $res[] = $data;
        }

        return $res;
    }

    private function getSourceOptions(): array
    {
        return $this->_toOptionArray($this->routeCollection, 'content_type',
            'title');
    }
}
