<?php

declare(strict_types=1);

namespace Elgentos\ElasticsuitePrismicSearch\Model\Prismic\Indexer\Fulltext\Action;

use Elgentos\ElasticsuitePrismicSearch\Model\ResourceModel\Prismic\Indexer\Fulltext\Action\Full as ResourceModel;
use Magento\Framework\App\Area;
use Magento\Framework\App\AreaList;

class Full
{
    private ResourceModel $resourceModel;

    private AreaList $areaList;

    public function __construct(
        ResourceModel $resourceModel,
        AreaList $areaList
    ) {
        $this->resourceModel  = $resourceModel;
        $this->areaList       = $areaList;
    }

    public function rebuildStoreIndex(int $storeId, $ids = []): \Traversable
    {
        $this->areaList->getArea(Area::AREA_FRONTEND)->load(Area::PART_DESIGN);

        $prismicDocuments = $this->getSearchablePrismicDocument($storeId, $ids);
        foreach ($prismicDocuments as $pageData) {
            yield $pageData['id'] => $pageData;
        }
    }

    private function getSearchablePrismicDocument(int $storeId, $ids = []): array
    {
        return $this->resourceModel->getSearchablePrismicDocument($storeId, $ids);
    }
}
