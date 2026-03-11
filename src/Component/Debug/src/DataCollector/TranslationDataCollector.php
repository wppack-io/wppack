<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\DataCollector;

use WpPack\Component\Debug\Attribute\AsDataCollector;

#[AsDataCollector(name: 'translation', priority: 45)]
final class TranslationDataCollector extends AbstractDataCollector
{
    /** @var array<string, int> */
    private array $domainUsage = [];

    /** @var list<string> */
    private array $loadedDomains = [];

    /** @var list<array{original: string, domain: string}> */
    private array $missingTranslations = [];

    private int $totalLookups = 0;

    public function __construct()
    {
        $this->registerHooks();
    }

    public function getName(): string
    {
        return 'translation';
    }

    public function getLabel(): string
    {
        return 'Translation';
    }

    public function captureGettext(string $translated, string $text, string $domain): string
    {
        $this->totalLookups++;
        if (!isset($this->domainUsage[$domain])) {
            $this->domainUsage[$domain] = 0;
        }
        $this->domainUsage[$domain]++;

        if ($translated === $text && $domain !== 'default') {
            $this->missingTranslations[] = ['original' => $text, 'domain' => $domain];
        }

        return $translated;
    }

    public function captureGettextWithContext(string $translated, string $text, string $context, string $domain): string
    {
        $this->totalLookups++;
        if (!isset($this->domainUsage[$domain])) {
            $this->domainUsage[$domain] = 0;
        }
        $this->domainUsage[$domain]++;

        if ($translated === $text && $domain !== 'default') {
            $this->missingTranslations[] = ['original' => $text, 'domain' => $domain];
        }

        return $translated;
    }

    public function captureNgettext(string $translated, string $single, string $plural, int $number, string $domain): string
    {
        $this->totalLookups++;
        if (!isset($this->domainUsage[$domain])) {
            $this->domainUsage[$domain] = 0;
        }
        $this->domainUsage[$domain]++;

        if ($translated === $single && $domain !== 'default') {
            $this->missingTranslations[] = ['original' => $single, 'domain' => $domain];
        }

        return $translated;
    }

    public function captureTextdomainLoaded(string $domain, string $mofile): void
    {
        $this->loadedDomains[] = $domain;
    }

    public function captureTextdomainUnloaded(string $domain): void
    {
        $index = array_search($domain, $this->loadedDomains, true);
        if ($index !== false) {
            unset($this->loadedDomains[$index]);
            $this->loadedDomains = array_values($this->loadedDomains);
        }
    }

    public function collect(): void
    {
        // Deduplicate missing translations
        $uniqueMissing = [];
        $seen = [];
        foreach ($this->missingTranslations as $entry) {
            $key = $entry['domain'] . '::' . $entry['original'];
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $uniqueMissing[] = $entry;
            }
        }

        $this->data = [
            'total_lookups' => $this->totalLookups,
            'loaded_domains' => array_unique($this->loadedDomains),
            'domain_usage' => $this->domainUsage,
            'missing_translations' => $uniqueMissing,
            'missing_count' => count($uniqueMissing),
        ];
    }

    public function getBadgeValue(): string
    {
        $missingCount = $this->data['missing_count'] ?? 0;

        return $missingCount > 0 ? (string) $missingCount : '';
    }

    public function getBadgeColor(): string
    {
        return 'default';
    }

    public function reset(): void
    {
        parent::reset();
        $this->domainUsage = [];
        $this->loadedDomains = [];
        $this->missingTranslations = [];
        $this->totalLookups = 0;
    }

    private function registerHooks(): void
    {
        if (!function_exists('add_filter')) {
            return;
        }

        add_filter('gettext', [$this, 'captureGettext'], PHP_INT_MAX, 3);
        add_filter('gettext_with_context', [$this, 'captureGettextWithContext'], PHP_INT_MAX, 4);
        add_filter('ngettext', [$this, 'captureNgettext'], PHP_INT_MAX, 5);
        add_action('load_textdomain', [$this, 'captureTextdomainLoaded'], 10, 2);
        add_action('unload_textdomain', [$this, 'captureTextdomainUnloaded'], 10, 1);
    }
}
