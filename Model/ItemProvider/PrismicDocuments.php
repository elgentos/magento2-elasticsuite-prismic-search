<?php

declare(strict_types=1);

namespace Elgentos\ElasticsuitePrismicSearch\Model\ItemProvider;

use Elgentos\ElasticsuitePrismicSearch\Helper\Configuration;
use Elgentos\PrismicIO\Api\ConfigurationInterface;
use Elgentos\PrismicIO\Exception\ApiNotEnabledException;
use Elgentos\PrismicIO\Model\Api as ApiFactory;
use Elgentos\PrismicIO\Model\ResourceModel\Route\Collection as RouteCollection;
use Elgentos\PrismicIO\Renderer\PageFactory;
use Elgentos\PrismicIO\ViewModel\LinkResolver;
use Elgentos\PrismicIO\Model\ResourceModel\Route\CollectionFactory as RouteCollectionFactory;
use Html2Text\Html2Text;
use Magento\Email\Model\TemplateFactory;
use Magento\Framework\App\State;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Layout;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Model\ResourceModel\UrlRewriteCollection;
use Magento\UrlRewrite\Model\ResourceModel\UrlRewriteCollectionFactory;
use Magento\UrlRewrite\Model\UrlRewrite;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Prismic\Api as PrismicApi;
use Prismic\Dom\Link as PrismicLink;
use Prismic\Predicates;
use Psr\Log\LoggerInterface;

class PrismicDocuments
{
    const CHUNK_SIZE = 20;
    const BLOCK_BLACKLIST = 'block_blacklist';

    private PrismicApi $api;
    private Configuration $extensionConfiguration;
    private ConfigurationInterface $configuration;
    private StoreManagerInterface $storeManager;
    private array $documents;
    private array $foundDocuments;
    private array $languageSpecificDocuments;
    private LoggerInterface $logger;
    private LinkResolver $linkResolver;
    private PageFactory $prismicPageFactory;
    private Emulation $emulation;
    private State $appState;
    private RouteCollection $routeCollection;
    private UrlRewriteCollection $urlRewriteCollection;
    private EventManager $eventManager;

    public function __construct(
        ApiFactory                  $apiFactory,
        ConfigurationInterface      $configuration,
        StoreManagerInterface       $storeManager,
        LinkResolver                $linkResolver,
        Configuration               $extensionConfiguration,
        LoggerInterface             $logger,
        PageFactory                 $prismicPageFactory,
        Emulation                   $emulation,
        State                       $appState,
        RouteCollectionFactory      $routeCollectionFactory,
        UrlRewriteCollectionFactory $urlRewriteCollectionFactory,
        EventManager                $eventManager
    ) {
        $this->extensionConfiguration = $extensionConfiguration;
        $this->configuration = $configuration;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
        $this->linkResolver = $linkResolver;
        $this->prismicPageFactory = $prismicPageFactory;
        $this->emulation = $emulation;
        $this->appState = $appState;
        $this->routeCollection = $routeCollectionFactory->create();
        $this->api = $apiFactory->create();
        $this->urlRewriteCollection = $urlRewriteCollectionFactory->create();
        $this->eventManager = $eventManager;
    }

    /**
     * @param int   $storeId
     * @param array $ids
     *
     * @return array
     * @throws ApiNotEnabledException
     * @throws NoSuchEntityException
     */
    public function getItems(int $storeId, array $ids = []): array
    {
        $this->documents = [];

        $store = $this->storeManager->getStore($storeId);

        $prismicContentTypes = $this->getPrismicContentTypes($store);

        $this->foundDocuments = [];

        $this->fetchUrlRewriteDocuments($store, $ids);

        foreach ($prismicContentTypes as $prismicContentType) {
            $this->languageSpecificDocuments = [];
            $this->fetchFallbackLanguageDocuments($store, $prismicContentType, $ids);
            $this->fetchSpecificLanguageDocuments($store, $prismicContentType, $ids);
            $this->logger->info($store->getCode() . ': ' . count($this->foundDocuments));
        }

        $this->addDocumentsToArray($this->foundDocuments, $store);

        return $this->documents;
    }

    protected function getPrismicContentTypes(StoreInterface $store): array
    {
        return $this->routeCollection->getColumnValues('content_type');
    }

    protected function addDocumentsToArray(array $documents, StoreInterface $store): void
    {
        if (empty($documents)) {
            return;
        }

        foreach ($documents as $document) {
            $document->store = $store;
            $document->link_type = 'Document';

            $url = $this->getUrl(
                PrismicLink::asUrl($document, $this->linkResolver),
                $store
            );

            $title = current(array_filter((array)$document->data, function ($item) {
                return is_array($item)
                    && isset($item[0], $item[0]->type)
                    && stripos($item[0]->type, 'heading') !== false;
            }));

            $documentData = [
                'id' => $document->id,
                'store_id' => $store->getId(),
                'url' => $url,
                'type' => $document->type,
                'title' => $title[0]->text ?? '',
                'content' => $this->getIndexableTextFromDocument($document, $store)
            ];

            $this->eventManager->dispatch(
                'elgentos_elasticsuite_prismic_search_before_indexation',
                ['document' => $document, 'documentData' => $documentData]
            );

            if ($documentData['content']) {
                $this->documents[] = $documentData;

                $this->logger->info(
                    $store->getCode() . ' - ' . $document->type . ': ' . $document->id . ' (' . $title[0]->text . ')'
                );
            }
        }
    }

