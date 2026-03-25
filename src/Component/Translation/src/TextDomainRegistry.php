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

final class TextDomainRegistry
{
    /** @var array<string, PluginTextDomain|ThemeTextDomain> */
    private array $domains = [];

    public function register(object $object): void
    {
        $reflection = new \ReflectionClass($object);

        /** @var list<\ReflectionAttribute<PluginTextDomain>> $pluginAttributes */
        $pluginAttributes = $reflection->getAttributes(PluginTextDomain::class);

        if ($pluginAttributes !== []) {
            $attribute = $pluginAttributes[0]->newInstance();
            $this->domains[$attribute->domain] = $attribute;
            self::loadPlugin($attribute->domain, $attribute->path);

            return;
        }

        /** @var list<\ReflectionAttribute<ThemeTextDomain>> $themeAttributes */
        $themeAttributes = $reflection->getAttributes(ThemeTextDomain::class);

        if ($themeAttributes !== []) {
            $attribute = $themeAttributes[0]->newInstance();
            $this->domains[$attribute->domain] = $attribute;
            self::loadTheme($attribute->domain, $attribute->path);

            return;
        }

        throw new \LogicException(sprintf(
            'Class "%s" must have a #[PluginTextDomain] or #[ThemeTextDomain] attribute.',
            $object::class,
        ));
    }

    public function has(string $domain): bool
    {
        return isset($this->domains[$domain]);
    }

    /**
     * @return array<string, PluginTextDomain|ThemeTextDomain>
     */
    public function all(): array
    {
        return $this->domains;
    }

    public static function loadPlugin(string $domain, string $path = ''): void
    {
        $resolvedPath = $path !== '' ? $path : $domain . '/languages';
        load_plugin_textdomain($domain, false, $resolvedPath);
    }

    public static function loadTheme(string $domain, string $path = 'languages'): void
    {
        load_theme_textdomain($domain, get_template_directory() . '/' . $path);
    }
}
