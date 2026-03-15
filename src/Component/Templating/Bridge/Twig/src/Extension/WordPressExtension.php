<?php

declare(strict_types=1);

namespace WpPack\Component\Templating\Bridge\Twig\Extension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use WpPack\Component\Escaper\Escaper;

final class WordPressExtension extends AbstractExtension
{
    public function __construct(
        private readonly ?Escaper $escaper = null,
    ) {}

    /**
     * @return list<TwigFilter>
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('esc_html', $this->escHtml(...), ['is_safe' => ['html']]),
            new TwigFilter('esc_attr', $this->escAttr(...), ['is_safe' => ['html']]),
            new TwigFilter('esc_url', $this->escUrl(...), ['is_safe' => ['html']]),
            new TwigFilter('esc_js', $this->escJs(...), ['is_safe' => ['html', 'js']]),
            new TwigFilter('wp_kses_post', $this->wpKsesPost(...), ['is_safe' => ['html']]),
        ];
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('wp_head', $this->captureWpHead(...), ['is_safe' => ['html']]),
            new TwigFunction('wp_footer', $this->captureWpFooter(...), ['is_safe' => ['html']]),
            new TwigFunction('body_class', $this->captureBodyClass(...), ['is_safe' => ['html']]),
            new TwigFunction('language_attributes', $this->captureLanguageAttributes(...), ['is_safe' => ['html']]),
        ];
    }

    public function escHtml(string $value): string
    {
        if ($this->escaper !== null) {
            return $this->escaper->html($value);
        }

        if (function_exists('esc_html')) {
            return esc_html($value);
        }

        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function escAttr(string $value): string
    {
        if ($this->escaper !== null) {
            return $this->escaper->attr($value);
        }

        if (function_exists('esc_attr')) {
            return esc_attr($value);
        }

        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function escUrl(string $value): string
    {
        if ($this->escaper !== null) {
            return $this->escaper->url($value);
        }

        if (function_exists('esc_url')) {
            return esc_url($value);
        }

        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function escJs(string $value): string
    {
        if ($this->escaper !== null) {
            return $this->escaper->js($value);
        }

        if (function_exists('esc_js')) {
            return esc_js($value);
        }

        return str_replace(
            ["\\", '"', "'", "\n", "\r", '</'],
            ["\\\\", '\\"', "\\'", "\\n", "\\r", '<\\/'],
            $value,
        );
    }

    public function wpKsesPost(string $value): string
    {
        if (function_exists('wp_kses_post')) {
            return wp_kses_post($value);
        }

        return $value;
    }

    private function captureWpHead(): string
    {
        if (!function_exists('wp_head')) {
            return '';
        }

        ob_start();
        wp_head();

        return ob_get_clean() ?: '';
    }

    private function captureWpFooter(): string
    {
        if (!function_exists('wp_footer')) {
            return '';
        }

        ob_start();
        wp_footer();

        return ob_get_clean() ?: '';
    }

    private function captureBodyClass(): string
    {
        if (!function_exists('body_class')) {
            return '';
        }

        ob_start();
        body_class();

        return ob_get_clean() ?: '';
    }

    private function captureLanguageAttributes(): string
    {
        if (!function_exists('language_attributes')) {
            return '';
        }

        ob_start();
        language_attributes();

        return ob_get_clean() ?: '';
    }
}
