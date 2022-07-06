<?php

declare(strict_types=1);

namespace Elgentos\ElasticsuitePrismicSearch\Controller\Result;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    protected PageFactory $resultPageFactory;

    public function __construct(
        Context     $context,
        PageFactory $pageFactory
    ) {
        parent::__construct($context);

        $this->resultPageFactory = $pageFactory;
    }

    public function execute(): Page
    {
        $resultPage = $this->resultPageFactory->create();

        return $resultPage;
    }
}