    public function getUrl(?string $url, Store $store): string
    {
        return str_replace($store->getBaseUrl(), '', $url);
    }

    /**
     * @throws NoSuchEntityException
     * @throws ApiNotEnabledException|\Exception
     */
    private function getIndexableTextFromDocument(\stdClass $document, StoreInterface $store): string
    {
        return $this->appState->emulateAreaCode('frontend', function () use ($document, $store) {
            $this->emulation->startEnvironmentEmulation($store->getId());

            // Create Prismic Document object
            $prismicPage     = $this->prismicPageFactory->create();
            /** @var \Magento\Framework\View\Result\Page $page */
            $page            = $prismicPage->createPage($document, ['isIsolated' => true]);

            // Remove blocks from layout
            /** @var Layout $layout */
            $layout = $page->getLayout();

            array_map(static function ($blockName) use ($layout, $document) {
                if (stripos($blockName, '::') !== false) {
                    [$prismicContentType, $blockName] = explode('::', $blockName);
                    if ($prismicContentType === $document->type) {
                        $layout->unsetElement($blockName);
                    }
                } else {
                    $layout->unsetElement($blockName);
                }
            }, array_filter(
                array_map(
                    'trim',
                    explode(
                        PHP_EOL,
                        $this->extensionConfiguration->getConfigValue(self::BLOCK_BLACKLIST)
                    )
                )
            ));

            $content = $layout->renderElement('prismicio_content');
            $this->emulation->stopEnvironmentEmulation();

            // Extract text from HTML
            $html = new Html2Text($content);
            return $html->getText();
        });
    }

    private function fetchFallbackLanguageDocuments(
        StoreInterface $store,
        string $prismicContentType,
        array $ids
    ): void {
        if ($this->configuration->hasContentLanguageFallback($store)) {
            $page = 0;
            do {
                $predicates = [
                    Predicates::at('document.type', $prismicContentType)
                ];
                if (!empty($ids)) {
                    $predicates[] = Predicates::in('document.id', $ids);
                }
                $localeDocuments = $this->api->query(
                    $predicates,
                    [
                        'lang' => $this->configuration->getContentLanguageFallback($store),
                        'pageSize' => self::CHUNK_SIZE,
                        'page' => $page
                    ]
                );

                $page++;

                foreach ($localeDocuments->results as $doc) {
                    $languageSpecificDocumentFound = false;
                    foreach ($doc->alternate_languages ?? [] as $alternateLanguage) {
                        if ($alternateLanguage->lang === $this->configuration->getContentLanguage($store)) {
                            $this->languageSpecificDocuments[] = $alternateLanguage->id;
                            $languageSpecificDocumentFound     = true;
                        }
                    }

                    // If the language-specific document is not found, add
                    // this fallback language document to the
                    // found documents array
                    if (!$languageSpecificDocumentFound && !isset($this->foundDocuments[$doc->id])) {
                        $this->foundDocuments[$doc->id] = $doc;
                    }
                }
            } while (!empty($localeDocuments->results));
        }
    }

    private function fetchSpecificLanguageDocuments(
        StoreInterface $store,
        string $prismicContentType,
        array $ids
    ): void {
        $page = 0;
        do {
            $predicates = [
                Predicates::at('document.type', $prismicContentType)
            ];
            $ids = array_unique(array_merge($ids, $this->languageSpecificDocuments));
            if (!empty($ids)) {
                $predicates[] = Predicates::in('document.id', $ids);
            }
            $localeDocuments = $this->api->query(
                $predicates,
                [
                    'lang' => $this->configuration->getContentLanguage($store),
                    'pageSize' => self::CHUNK_SIZE,
                    'page' => $page
                ]
            );

            $page++;

            foreach ($localeDocuments->results as $doc) {
                if (!isset($this->foundDocuments[$doc->id])) {
                    $this->foundDocuments[$doc->id] = $doc;
                }
            }
        } while (!empty($localeDocuments->results));
    }

    private function fetchUrlRewriteDocuments(
        StoreInterface $store,
        array $ids
    ): void {
        $urlRewrites = $this->urlRewriteCollection
            ->addFieldToFilter('target_path', ['like' => 'prismicio/direct/page/%'])
            ->addFieldToFilter('store_id', $store->getId())
            ->addFieldToSelect(['store_id', 'request_path', 'target_path']);

        $urlRewriteDocuments = array_reduce($urlRewrites->getItems(), function ($carry, $urlRewrite) {
            [,,,,$contentType,,$uid] = explode('/', $urlRewrite->getData('target_path'));
            if (!isset($carry[$contentType])) $carry[$contentType] = [];
            $carry[$contentType][] = $uid;
            return $carry;
        }, []);

        foreach ($urlRewriteDocuments as $prismicContentType => $uids) {
            foreach(array_chunk($uids, self::CHUNK_SIZE) as $uidsChunk) {
                $localeDocuments = $this->api->query(
                    [
                        Predicates::at('document.type', $prismicContentType),
                        Predicates::in(sprintf('my.%s.uid', $prismicContentType), $uidsChunk)
                    ],
                    [
                        'lang' => $this->configuration->getContentLanguage($store),
                        'pageSize' => self::CHUNK_SIZE,
                        'page' => 0
                    ]
                );

                foreach ($localeDocuments->results as $doc) {
                    if (!isset($this->foundDocuments[$doc->id])) {
                        $this->foundDocuments[$doc->id] = $doc;
                    }
                }
            }
        }
    }
}
