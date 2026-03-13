<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\ErrorHandler;

use WpPack\Component\Debug\CssTheme;

final readonly class ErrorRenderer
{
    public function render(FlattenException $exception, string $toolbarHtml = ''): string
    {
        $class = $this->escape($exception->getClass());
        $message = $this->escape($exception->getMessage());
        $file = $this->escape($exception->getFile());
        $line = $exception->getLine();
        $statusCode = $exception->getStatusCode();
        $code = $exception->getCode();

        $traceHtml = $this->renderTrace($exception->getTrace());
        $chainHtml = $this->renderChain($exception->getChain());

        $shortClass = $this->escape($this->shortClassName($exception->getClass()));
        $codeLabel = $code !== 0 ? ' <span class="exception-code">(code ' . $code . ')</span>' : '';

        $cssVariables = CssTheme::cssVariables();

        $chainSectionHtml = '';
        $chainCount = count($exception->getChain());
        if ($chainCount > 1) {
            $chainSectionHtml = <<<HTML
            <!-- ═══ Previous Exceptions ═══ -->
            <div class="section">
              <div class="section-title">Previous Exceptions ({$this->escape((string) ($chainCount - 1))})</div>
              {$chainHtml}
            </div>
            HTML;
        }

        return <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{$shortClass} - WpPack Debug</title>
        <style>
        /* ── Reset ─────────────────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        /* ── Variables / Base ──────────────────────────────────── */
        :root {
        {$cssVariables}
        }

        html { font-size: 13px; }
        body {
            font-family: var(--wpd-font-sans);
            background: var(--wpd-white);
            color: var(--wpd-gray-900);
            line-height: 1.5;
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* ── Header ────────────────────────────────────────────── */
        .header {
            background: var(--wpd-red-a10);
            border-bottom: 1px solid var(--wpd-red-a12);
            padding: 16px;
        }
        .header-inner { max-width: 1400px; margin: 0 auto; }
        .exception-class {
            font-size: 20px;
            font-weight: 600;
            color: var(--wpd-red);
            word-break: break-all;
        }
        .exception-code {
            font-size: 11px;
            color: var(--wpd-gray-500);
            font-weight: 400;
        }
        .exception-message {
            font-size: 12px;
            color: var(--wpd-gray-900);
            margin-top: 4px;
            line-height: 1.3;
            word-break: break-word;
        }

        /* ── Container ─────────────────────────────────────────── */
        .container { max-width: 1400px; margin: 0 auto; padding: 0 0 50px; }

        /* ── Section ───────────────────────────────────────────── */
        .section { padding: 20px 16px; }
        .section + .section { border-top: 1px solid var(--wpd-gray-200); }
        .section-title {
            font-size: 11px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--wpd-gray-900);
            margin-bottom: 14px;
        }

        /* ── Code Table (shared by trace bodies) ──────────────── */
        .code-table {
            width: 100%;
            border-collapse: collapse;
            font-family: var(--wpd-font-mono);
            font-size: 12px;
            line-height: 1.6;
            tab-size: 4;
        }
        .code-table td { padding: 0; white-space: pre; vertical-align: top; }
        .code-table .line-number {
            width: 56px;
            min-width: 56px;
            text-align: right;
            padding-right: 12px;
            color: var(--wpd-gray-400);
            user-select: none;
            -webkit-user-select: none;
            border-right: 1px solid var(--wpd-gray-200);
        }
        .code-table .line-code {
            padding-left: 12px;
            padding-right: 12px;
            overflow-x: auto;
        }
        .code-table tr.highlight {
            background: var(--wpd-red-a8);
        }
        .code-table tr.highlight .line-number {
            color: var(--wpd-red);
            font-weight: 700;
        }

        /* ── Stack Trace ───────────────────────────────────────── */
        .trace-list { list-style: none; }
        .trace-frame {
            background: var(--wpd-white);
            border: 1px solid var(--wpd-gray-200);
            border-radius: var(--wpd-radius);
            margin-bottom: 8px;
            overflow: hidden;
        }
        .trace-header {
            display: flex;
            align-items: baseline;
            gap: 8px;
            padding: 8px 12px;
            cursor: pointer;
            user-select: none;
            -webkit-user-select: none;
            transition: background .15s;
            flex-wrap: wrap;
        }
        .trace-header:hover { background: var(--wpd-white); }
        .trace-frame.open .trace-header { border-bottom: 1px solid var(--wpd-gray-200); }
        .trace-index {
            font-family: var(--wpd-font-mono);
            font-size: 11px;
            color: var(--wpd-gray-400);
            min-width: 24px;
            text-align: right;
            flex-shrink: 0;
        }
        .trace-function {
            font-family: var(--wpd-font-mono);
            font-size: 12px;
            color: var(--wpd-gray-900);
            flex: 1;
            word-break: break-all;
        }
        .trace-function .class-name { color: var(--wpd-primary); }
        .trace-function .method-name { color: var(--wpd-green); }
        .trace-function .type-sep { color: var(--wpd-gray-400); }
        .trace-function .args-list { color: var(--wpd-gray-500); font-size: 11px; }
        .trace-location {
            font-family: var(--wpd-font-mono);
            font-size: 11px;
            color: var(--wpd-gray-400);
            flex-shrink: 0;
        }
        .trace-location .loc-file { color: var(--wpd-gray-500); }
        .trace-location .loc-line { color: var(--wpd-gray-900); font-weight: 600; }
        .trace-toggle { flex-shrink: 0; }
        .wpd-log-indicator {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 14px;
            height: 14px;
            font-size: 11px;
            font-weight: 600;
            color: var(--wpd-gray-400);
            border: 1px solid var(--wpd-gray-300);
            border-radius: 3px;
        }
        .trace-header:hover .wpd-log-indicator { color: var(--wpd-primary); border-color: var(--wpd-primary); }
        .trace-body {
            display: none;
            background: var(--wpd-gray-50);
        }
        .trace-frame.open .trace-body { display: block; }

        /* ── Chain / Previous Exceptions ───────────────────────── */
        .chain-item {
            margin-bottom: 20px;
        }
        .chain-item:last-child { margin-bottom: 0; }
        .chain-item-class {
            font-size: 18px;
            color: var(--wpd-red);
            font-weight: 600;
        }
        .chain-item-message {
            color: var(--wpd-gray-900);
            font-size: 12px;
            margin-top: 4px;
            word-break: break-word;
        }
        .chain-item-trace { margin-top: 12px; }

        /* ── Empty state ───────────────────────────────────────── */
        .empty-state {
            color: var(--wpd-gray-400);
            font-style: italic;
            padding: 8px 0;
        }

        /* ── Scrollbar ─────────────────────────────────────────── */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: var(--wpd-white); }
        ::-webkit-scrollbar-thumb { background: var(--wpd-gray-300); border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--wpd-gray-400); }

        /* ── Responsive ────────────────────────────────────────── */
        @media (max-width: 768px) {
            .header { padding: 12px; }
            .section { padding-left: 12px; padding-right: 12px; }
            .exception-class { font-size: 15px; }
            .exception-message { font-size: 13px; }
            .trace-header { flex-direction: column; gap: 2px; }
        }
        </style>
        </head>
        <body>
        <!-- ═══ Header ═══ -->
        <div class="header">
          <div class="header-inner">
            <div class="exception-class">{$class}{$codeLabel}</div>
            <div class="exception-message">{$message}</div>
          </div>
        </div>

        <div class="container">

        <!-- ═══ Stack Trace ═══ -->
        <div class="section">
          <div class="section-title">Stack Trace</div>
          {$traceHtml}
        </div>

        {$chainSectionHtml}

        </div><!-- .container -->

        {$toolbarHtml}

        <script>
        /* ── Accordion toggle ── */
        document.querySelectorAll('.trace-header').forEach(function(header) {
            header.addEventListener('click', function() {
                var frame = this.closest('.trace-frame');
                if (!frame) return;
                var opening = !frame.classList.contains('open');
                frame.classList.toggle('open');
                var indicator = frame.querySelector('.wpd-log-indicator');
                if (indicator) indicator.textContent = opening ? '\u2212' : '+';
            });
        });
        </script>
        </body>
        </html>
        HTML;
    }

    /**
     * @param list<array<string, mixed>> $trace
     */
    private function renderTrace(array $trace, bool $openFirst = true): string
    {
        if ($trace === []) {
            return '<p class="empty-state">No stack trace available.</p>';
        }

        $html = '<ul class="trace-list">';

        foreach ($trace as $index => $frame) {
            $file = $frame['file'];
            $line = $frame['line'];
            $class = $frame['class'];
            $type = $frame['type'];
            $function = $frame['function'];
            $args = $frame['args'];
            $codeContext = $frame['code_context'];
            $highlightLine = $frame['highlight_line'];

            // Build function display
            $funcHtml = '';
            if ($function === '') {
                // Throw location frame — show file path as label
                if ($file !== '') {
                    $funcHtml = '<span class="loc-file">' . $this->escape($this->shortenPath($file)) . '</span>';
                    if ($line > 0) {
                        $funcHtml .= ':<span class="loc-line">' . $line . '</span>';
                    }
                }
            } else {
                if ($class !== '') {
                    $funcHtml .= '<span class="class-name">' . $this->escape($this->shortClassName($class)) . '</span>';
                    $funcHtml .= '<span class="type-sep">' . $this->escape($type) . '</span>';
                }
                $funcHtml .= '<span class="method-name">' . $this->escape($function) . '</span>';
                if ($args !== []) {
                    $funcHtml .= '<span class="args-list">(' . $this->escape(implode(', ', $args)) . ')</span>';
                } else {
                    $funcHtml .= '<span class="args-list">()</span>';
                }
            }

            // Location display (skip for throw frames — already shown in function area)
            $locHtml = '';
            if ($function !== '' && $file !== '') {
                $shortFile = $this->shortenPath($file);
                $locHtml = '<span class="loc-file">' . $this->escape($shortFile) . '</span>';
                if ($line > 0) {
                    $locHtml .= ':<span class="loc-line">' . $line . '</span>';
                }
            }

            // Code context body
            $bodyHtml = '';
            if ($codeContext !== [] && $highlightLine > 0) {
                $startLine = max(1, $highlightLine - (int) floor(count($codeContext) / 2));
                // Recalculate based on actual context range
                if ($file !== '' && $line > 0) {
                    $startLine = max(1, $line - 10);
                }
                $rows = '';
                foreach ($codeContext as $ci => $codeLine) {
                    $currentLineNum = $startLine + $ci;
                    $hl = $currentLineNum === $highlightLine ? ' class="highlight"' : '';
                    $rows .= "<tr{$hl}>"
                        . '<td class="line-number">' . $currentLineNum . '</td>'
                        . '<td class="line-code">' . $this->escape($codeLine) . '</td>'
                        . '</tr>';
                }
                $bodyHtml = '<div style="overflow-x:auto"><table class="code-table"><tbody>'
                    . $rows . '</tbody></table></div>';
            }

            // Frame #0 opens by default (error origin)
            $isOpen = $openFirst && $index === 0;
            $openClass = $isOpen ? ' open' : '';
            $indicator = $isOpen ? "\u{2212}" : '+';

            $html .= '<li class="trace-frame' . $openClass . '">'
                . '<div class="trace-header">'
                . '<span class="trace-index">#' . $index . '</span>'
                . '<span class="trace-function">' . $funcHtml . '</span>'
                . '<span class="trace-location">' . $locHtml . '</span>'
                . '<span class="trace-toggle"><span class="wpd-log-indicator">' . $indicator . '</span></span>'
                . '</div>';

            if ($bodyHtml !== '') {
                $html .= '<div class="trace-body">' . $bodyHtml . '</div>';
            }

            $html .= '</li>';
        }

        $html .= '</ul>';

        return $html;
    }

    /**
     * @param list<array<string, mixed>> $chain
     */
    private function renderChain(array $chain): string
    {
        // Skip the first entry (it's the main exception)
        if (count($chain) <= 1) {
            return '';
        }

        $html = '';
        for ($i = 1, $count = count($chain); $i < $count; $i++) {
            $item = $chain[$i];
            $html .= '<div class="chain-item">'
                . '<div class="chain-item-class">' . $this->escape($item['class']) . '</div>'
                . '<div class="chain-item-message">' . $this->escape($item['message']) . '</div>';

            if (isset($item['trace']) && $item['trace'] !== []) {
                $html .= '<div class="chain-item-trace">'
                    . $this->renderTrace($item['trace'], false)
                    . '</div>';
            }

            $html .= '</div>';
        }

        return $html;
    }

    private function shortClassName(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos !== false ? substr($fqcn, $pos + 1) : $fqcn;
    }

    private function shortenPath(string $path): string
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

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
