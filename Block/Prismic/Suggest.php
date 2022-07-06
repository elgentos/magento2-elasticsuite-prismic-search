<?php

declare(strict_types=1);

namespace Elgentos\ElasticsuitePrismicSearch\Block\Prismic;

use Magento\Cms\Helper\Page;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context as TemplateContext;
use Magento\Search\Model\Query;
use Magento\Search\Model\QueryFactory;
use Elgentos\ElasticsuitePrismicSearch\Model\ResourceModel\Prismic\Fulltext\Collection as PrismicCollection;
use Elgentos\ElasticsuitePrismicSearch\Model\ResourceModel\Prismic\Fulltext\CollectionFactory
    as PrismicCollectionFactory;
use Elgentos\ElasticsuitePrismicSearch\Helper\Configuration;

class Suggest extends Template
{
    public const MAX_RESULT = 'max_result';

    private QueryFactory $queryFactory;

    private Configuration $helper;

    private PrismicCollection $prismicCollection;

    protected Page $cmsPage;
    private UrlInterface $urlBuilder;

    public function __construct(
        TemplateContext          $context,
        QueryFactory             $queryFactory,
        PrismicCollectionFactory $prismicCollectionFactory,
        Configuration            $helper,
        Page                     $cmsPage,
        UrlInterface             $urlBuilder,
        array                    $data = []
    ) {
        parent::__construct($context, $data);

        $this->queryFactory = $queryFactory;
        $this->helper = $helper;
        $this->prismicCollection = $this->initPrismicCollection($prismicCollectionFactory);
        $this->cmsPage = $cmsPage;
        $this->urlBuilder = $urlBuilder;
    }

    public function canShowBlock(): bool
    {
        return $this->getResultCount() > 0;
    }

    public function getPrismicCollection(): PrismicCollection
    {
        return $this->prismicCollection;
    }

    public function getPrismicCollectionPerType(): array
    {
        $results = [];
        foreach ($this->prismicCollection as $document) {
            $results[$document->getType()][] = $document;
        }

        return $results;
    }

    public function getNumberOfResults(): int
    {
        return (int)$this->helper->getConfigValue(self::MAX_RESULT);
    }

    public function getResultCount(): int
    {
        return (int) $this->getPrismicCollection()->getSize();
    }

    public function getQuery(): Query
    {
        return $this->queryFactory->get();
    }

    public function getQueryText(): string
    {
        return $this->getQuery()->getQueryText();
    }

    public function getShowAllUrl(): string
    {
        return $this->getUrl('elasticsuite_prismic/result', ['q' => $this->getQueryText()]);
    }

    public function getPageUrl(string $url): string
    {
        return $this->urlBuilder->getUrl($url);
    }

    private function initPrismicCollection(PrismicCollectionFactory $collectionFactory): PrismicCollection
    {
        $prismicCollection = $collectionFactory->create();

        $prismicCollection->setPageSize($this->getNumberOfResults());
        $prismicCollection->addStoreFilter((int)$this->_storeManager->getStore()->getId());
        $prismicCollection->addSearchFilter($this->getQueryText());

        return $prismicCollection;
    }
}
