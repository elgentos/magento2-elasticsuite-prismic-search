<?php

declare(strict_types=1);

namespace Elgentos\ElasticsuitePrismicSearch\Model\ResourceModel\Prismic\Fulltext;

use Magento\Framework\Api\Search\Document;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\DataObject;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Search\Model\SearchEngine;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\Search\ResponseInterface;
use Smile\ElasticsuiteCore\Search\Request\Builder;
use Smile\ElasticsuiteCore\Search\RequestInterface;

class Collection extends \Magento\Cms\Model\ResourceModel\Page\Collection
{
    private ResponseInterface $queryResponse;

    private Builder $requestBuilder;

    private SearchEngine $searchEngine;

    private string $queryText;

    private string $searchRequestName;

    private array $filters = [];

    private array $facets = [];

    private int $storeId;

    public function __construct(
        EntityFactoryInterface $entityFactory,
        LoggerInterface        $logger,
        FetchStrategyInterface $fetchStrategy,
        ManagerInterface       $eventManager,
        StoreManagerInterface  $storeManager,
        MetadataPool           $metadataPool,
        Builder                $requestBuilder,
        SearchEngine           $searchEngine,
        string                 $searchRequestName = 'prismic_search_container',
        AdapterInterface       $connection = null,
        AbstractDb             $resource = null
    ) {
        parent::__construct(
            $entityFactory,
            $logger,
            $fetchStrategy,
            $eventManager,
            $storeManager,
            $metadataPool,
            $connection,
            $resource
        );

        $this->requestBuilder = $requestBuilder;
        $this->searchEngine = $searchEngine;
        $this->searchRequestName = $searchRequestName;
    }

    /**
    * Add filter by store
    *
    * @param int|array|\Magento\Store\Model\Store $storeId Store id
    *
    * @return $this
    */
    public function setStoreId($storeId)
    {
        $this->storeId = $storeId;

        return $this;
    }

    /**
     * Returns current store id.
     *
     * @return int
     */
    public function getStoreId()
    {
        return $this->storeId;
    }

    /**
     * Add filter by store
     *
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag) Method is inherited
     * @SuppressWarnings(PHPMD.UnusedFormalParameter) Method is inherited
     *
     * @param int|array|\Magento\Store\Model\Store $store     Store
     * @param bool                                 $withAdmin With admin
     *
     * @return $this
     */
    public function addStoreFilter($store, $withAdmin = true)
    {
        if (is_object($store)) {
            $store = $store->getId();
        }

        if (is_array($store)) {
            throw new \LogicException("Filtering on multiple stores is not allowed in search engine collections.");
        }

        return $this->setStoreId($store);
    }

    /**
     * Add search query filter
     *
     * @param string $query Search query text.
     *
     * @return $this
     */
    public function addSearchFilter($query)
    {
        $this->queryText = $query;

        return $this;
    }

    /**
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     *
     * {@inheritdoc}
     */
    // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    protected function _renderFiltersBefore(): void
    {
        $searchRequest = $this->prepareRequest();

        $this->queryResponse = $this->searchEngine->search($searchRequest);

        $this->_totalRecords = $this->queryResponse->count();

        $documents = array_map(
            function (Document $doc) {
                /** @phpstan-ignore-next-line */
                return $doc->getSource();
            },
            /** @phpstan-ignore-next-line */
            $this->queryResponse->getIterator()->getArrayCopy()
        );
    }

    /**
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     *
     * {@inheritDoc}
     */
    // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    protected function _afterLoad()
    {
        // Resort items according the search response.
        $orginalItems = $this->_items;
        $this->_items = [];

        foreach ($this->queryResponse->getIterator() as $document) {
            $documentId = $document->getId();
            $document = new DataObject($document->getSource());
            $document->setData('store_id', $this->storeId);
            $this->_items[$documentId] = $document;
        }

        return parent::_afterLoad();
    }

    /**
     * Prepare the search request before it will be executed.
     *
     * @return RequestInterface
     */
    private function prepareRequest()
    {
        // Store id and request name.
        $storeId = $this->storeId;
        $searchRequestName = $this->searchRequestName;

        // Pagination params.
        $size = $this->_pageSize ? $this->_pageSize : 20;
        $from = $size * (max(1, $this->_curPage) - 1);

        // Query text.
        $queryText = $this->queryText;

        // Setup sort orders.
        $sortOrders = $this->prepareSortOrders();

        $searchRequest = $this->requestBuilder->create(
            $storeId,
            $searchRequestName,
            $from,
            $size,
            $queryText,
            $sortOrders,
            $this->filters,
            $this->facets
        );

        return $searchRequest;
    }

    /**
     * Convert standard field name to ES fieldname.
     * (eg. category_ids => category.category_id).
     *
     * @param string $fieldName Field name to be mapped.
     *
     * @return string
     */
    private function mapFieldName($fieldName)
    {
        if (isset($this->fieldNameMapping[$fieldName])) {
            $fieldName = $this->fieldNameMapping[$fieldName];
        }

        return $fieldName;
    }

    /**
     * Prepare sort orders for the request builder.
     *
     * @return array()
     */
    private function prepareSortOrders()
    {
        $sortOrders = [];

        foreach ($this->_orders as $attribute => $direction) {
            $sortParams = ['direction' => $direction];
            $sortField = $this->mapFieldName($attribute);
            $sortOrders[$sortField] = $sortParams;
        }

        return $sortOrders;
    }
}
