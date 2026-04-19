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

namespace WPPack\Component\Templating\Bridge\Twig\Extension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use WPPack\Component\Escaper\Escaper;
use WPPack\Component\Sanitizer\Sanitizer;

final class WordPressExtension extends AbstractExtension
{
    public function __construct(
        private readonly Escaper $escaper = new Escaper(),
        private readonly Sanitizer $sanitizer = new Sanitizer(),
    ) {}

    /**
     * @return list<TwigFilter>
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('esc_html', $this->escaper->html(...), ['is_safe' => ['html']]),
            new TwigFilter('esc_attr', $this->escaper->attr(...), ['is_safe' => ['html']]),
            new TwigFilter('esc_url', $this->escaper->url(...), ['is_safe' => ['html']]),
            new TwigFilter('esc_js', $this->escaper->js(...), ['is_safe' => ['html', 'js']]),
            new TwigFilter('esc_textarea', $this->escaper->textarea(...), ['is_safe' => ['html']]),
            new TwigFilter('wp_kses_post', $this->sanitizer->ksesPost(...), ['is_safe' => ['html']]),
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

    private function captureWpHead(): string
    {
        ob_start();
        wp_head();

        return ob_get_clean() ?: '';
    }

    private function captureWpFooter(): string
    {
        ob_start();
        wp_footer();

        return ob_get_clean() ?: '';
    }

    private function captureBodyClass(): string
    {
        ob_start();
        body_class();

        return ob_get_clean() ?: '';
    }

    private function captureLanguageAttributes(): string
    {
        ob_start();
        language_attributes();

        return ob_get_clean() ?: '';
    }
}
