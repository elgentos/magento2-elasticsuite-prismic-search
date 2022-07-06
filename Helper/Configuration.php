<?php

declare(strict_types=1);

namespace Elgentos\ElasticsuitePrismicSearch\Helper;

use Smile\ElasticsuiteCore\Helper\AbstractConfiguration;

class Configuration extends AbstractConfiguration
{
    public const CONFIG_XML_PREFIX = 'elgentos_elasticsuite_prismic/prismic_settings';

    public function getConfigValue(string $key): string
    {
        return (string) $this->scopeConfig->getValue(self::CONFIG_XML_PREFIX . "/" . $key);
    }
}
