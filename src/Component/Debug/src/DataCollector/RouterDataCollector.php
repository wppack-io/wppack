<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\Debug\DataCollector;

use WPPack\Component\Debug\Attribute\AsDataCollector;

#[AsDataCollector(name: 'router', priority: 150)]
final class RouterDataCollector extends AbstractDataCollector
{
    private string $matchedRule = '';
    private string $matchedQuery = '';
    /** @var array<string, string> */
    private array $queryVars = [];
    private string $templateFile = '';

    public function __construct()
    {
        $this->registerHooks();
    }

    public function getName(): string
    {
        return 'router';
    }

    public function getLabel(): string
    {
        return 'Router';
    }

    public function collect(): void
    {
        $is404 = is_404();
        $isFrontPage = is_front_page();
        $isSingular = is_singular();
        $isArchive = is_archive();
        $isSearch = is_search();

        $queryType = match (true) {
            $is404 => '404',
            $isFrontPage => 'front_page',
            $isSingular => 'singular',
            $isArchive => 'archive',
            $isSearch => 'search',
            default => 'other',
        };

        $isBlockTheme = wp_is_block_theme();

        $blockTemplate = [
            'slug' => '',
            'source' => '',
            'theme' => '',
            'type' => '',
            'has_theme_file' => false,
            'file_path' => '',
            'id' => '',
            'parts' => [],
        ];

        if ($isBlockTheme) {
            $blockTemplate = $this->collectBlockTemplate($blockTemplate);
        }

        $this->data = [
            'matched_rule' => $this->matchedRule,
            'matched_query' => $this->matchedQuery,
            'query_vars' => $this->queryVars,
            'template' => $this->templateFile !== '' ? basename($this->templateFile) : '',
            'template_path' => $this->templateFile,
            'is_404' => $is404,
            'rewrite_rules_count' => $this->getRewriteRulesCount(),
            'is_front_page' => $isFrontPage,
            'is_singular' => $isSingular,
            'is_archive' => $isArchive,
            'is_search' => $isSearch,
            'query_type' => $queryType,
            'is_block_theme' => $isBlockTheme,
            'theme_exists' => wp_get_theme()->exists(),
            'block_template' => $blockTemplate,
        ];
    }

    public function getIndicatorValue(): string
    {
        if ($this->data['is_404'] ?? false) {
            return '404';
        }

        if ($this->data['is_block_theme'] ?? false) {
            return $this->data['block_template']['slug'] ?? '';
        }

        return $this->data['template'] ?? '';
    }

    public function getIndicatorColor(): string
    {
        if ($this->data['is_404'] ?? false) {
            return 'red';
        }

        if (($this->data['matched_rule'] ?? '') !== '') {
            return 'green';
        }

        return 'default';
    }

    public function reset(): void
    {
        parent::reset();
        $this->matchedRule = '';
        $this->matchedQuery = '';
        $this->queryVars = [];
        $this->templateFile = '';
    }

    /**
     * Capture routing information from the parse_request action.
     */
    public function captureParseRequest(object $wp): void
    {
        if (property_exists($wp, 'matched_rule')) {
            $this->matchedRule = (string) $wp->matched_rule;
        }

        if (property_exists($wp, 'matched_query')) {
            $this->matchedQuery = (string) $wp->matched_query;
        }

        if (property_exists($wp, 'query_vars') && is_array($wp->query_vars)) {
            $this->queryVars = $wp->query_vars;
        }
    }

    /**
     * Capture the resolved template file from the template_include filter.
     */
    public function captureTemplate(string $template): string
    {
        $this->templateFile = $template;

        return $template;
    }

    /**
     * @param array{slug: string, source: string, theme: string, type: string, has_theme_file: bool, file_path: string, id: string, parts: list<array{slug: string, source: string, area: string}>} $default
     * @return array{slug: string, source: string, theme: string, type: string, has_theme_file: bool, file_path: string, id: string, parts: list<array{slug: string, source: string, area: string}>}
     */
    private function collectBlockTemplate(array $default): array
    {
        global $_wp_current_template_id;

        if (!isset($_wp_current_template_id) || !is_string($_wp_current_template_id) || $_wp_current_template_id === '') {
            return $default;
        }

        $template = get_block_template($_wp_current_template_id, 'wp_template');

        if (!is_object($template)) {
            return $default;
        }

        $slug = $template->slug;
        $result = [
            'slug' => $slug,
            'source' => $template->source,
            'theme' => $template->theme,
            'type' => $template->type,
            'has_theme_file' => $template->has_theme_file,
            'file_path' => $this->resolveBlockTemplateFilePath($slug),
            'id' => $template->id,
            'parts' => [],
        ];

        if ($template->content !== '') {
            $result['parts'] = $this->collectBlockTemplateParts($template->content);
        }

        return $result;
    }

    private function resolveBlockTemplateFilePath(string $slug): string
    {
        if ($slug === '') {
            return '';
        }

        $path = get_theme_file_path('templates/' . $slug . '.html');

        return is_file($path) ? $path : '';
    }

    /**
     * @return list<array{slug: string, source: string, area: string}>
     */
    private function collectBlockTemplateParts(string $content): array
    {
        if (!preg_match_all('/<!-- wp:template-part \{[^}]*"slug"\s*:\s*"([^"]+)"[^}]*\} \/-->/', $content, $matches)) {
            return [];
        }

        $parts = [];
        $stylesheet = get_stylesheet();

        foreach ($matches[1] as $partSlug) {
            $partId = $stylesheet !== '' ? $stylesheet . '//' . $partSlug : $partSlug;
            $part = get_block_template($partId, 'wp_template_part');

            if ($part instanceof \WP_Block_Template) {
                $parts[] = [
                    'slug' => $partSlug,
                    'source' => $part->source,
                    'area' => $part->area,
                ];
            } else {
                $parts[] = [
                    'slug' => $partSlug,
                    'source' => '',
                    'area' => '',
                ];
            }
        }

        return $parts;
    }

    private function registerHooks(): void
    {
        add_action('parse_request', [$this, 'captureParseRequest'], PHP_INT_MAX, 1);
        add_filter('template_include', [$this, 'captureTemplate'], PHP_INT_MAX, 1);
    }

    private function getRewriteRulesCount(): int
    {
        global $wp_rewrite;

        if (!isset($wp_rewrite) || !is_object($wp_rewrite)) {
            return 0;
        }

        if (!property_exists($wp_rewrite, 'rules') || !is_array($wp_rewrite->rules)) {
            return 0;
        }

        return count($wp_rewrite->rules);
    }
}
