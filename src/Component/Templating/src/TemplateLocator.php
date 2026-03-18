<?php

declare(strict_types=1);

namespace WpPack\Component\Templating;

/**
 * Locates template files in registered paths and WordPress theme directories.
 *
 * Engine-agnostic: can be reused by PhpRenderer, TwigRenderer, etc.
 */
final class TemplateLocator
{
    /** @param list<string> $paths */
    public function __construct(
        private array $paths = [],
    ) {}

    /**
     * Locate a template file.
     *
     * Search order:
     * 1. WordPress locate_template()
     * 2. Custom paths in registration order
     *
     * @param string $template Template name (e.g., 'partials/card')
     * @param string $variant  Optional variant suffix (e.g., 'sidebar' → partials/card-sidebar.php)
     *
     * @return string|null Absolute file path, or null if not found
     */
    public function locate(string $template, string $variant = ''): ?string
    {
        $candidates = $this->buildCandidates($template, $variant);

        // 1. WordPress theme template lookup
        $found = locate_template($candidates);
        if ($found !== '') {
            return $found;
        }

        // 2. Custom paths
        foreach ($this->paths as $basePath) {
            foreach ($candidates as $candidate) {
                $file = $basePath . '/' . $candidate;
                if (is_file($file)) {
                    return $file;
                }
            }
        }

        return null;
    }

    public function addPath(string $path): void
    {
        $this->paths[] = $path;
    }

    /**
     * @return list<string>
     */
    public function getPaths(): array
    {
        return $this->paths;
    }

    /**
     * Build candidate file names for the given template and variant.
     *
     * @return list<string>
     */
    private function buildCandidates(string $template, string $variant): array
    {
        $candidates = [];

        if ($variant !== '') {
            $candidates[] = $template . '-' . $variant . '.php';
        }

        $candidates[] = $template . '.php';

        return $candidates;
    }
}
