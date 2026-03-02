<?php

declare(strict_types=1);

namespace WpPack\Component\OEmbed\Tests\Attribute;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Hook\Attribute\Filter;
use WpPack\Component\Hook\Hook;
use WpPack\Component\Hook\HookType;
use WpPack\Component\OEmbed\Attribute\Filter\EmbedDefaultsFilter;
use WpPack\Component\OEmbed\Attribute\Filter\EmbedHandlersFilter;
use WpPack\Component\OEmbed\Attribute\Filter\EmbedOembedHtmlFilter;
use WpPack\Component\OEmbed\Attribute\Filter\OembedDataparseFilter;
use WpPack\Component\OEmbed\Attribute\Filter\OembedDiscoveryLinksFilter;
use WpPack\Component\OEmbed\Attribute\Filter\OembedFetchUrlFilter;
use WpPack\Component\OEmbed\Attribute\Filter\OembedProvidersFilter;
use WpPack\Component\OEmbed\Attribute\Filter\OembedResponseDataFilter;
use WpPack\Component\OEmbed\Attribute\Filter\OembedResultFilter;
use WpPack\Component\OEmbed\Attribute\Filter\OembedTtlFilter;
use WpPack\Component\OEmbed\Attribute\Filter\OembedWhitelistFilter;
use WpPack\Component\OEmbed\Attribute\Filter\PreOembedResultFilter;

final class NamedHookTest extends TestCase
{
    #[Test]
    public function embedDefaultsFilterHasCorrectHookName(): void
    {
        $filter = new EmbedDefaultsFilter();

        self::assertSame('embed_defaults', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
        self::assertSame(10, $filter->priority);
    }

    #[Test]
    public function embedHandlersFilterHasCorrectHookName(): void
    {
        $filter = new EmbedHandlersFilter();

        self::assertSame('embed_handlers', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
    }

    #[Test]
    public function embedOembedHtmlFilterHasCorrectHookName(): void
    {
        $filter = new EmbedOembedHtmlFilter();

        self::assertSame('embed_oembed_html', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
    }

    #[Test]
    public function oembedDataparseFilterHasCorrectHookName(): void
    {
        $filter = new OembedDataparseFilter();

        self::assertSame('oembed_dataparse', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
    }

    #[Test]
    public function oembedDiscoveryLinksFilterHasCorrectHookName(): void
    {
        $filter = new OembedDiscoveryLinksFilter();

        self::assertSame('oembed_discovery_links', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
    }

    #[Test]
    public function oembedFetchUrlFilterHasCorrectHookName(): void
    {
        $filter = new OembedFetchUrlFilter();

        self::assertSame('oembed_fetch_url', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
    }

    #[Test]
    public function oembedProvidersFilterHasCorrectHookName(): void
    {
        $filter = new OembedProvidersFilter();

        self::assertSame('oembed_providers', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
    }

    #[Test]
    public function oembedResponseDataFilterHasCorrectHookName(): void
    {
        $filter = new OembedResponseDataFilter();

        self::assertSame('oembed_response_data', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
    }

    #[Test]
    public function oembedResultFilterHasCorrectHookName(): void
    {
        $filter = new OembedResultFilter();

        self::assertSame('oembed_result', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
    }

    #[Test]
    public function oembedTtlFilterHasCorrectHookName(): void
    {
        $filter = new OembedTtlFilter();

        self::assertSame('oembed_ttl', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
    }

    #[Test]
    public function oembedWhitelistFilterHasCorrectHookName(): void
    {
        $filter = new OembedWhitelistFilter();

        self::assertSame('oembed_whitelist', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
    }

    #[Test]
    public function preOembedResultFilterHasCorrectHookName(): void
    {
        $filter = new PreOembedResultFilter();

        self::assertSame('pre_oembed_result', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
    }

    #[Test]
    public function embedDefaultsFilterAcceptsCustomPriority(): void
    {
        $filter = new EmbedDefaultsFilter(priority: 5);

        self::assertSame(5, $filter->priority);
    }

    #[Test]
    public function allFiltersExtendFilter(): void
    {
        self::assertInstanceOf(Filter::class, new EmbedDefaultsFilter());
        self::assertInstanceOf(Filter::class, new EmbedHandlersFilter());
        self::assertInstanceOf(Filter::class, new EmbedOembedHtmlFilter());
        self::assertInstanceOf(Filter::class, new OembedDataparseFilter());
        self::assertInstanceOf(Filter::class, new OembedDiscoveryLinksFilter());
        self::assertInstanceOf(Filter::class, new OembedFetchUrlFilter());
        self::assertInstanceOf(Filter::class, new OembedProvidersFilter());
        self::assertInstanceOf(Filter::class, new OembedResponseDataFilter());
        self::assertInstanceOf(Filter::class, new OembedResultFilter());
        self::assertInstanceOf(Filter::class, new OembedTtlFilter());
        self::assertInstanceOf(Filter::class, new OembedWhitelistFilter());
        self::assertInstanceOf(Filter::class, new PreOembedResultFilter());
    }

    #[Test]
    public function namedHooksAreDetectedByIsInstanceof(): void
    {
        $class = new class {
            #[OembedProvidersFilter]
            public function onOembedProviders(): void {}

            #[EmbedDefaultsFilter(priority: 5)]
            public function onEmbedDefaults(): void {}
        };

        $providersMethod = new \ReflectionMethod($class, 'onOembedProviders');
        $attributes = $providersMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('oembed_providers', $attributes[0]->newInstance()->hook);

        $defaultsMethod = new \ReflectionMethod($class, 'onEmbedDefaults');
        $attributes = $defaultsMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('embed_defaults', $attributes[0]->newInstance()->hook);
        self::assertSame(5, $attributes[0]->newInstance()->priority);
    }
}
