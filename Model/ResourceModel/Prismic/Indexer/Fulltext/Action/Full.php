<?php

declare(strict_types=1);

namespace Elgentos\ElasticsuitePrismicSearch\Model\ResourceModel\Prismic\Indexer\Fulltext\Action;

use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;
use Elgentos\ElasticsuitePrismicSearch\Model\ItemProvider\PrismicDocuments;
use Smile\ElasticsuiteCore\Model\ResourceModel\Indexer\AbstractIndexer;

class Full extends AbstractIndexer
{
    private PrismicDocuments $prismicDocuments;

    public function __construct(
        ResourceConnection $resource,
        StoreManagerInterface $storeManager,
        PrismicDocuments $prismicDocuments
    ) {
        $this->prismicDocuments = $prismicDocuments;
        parent::__construct($resource, $storeManager);
    }

    public function getSearchablePrismicDocument($storeId, $ids = []): array
    {
        return $this->prismicDocuments->getItems($storeId, $ids);
    }
}
