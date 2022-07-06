<?php

declare(strict_types=1);

namespace Elgentos\ElasticsuitePrismicSearch\Model\ItemProvider;

use Elgentos\ElasticsuitePrismicSearch\Helper\Configuration;
use Elgentos\PrismicIO\Api\ConfigurationInterface;
use Elgentos\PrismicIO\Exception\ApiNotEnabledException;
use Elgentos\PrismicIO\Model\Api;
use Elgentos\PrismicIO\Renderer\PageFactory;
use Elgentos\PrismicIO\ViewModel\LinkResolver;
use Exception;
use Html2Text\Html2Text;
use Magento\Email\Model\TemplateFactory;
use Magento\Framework\App\Response\Http;
use Magento\Framework\App\ResponseFactory;
use Magento\Framework\App\State;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Prismic\Dom\Link as PrismicLink;
use Prismic\Predicates;
use Psr\Log\LoggerInterface;
use stdClass;

class PrismicDocuments
{
    private Configuration $extensionConfiguration;

    private Json $json;
    private ConfigurationInterface $configuration;
    private StoreManagerInterface $storeManager;
    private Api $apiFactory;
    private array $documents;
    private LoggerInterface $logger;
    private LinkResolver $linkResolver;
    private PageFactory $prismicPageFactory;
    private Emulation $emulation;
    private State $appState;
    private ResponseFactory $responseFactory;

    public function __construct(
        Api                     $apiFactory,
        ConfigurationInterface  $configuration,
        StoreManagerInterface   $storeManager,
        LinkResolver            $linkResolver,
        Configuration           $extensionConfiguration,
        Json                    $json,
        LoggerInterface         $logger,
        PageFactory             $prismicPageFactory,
        Emulation               $emulation,
        ResponseFactory         $responseFactory,
        State                   $appState
    ) {
        $this->extensionConfiguration = $extensionConfiguration;
        $this->json = $json;
        $this->configuration = $configuration;
        $this->storeManager = $storeManager;
        $this->apiFactory = $apiFactory;
        $this->logger = $logger;
        $this->linkResolver = $linkResolver;
        $this->prismicPageFactory = $prismicPageFactory;
        $this->emulation = $emulation;
        $this->appState = $appState;
        $this->responseFactory = $responseFactory;
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
        $api = $this->apiFactory->create();

        foreach ($prismicContentTypes as $prismicContentType) {
            $foundDocuments = [];
            $languageSpecificDocuments = [];

            // If the store has a content language fallback,
            // fetch those documents first
            if ($this->configuration->hasContentLanguageFallback($store)) {
                $page = 0;
                do {
                    $predicates = [
                        Predicates::at('document.type', $prismicContentType)
                    ];
                    if (!empty($ids)) {
                        $predicates[] = Predicates::in('document.id', $ids);
                    }
                    $localeDocuments = $api->query(
                        $predicates,
                        [
                            'lang' => $this->configuration->getContentLanguageFallback($store),
                            'pageSize' => 20,
                            'page' => $page
                        ]
                    );

                    $page++;

                    foreach ($localeDocuments->results as $doc) {
                        $languageSpecificDocumentFound = false;
                        foreach ($doc->alternate_languages ?? [] as $alternateLanguage) {
                            if ($alternateLanguage->lang === $this->configuration->getContentLanguage($store)) {
                                $languageSpecificDocuments[] = $alternateLanguage->id;
                                $languageSpecificDocumentFound = true;
                            }
                        }

                        // If the language-specific document is not found, add
                        // this fallback language document to the
                        // found documents array
                        if (!$languageSpecificDocumentFound && !isset($foundDocuments[$doc->id])) {
                            $foundDocuments[$doc->id] = $doc;
                        }
                    }
                } while (!empty($localeDocuments->results));
            }

            // Now fetch all documents for the specific language
            $page = 0;
            do {
                $predicates = [
                    Predicates::at('document.type', $prismicContentType)
                ];
                $ids = array_unique(array_merge($ids, $languageSpecificDocuments));
                if (!empty($ids)) {
                    $predicates[] = Predicates::in('document.id', $ids);
                }
                $localeDocuments = $api->query(
                    $predicates,
                    [
                        'lang' => $this->configuration->getContentLanguage($store),
                        'pageSize' => 20,
                        'page' => $page
                    ]
                );

                $page++;

                foreach ($localeDocuments->results as $doc) {
                    if (!isset($foundDocuments[$doc->id])) {
                        $foundDocuments[$doc->id] = $doc;
                    }
                }
            } while (!empty($localeDocuments->results));

            $this->logger->info($store->getCode() . ': ' . count($foundDocuments));

            $this->addDocumentsToArray($foundDocuments, $store);
        }

        return $this->documents;
    }

    protected function getPrismicContentTypes(StoreInterface $store): array
    {
        $prismicContentTypes = $this->json->unserialize($this->extensionConfiguration->getConfigValue('content_types'));
        return array_filter(array_column($prismicContentTypes, 'content_type'));
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

            $content = $this->getIndexableTextFromDocument($document, $store);

            $this->documents[] = [
                'id' => $document->id,
                'store_id' => $store->getId(),
                'url' => $url,
                'type' => $document->type,
                'title' => $title[0]->text ?? '',
                'content' => $content
            ];

            $this->logger->info(
                $store->getCode() . ' - ' . $document->type . ': ' . $document->id . ' (' . $title[0]->text . ')'
            );
        }
    }

    public function getUrl(?string $url, Store $store): string
    {
        return str_replace($store->getBaseUrl(), '', $url);
    }

    /**
     * @throws NoSuchEntityException
     * @throws ApiNotEnabledException|Exception
     */
    private function getIndexableTextFromDocument(stdClass $document, StoreInterface $store): string
    {
        return $this->appState->emulateAreaCode('frontend', function () use ($document, $store) {
            $this->emulation->startEnvironmentEmulation($store->getId());

            // Create Prismic Document object
            $prismicPage     = $this->prismicPageFactory->create();
            /** @var \Magento\Framework\View\Result\Page $page */
            $page            = $prismicPage->createPage($document, ['isIsolated' => true]);

            // Remove blocks from layout
            $layout          = $page->getLayout();
            array_map(function ($blockName) use ($layout) {
                    $layout->unsetElement($blockName);
                }, array_filter(
                    array_map('trim',
                        explode(
                            PHP_EOL,
                            $this->extensionConfiguration->getConfigValue('block_blacklist')
                        )
                    )
                )
            );

            // Generate response HTML
            /** @var Http $response */
            $response = $this->responseFactory->create();
            $page->renderResult($response);
            $content = $response->getContent();
            $this->emulation->stopEnvironmentEmulation();

            // Extract text from HTML
            $html = new Html2Text($content);
            return $html->getText();
        });
    }
}
