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

namespace WpPack\Component\Templating\Bridge\Twig;

use Twig\Environment;
use Twig\Extension\ExtensionInterface;
use Twig\Loader\FilesystemLoader;

final class TwigEnvironmentFactory
{
    private const DEFAULT_OPTIONS = [
        'autoescape' => 'html',
        'strict_variables' => true,
    ];

    /**
     * @param list<string>              $paths
     * @param array<string, mixed>      $options
     * @param list<ExtensionInterface>  $extensions
     */
    public function __construct(
        private readonly array $paths = [],
        private readonly array $options = [],
        private readonly array $extensions = [],
    ) {}

    public function create(): Environment
    {
        $loaderPaths = $this->buildPaths();
        $loader = new FilesystemLoader($loaderPaths);

        $options = array_merge(self::DEFAULT_OPTIONS, $this->options);
        $environment = new Environment($loader, $options);

        foreach ($this->extensions as $extension) {
            $environment->addExtension($extension);
        }

        return $environment;
    }

    /**
     * @return list<string>
     */
    private function buildPaths(): array
    {
        $paths = [];

        $childThemeDir = get_stylesheet_directory();
        if (is_dir($childThemeDir)) {
            $paths[] = $childThemeDir;
        }

        $parentThemeDir = get_template_directory();
        if (is_dir($parentThemeDir) && !\in_array($parentThemeDir, $paths, true)) {
            $paths[] = $parentThemeDir;
        }

        foreach ($this->paths as $path) {
            if (!\in_array($path, $paths, true)) {
                $paths[] = $path;
            }
        }

        return $paths;
    }
}
