<?php

/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WpPack\Component\Translation;

use WpPack\Component\Translation\Attribute\PluginTextDomain;
use WpPack\Component\Translation\Attribute\ThemeTextDomain;

class Translator
{
    public readonly string $domain;

    public function __construct(?string $domain = null)
    {
        $this->domain = $domain ?? $this->resolveDomainFromAttribute();
    }

    public function translate(string $text): string
    {
        return __($text, $this->domain);
    }

    public function echo(string $text): void
    {
        _e($text, $this->domain);
    }

    public function plural(string $single, string $plural, int $count): string
    {
        return _n($single, $plural, $count, $this->domain);
    }

    public function translateWithContext(string $text, string $context): string
    {
        return _x($text, $context, $this->domain);
    }

    public function pluralWithContext(string $single, string $plural, int $count, string $context): string
    {
        return _nx($single, $plural, $count, $context, $this->domain);
    }

    public function escHtml(string $text): string
    {
        return esc_html__($text, $this->domain);
    }

    public function escAttr(string $text): string
    {
        return esc_attr__($text, $this->domain);
    }

    private function resolveDomainFromAttribute(): string
    {
        $reflection = new \ReflectionClass($this);

        /** @var list<\ReflectionAttribute<PluginTextDomain>> $pluginAttributes */
        $pluginAttributes = $reflection->getAttributes(PluginTextDomain::class);

        if ($pluginAttributes !== []) {
            return $pluginAttributes[0]->newInstance()->domain;
        }

        /** @var list<\ReflectionAttribute<ThemeTextDomain>> $themeAttributes */
        $themeAttributes = $reflection->getAttributes(ThemeTextDomain::class);

        if ($themeAttributes !== []) {
            return $themeAttributes[0]->newInstance()->domain;
        }

        throw new \LogicException(sprintf(
            'Class "%s" must either pass a domain to the constructor or have a #[PluginTextDomain] or #[ThemeTextDomain] attribute.',
            static::class,
        ));
    }
}
