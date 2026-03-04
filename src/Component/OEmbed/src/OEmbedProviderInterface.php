<?php

declare(strict_types=1);

namespace WpPack\Component\OEmbed;

interface OEmbedProviderInterface
{
    /**
     * @return list<OEmbedProviderDefinition>
     */
    public function getProviders(): array;
}
