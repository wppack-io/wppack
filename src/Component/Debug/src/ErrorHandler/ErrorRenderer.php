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

namespace WPPack\Component\Debug\ErrorHandler;

use WPPack\Component\Debug\CssTheme;
use WPPack\Component\Templating\PhpRenderer;

final class ErrorRenderer
{
    private ?PhpRenderer $lazyPhpRenderer = null;

    public function __construct(
        private readonly ?PhpRenderer $phpRenderer = null,
    ) {}

    public function getPhpRenderer(): PhpRenderer
    {
        if ($this->phpRenderer !== null) {
            return $this->phpRenderer;
        }

        return $this->lazyPhpRenderer ??= new PhpRenderer([
            dirname(__DIR__, 2) . '/templates',
        ]);
    }

    public function render(FlattenException $exception, string $toolbarHtml = ''): string
    {
        $code = $exception->getCode();

        $traceHtml = $this->getPhpRenderer()->render('error/trace', [
            'trace' => $exception->getTrace(),
            'openFirst' => true,
            'renderer' => $this,
        ]);

        $chainHtml = $this->getPhpRenderer()->render('error/chain', [
            'chain' => $exception->getChain(),
            'renderer' => $this,
        ]);

        $chainCount = count($exception->getChain());
        $plainText = $exception->toPlainText($this->shortenPath(...));

        return $this->getPhpRenderer()->render('error/page', [
            'shortClass' => $this->shortClassName($exception->getClass()),
            'class' => $exception->getClass(),
            'message' => $exception->getMessage(),
            'codeLabel' => $code !== 0 ? ' <span class="exception-code">(code ' . $code . ')</span>' : '',
            'cssVariables' => CssTheme::cssVariables(),
            'traceHtml' => $traceHtml,
            'chainHtml' => $chainHtml,
            'chainCount' => $chainCount,
            'plainText' => $plainText,
            'toolbarHtml' => $toolbarHtml,
        ]);
    }

    public function shortClassName(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos !== false ? substr($fqcn, $pos + 1) : $fqcn;
    }

    public function shortenPath(string $path): string
    {
        $basePath = $this->resolveBasePath();
        if ($basePath !== '' && str_starts_with($path, $basePath)) {
            return substr($path, \strlen($basePath));
        }

        // Try to shorten vendor paths
        $vendorPos = strpos($path, '/vendor/');
        if ($vendorPos !== false) {
            return '...' . substr($path, $vendorPos);
        }

        return $path;
    }

    private function resolveBasePath(): string
    {
        if (!\defined('ABSPATH')) {
            return '';
        }

        // When WP core lives in a subdirectory (home_url ≠ site_url),
        // use the document root (where home_url points) as the base path.
        $homeUrl = home_url();
        $siteUrl = site_url();

        if ($homeUrl !== $siteUrl) {
            $rawHomePath = parse_url($homeUrl, \PHP_URL_PATH);
            $homePath = \is_string($rawHomePath) ? rtrim($rawHomePath, '/') : '';

            $rawSitePath = parse_url($siteUrl, \PHP_URL_PATH);
            $sitePath = \is_string($rawSitePath) ? rtrim($rawSitePath, '/') : '';

            if ($sitePath !== $homePath && str_starts_with($sitePath, $homePath)) {
                $suffix = ltrim(substr($sitePath, \strlen($homePath)), '/') . '/';

                if (str_ends_with(ABSPATH, $suffix)) {
                    return substr(ABSPATH, 0, -\strlen($suffix));
                }
            }
        }

        return ABSPATH;
    }

    public function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
