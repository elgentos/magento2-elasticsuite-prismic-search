<?php

declare(strict_types=1);

namespace Elgentos\ElasticsuitePrismicSearch\Block\Prismic;

use Magento\Cms\Helper\Page;
use Magento\Framework\Phrase;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context as TemplateContext;
use Magento\Search\Model\Query;
use Magento\Search\Model\QueryFactory;
use Elgentos\ElasticsuitePrismicSearch\Model\ResourceModel\Prismic\Fulltext\CollectionFactory
    as PrismicCollectionFactory;
use Elgentos\ElasticsuitePrismicSearch\Model\ResourceModel\Prismic\Fulltext\Collection as PrismicCollection;

class Result extends Template
{
    private QueryFactory $queryFactory;

    private PrismicCollection $prismicCollection;

    protected Page $cmsPage;

    public function __construct(
        TemplateContext $context,
        QueryFactory $queryFactory,
        PrismicCollectionFactory $prismicCollectionFactory,
        Page $cmsPage,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->queryFactory   = $queryFactory;
        $this->prismicCollection = $this->initPrismicCollection($prismicCollectionFactory);
        $this->cmsPage        = $cmsPage;
    }

    // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    protected function _prepareLayout(): Result
    {
        $title = $this->getSearchQueryText();
        $this->pageConfig->getTitle()->set($title);

        // add Home breadcrumb
        $breadcrumbs = $this->getLayout()->getBlock('breadcrumbs');
        if ($breadcrumbs) {
            $breadcrumbs->addCrumb(
                'home',
                [
                    'label' => __('Home'),
                    'title' => __('Go to Home Page'),
                    'link' => $this->_storeManager->getStore()->getBaseUrl()
                ]
            )->addCrumb(
                'search',
                ['label' => $title, 'title' => $title]
            );
        }

        return parent::_prepareLayout();
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

    /**
     * Returns page url.
     *
     * @param int $pageId Page id
     *
     * @return mixed
     */
    public function getPageUrl(int $pageId)
    {
        return $this->cmsPage->getPageUrl($pageId);
    }

    public function getSearchQueryText(): Phrase
    {
        return __("Search results for: '%1'", $this->escapeHtml($this->getQueryText()));
    }

    private function initPrismicCollection(PrismicCollectionFactory $collectionFactory): PrismicCollection
    {
        $prismicCollection = $collectionFactory->create();

        $queryText = $this->getQueryText();
        $prismicCollection->addStoreFilter((int)$this->_storeManager->getStore()->getId());
        $prismicCollection->addSearchFilter($queryText);

        return $prismicCollection;
    }
}
