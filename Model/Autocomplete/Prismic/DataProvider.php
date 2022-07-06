<?php

declare(strict_types=1);

namespace Elgentos\ElasticsuitePrismicSearch\Model\Autocomplete\Prismic;

use Elgentos\ElasticsuitePrismicSearch\Block\Prismic\Suggest;
use Elgentos\ElasticsuitePrismicSearch\Model\ResourceModel\Prismic\Fulltext\Collection;
use Magento\Search\Model\Autocomplete\DataProviderInterface;
use Magento\Search\Model\Autocomplete\Item;
use Magento\Search\Model\QueryFactory;
use Magento\Search\Model\Autocomplete\ItemFactory;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Elgentos\ElasticsuitePrismicSearch\Helper\Configuration as ConfigurationHelper;
use Elgentos\ElasticsuitePrismicSearch\Model\ResourceModel\Prismic\Fulltext\CollectionFactory as PrismicCollectionFactory;
use Smile\ElasticsuiteCore\Model\Autocomplete\Terms\DataProvider as TermDataProvider;
use Magento\Framework\Event\ManagerInterface as EventManager;

class DataProvider implements DataProviderInterface
{
    public const AUTOCOMPLETE_TYPE = 'prismic';

    protected ItemFactory $itemFactory;

    protected QueryFactory $queryFactory;

    protected TermDataProvider $termDataProvider;

    protected PrismicCollectionFactory $prismicCollectionFactory;

    protected ConfigurationHelper $configurationHelper;

    protected StoreManagerInterface $storeManager;

    protected EventManager $eventManager;

    private string $type;

    public function __construct(
        ItemFactory $itemFactory,
        QueryFactory $queryFactory,
        TermDataProvider $termDataProvider,
        PrismicCollectionFactory $prismicCollectionFactory,
        ConfigurationHelper $configurationHelper,
        StoreManagerInterface $storeManager,
        EventManager $eventManager,
        string $type = self::AUTOCOMPLETE_TYPE
    ) {
        $this->itemFactory              = $itemFactory;
        $this->queryFactory             = $queryFactory;
        $this->termDataProvider         = $termDataProvider;
        $this->prismicCollectionFactory = $prismicCollectionFactory;
        $this->configurationHelper      = $configurationHelper;
        $this->eventManager             = $eventManager;
        $this->type                     = $type;
        $this->storeManager             = $storeManager;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getItems(): array
    {
        $result = [];

        foreach ($this->getPrismicCollection() as $document) {
            /** @var Store $store */
            $store = $this->storeManager->getStore();
            $item  = $this->itemFactory->create(
                [
                    'title' => $document->getTitle(),
                    'url'   => $store->getBaseUrl(). $document->getIdentifier(),
                    'type'  => $this->getType(),
                ]
            );

            $this->eventManager->dispatch(
                'smile_elasticsuite_prismic_search_autocomplete_page_item',
                ['document' => $document, 'item' => $item]
            );

            $result[] = $item;
        }

        return $result;
    }

    private function getPrismicCollection(): Collection
    {
        $prismicCollection = $this->prismicCollectionFactory->create();

        $prismicCollection->setPageSize($this->getResultsPageSize());

        $queryText = $this->queryFactory->get()->getQueryText();
        $prismicCollection->addSearchFilter($queryText);

        return $prismicCollection;
    }

    private function getResultsPageSize(): int
    {
        return (int) $this->configurationHelper->getConfigValue(Suggest::MAX_RESULT);
    }
}
