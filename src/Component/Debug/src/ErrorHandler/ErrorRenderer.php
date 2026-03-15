<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\ErrorHandler;

use WpPack\Component\Debug\Compat\EscapeFunctions;
use WpPack\Component\Debug\CssTheme;
use WpPack\Component\Templating\PhpRenderer;

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

        EscapeFunctions::ensure();

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

        $chainSectionHtml = '';
        $chainCount = count($exception->getChain());
        if ($chainCount > 1) {
            $chainSectionHtml = '<!-- ═══ Previous Exceptions ═══ -->'
                . '<div class="section">'
                . '<div class="section-title">Previous Exceptions (' . $this->escape((string) ($chainCount - 1)) . ')</div>'
                . $chainHtml
                . '</div>';
        }

        return $this->getPhpRenderer()->render('error/page', [
            'shortClass' => $this->shortClassName($exception->getClass()),
            'class' => $exception->getClass(),
            'message' => $exception->getMessage(),
            'codeLabel' => $code !== 0 ? ' <span class="exception-code">(code ' . $code . ')</span>' : '',
            'cssVariables' => CssTheme::cssVariables(),
            'traceHtml' => $traceHtml,
            'chainSectionHtml' => $chainSectionHtml,
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
        if (defined('ABSPATH') && str_starts_with($path, ABSPATH)) {
            return substr($path, strlen(ABSPATH));
        }

        // Try to shorten vendor paths
        $vendorPos = strpos($path, '/vendor/');
        if ($vendorPos !== false) {
            return '...' . substr($path, $vendorPos);
        }

        return $path;
    }

    public function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
