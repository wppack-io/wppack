<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\ErrorHandler;

final readonly class ErrorRenderer
{
    public function render(FlattenException $exception): string
    {
        $class = $this->escape($exception->getClass());
        $message = $this->escape($exception->getMessage());
        $file = $this->escape($exception->getFile());
        $line = $exception->getLine();
        $statusCode = $exception->getStatusCode();
        $code = $exception->getCode();

        $traceHtml = $this->renderTrace($exception->getTrace(), $exception->getFile(), $exception->getLine());
        $chainHtml = $this->renderChain($exception->getChain());
        $requestHtml = $this->renderRequestTab();
        $environmentHtml = $this->renderEnvironmentTab();
        $performanceHtml = $this->renderPerformanceTab();

        $codeSnippetHtml = $this->renderCodeSnippet(
            $exception->getFile(),
            $exception->getLine(),
            10,
        );

        $shortClass = $this->escape($this->shortClassName($exception->getClass()));
        $codeLabel = $code !== 0 ? ' <span class="exception-code">(code ' . $code . ')</span>' : '';
        $chainCount = count($exception->getChain());
        $chainTabHtml = $chainCount > 1
            ? '<button class="tab-btn" data-tab="chain">Previous (' . ($chainCount - 1) . ')</button>'
            : '';

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
            --bg:          #1e1e2e;
            --bg-surface:  #181825;
            --bg-overlay:  #11111b;
            --bg-mantle:   #1a1a2e;
            --border:      #313244;
            --border-hl:   #45475a;
            --text:        #cdd6f4;
            --text-dim:    #a6adc8;
            --text-muted:  #6c7086;
            --accent:      #89b4fa;
            --accent-dim:  #74c7ec;
            --red:         #f38ba8;
            --red-dim:     #f38ba833;
            --green:       #a6e3a1;
            --yellow:      #f9e2af;
            --peach:       #fab387;
            --mauve:       #cba6f7;
            --pink:        #f5c2e7;
            --teal:        #94e2d5;
            --font-mono:   "JetBrains Mono", "Fira Code", "Source Code Pro", "Cascadia Code", "Consolas", monospace;
            --font-sans:   system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            --radius:      8px;
            --radius-sm:   4px;
            --shadow:      0 4px 24px rgba(0,0,0,.4);
        }

        html { font-size: 15px; }
        body {
            font-family: var(--font-sans);
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* ── Header ────────────────────────────────────────────── */
        .header {
            background: linear-gradient(135deg, #45274a 0%, #2d1832 50%, #1e1e2e 100%);
            border-bottom: 3px solid var(--red);
            padding: 2rem 2rem 1.6rem;
        }
        .header-inner { max-width: 1400px; margin: 0 auto; }
        .status-badge {
            display: inline-block;
            background: var(--red);
            color: var(--bg-overlay);
            font-weight: 700;
            font-size: .85rem;
            padding: .2rem .7rem;
            border-radius: var(--radius-sm);
            margin-bottom: .8rem;
            letter-spacing: .03em;
        }
        .exception-class {
            font-family: var(--font-mono);
            font-size: 1.5rem;
            font-weight: 700;
            color: #fff;
            word-break: break-all;
            margin-bottom: .3rem;
        }
        .exception-code {
            font-size: .85rem;
            color: var(--text-muted);
            font-weight: 400;
        }
        .exception-message {
            font-size: 1.15rem;
            color: var(--peach);
            margin: .5rem 0;
            line-height: 1.5;
            word-break: break-word;
        }
        .exception-location {
            font-family: var(--font-mono);
            font-size: .85rem;
            color: var(--text-dim);
            margin-top: .6rem;
        }
        .exception-location .file-path { color: var(--accent); }
        .exception-location .line-num { color: var(--yellow); }

        /* ── Container ─────────────────────────────────────────── */
        .container { max-width: 1400px; margin: 0 auto; padding: 1.5rem 2rem 3rem; }

        /* ── Code Snippet ──────────────────────────────────────── */
        .code-snippet {
            background: var(--bg-overlay);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow);
        }
        .code-snippet-title {
            background: var(--bg-mantle);
            padding: .6rem 1rem;
            font-family: var(--font-mono);
            font-size: .8rem;
            color: var(--text-dim);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: .5rem;
        }
        .code-table {
            width: 100%;
            border-collapse: collapse;
            font-family: var(--font-mono);
            font-size: .82rem;
            line-height: 1.65;
            tab-size: 4;
        }
        .code-table td { padding: 0; white-space: pre; vertical-align: top; }
        .code-table .line-number {
            width: 60px;
            min-width: 60px;
            text-align: right;
            padding-right: 16px;
            color: var(--text-muted);
            user-select: none;
            -webkit-user-select: none;
            border-right: 1px solid var(--border);
        }
        .code-table .line-code {
            padding-left: 16px;
            padding-right: 16px;
            overflow-x: auto;
        }
        .code-table tr.highlight {
            background: var(--red-dim);
        }
        .code-table tr.highlight .line-number {
            color: var(--red);
            font-weight: 700;
        }

        /* ── Tabs ──────────────────────────────────────────────── */
        .tabs { margin-bottom: 1.5rem; }
        .tab-bar {
            display: flex;
            gap: 2px;
            border-bottom: 2px solid var(--border);
            margin-bottom: 0;
            flex-wrap: wrap;
        }
        .tab-btn {
            background: transparent;
            border: none;
            color: var(--text-muted);
            font-family: var(--font-sans);
            font-size: .88rem;
            font-weight: 500;
            padding: .7rem 1.2rem;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            transition: color .15s, border-color .15s;
        }
        .tab-btn:hover { color: var(--text); }
        .tab-btn.active {
            color: var(--accent);
            border-bottom-color: var(--accent);
        }
        .tab-panel {
            display: none;
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-top: none;
            border-radius: 0 0 var(--radius) var(--radius);
            padding: 1rem;
        }
        .tab-panel.active { display: block; }

        /* ── Stack Trace ───────────────────────────────────────── */
        .trace-list { list-style: none; }
        .trace-frame {
            border: 1px solid var(--border);
            border-radius: var(--radius);
            margin-bottom: .5rem;
            overflow: hidden;
            transition: border-color .15s;
        }
        .trace-frame:hover { border-color: var(--border-hl); }
        .trace-header {
            display: flex;
            align-items: baseline;
            gap: .8rem;
            padding: .65rem 1rem;
            cursor: pointer;
            user-select: none;
            -webkit-user-select: none;
            background: var(--bg-mantle);
            transition: background .15s;
            flex-wrap: wrap;
        }
        .trace-header:hover { background: var(--bg-overlay); }
        .trace-index {
            font-family: var(--font-mono);
            font-size: .75rem;
            color: var(--text-muted);
            min-width: 28px;
            text-align: right;
            flex-shrink: 0;
        }
        .trace-function {
            font-family: var(--font-mono);
            font-size: .82rem;
            color: var(--text);
            flex: 1;
            word-break: break-all;
        }
        .trace-function .class-name { color: var(--accent); }
        .trace-function .method-name { color: var(--green); }
        .trace-function .type-sep { color: var(--text-muted); }
        .trace-function .args-list { color: var(--text-dim); font-size: .78rem; }
        .trace-location {
            font-family: var(--font-mono);
            font-size: .78rem;
            color: var(--text-muted);
            flex-shrink: 0;
        }
        .trace-location .loc-file { color: var(--text-dim); }
        .trace-location .loc-line { color: var(--yellow); }
        .trace-chevron {
            color: var(--text-muted);
            font-size: .7rem;
            flex-shrink: 0;
            transition: transform .2s;
        }
        .trace-frame.open .trace-chevron { transform: rotate(90deg); }
        .trace-body {
            display: none;
            border-top: 1px solid var(--border);
        }
        .trace-frame.open .trace-body { display: block; }

        /* ── Chain / Previous Exceptions ───────────────────────── */
        .chain-item {
            background: var(--bg-overlay);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1rem 1.2rem;
            margin-bottom: .8rem;
        }
        .chain-item-class {
            font-family: var(--font-mono);
            font-size: .95rem;
            color: var(--accent);
            font-weight: 600;
            margin-bottom: .3rem;
        }
        .chain-item-message {
            color: var(--peach);
            font-size: .9rem;
            margin-bottom: .3rem;
            word-break: break-word;
        }
        .chain-item-location {
            font-family: var(--font-mono);
            font-size: .78rem;
            color: var(--text-muted);
        }

        /* ── Info Tables ───────────────────────────────────────── */
        .info-section { margin-bottom: 1.2rem; }
        .info-section-title {
            font-size: .82rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--accent);
            margin-bottom: .5rem;
            padding-bottom: .3rem;
            border-bottom: 1px solid var(--border);
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            font-size: .82rem;
        }
        .info-table th, .info-table td {
            padding: .4rem .6rem;
            border-bottom: 1px solid var(--border);
            text-align: left;
            vertical-align: top;
        }
        .info-table th {
            font-family: var(--font-mono);
            color: var(--teal);
            font-weight: 500;
            width: 260px;
            white-space: nowrap;
        }
        .info-table td {
            font-family: var(--font-mono);
            color: var(--text-dim);
            word-break: break-all;
        }
        .info-table tr:last-child th,
        .info-table tr:last-child td { border-bottom: none; }

        /* ── Performance Bar ───────────────────────────────────── */
        .perf-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.2rem;
        }
        .perf-card {
            background: var(--bg-overlay);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1rem 1.2rem;
            text-align: center;
        }
        .perf-value {
            font-family: var(--font-mono);
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--accent);
        }
        .perf-label {
            font-size: .78rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: .05em;
            margin-top: .2rem;
        }

        /* ── Footer ────────────────────────────────────────────── */
        .footer {
            text-align: center;
            padding: 1rem;
            font-size: .75rem;
            color: var(--text-muted);
            border-top: 1px solid var(--border);
        }
        .footer a { color: var(--accent); text-decoration: none; }
        .footer a:hover { text-decoration: underline; }

        /* ── Empty state ───────────────────────────────────────── */
        .empty-state {
            color: var(--text-muted);
            font-style: italic;
            padding: .8rem 0;
        }

        /* ── Scrollbar ─────────────────────────────────────────── */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: var(--bg-overlay); }
        ::-webkit-scrollbar-thumb { background: var(--border-hl); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--text-muted); }

        /* ── Responsive ────────────────────────────────────────── */
        @media (max-width: 768px) {
            .header { padding: 1.2rem 1rem; }
            .container { padding: 1rem; }
            .exception-class { font-size: 1.1rem; }
            .exception-message { font-size: 1rem; }
            .trace-header { flex-direction: column; gap: .3rem; }
            .info-table th { width: auto; white-space: normal; }
            .perf-grid { grid-template-columns: 1fr 1fr; }
        }
        </style>
        </head>
        <body>
        <!-- ═══ Header ═══ -->
        <div class="header">
          <div class="header-inner">
            <div class="status-badge">HTTP {$statusCode}</div>
            <div class="exception-class">{$class}{$codeLabel}</div>
            <div class="exception-message">{$message}</div>
            <div class="exception-location">
              <span class="file-path">{$file}</span> line <span class="line-num">{$line}</span>
            </div>
          </div>
        </div>

        <div class="container">

        <!-- ═══ Code Snippet ═══ -->
        {$codeSnippetHtml}

        <!-- ═══ Tabs ═══ -->
        <div class="tabs">
          <div class="tab-bar">
            <button class="tab-btn active" data-tab="trace">Stack Trace</button>
            {$chainTabHtml}
            <button class="tab-btn" data-tab="request">Request</button>
            <button class="tab-btn" data-tab="environment">Environment</button>
            <button class="tab-btn" data-tab="performance">Performance</button>
          </div>

          <!-- ── Stack Trace Tab ── -->
          <div class="tab-panel active" id="tab-trace">
            {$traceHtml}
          </div>

          <!-- ── Previous Exceptions Tab ── -->
          <div class="tab-panel" id="tab-chain">
            {$chainHtml}
          </div>

          <!-- ── Request Tab ── -->
          <div class="tab-panel" id="tab-request">
            {$requestHtml}
          </div>

          <!-- ── Environment Tab ── -->
          <div class="tab-panel" id="tab-environment">
            {$environmentHtml}
          </div>

          <!-- ── Performance Tab ── -->
          <div class="tab-panel" id="tab-performance">
            {$performanceHtml}
          </div>
        </div><!-- .tabs -->

        </div><!-- .container -->

        <div class="footer">
          WpPack Debug &middot; PHP {$this->escape(PHP_VERSION)} &middot; {$this->escape(PHP_OS)}
        </div>

        <script>
        /* ── Tab switching ── */
        document.querySelectorAll('.tab-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var tabId = this.getAttribute('data-tab');
                this.closest('.tabs').querySelectorAll('.tab-btn').forEach(function(b) {
                    b.classList.remove('active');
                });
                this.classList.add('active');
                this.closest('.tabs').querySelectorAll('.tab-panel').forEach(function(p) {
                    p.classList.remove('active');
                });
                var panel = document.getElementById('tab-' + tabId);
                if (panel) panel.classList.add('active');
            });
        });

        /* ── Accordion toggle ── */
        document.querySelectorAll('.trace-header').forEach(function(header) {
            header.addEventListener('click', function() {
                var frame = this.closest('.trace-frame');
                if (frame) frame.classList.toggle('open');
            });
        });
        </script>
        </body>
        </html>
        HTML;
    }

    private function renderCodeSnippet(string $file, int $line, int $context): string
    {
        if ($file === '' || $line <= 0 || !is_file($file) || !is_readable($file)) {
            return '';
        }

        $lines = @file($file);
        if ($lines === false) {
            return '';
        }

        $start = max(0, $line - $context - 1);
        $end = min(count($lines), $line + $context);

        $shortFile = $this->shortenPath($file);
        $rows = '';
        for ($i = $start; $i < $end; $i++) {
            $lineNum = $i + 1;
            $lineContent = $this->escape(rtrim($lines[$i]));
            $highlightClass = $lineNum === $line ? ' class="highlight"' : '';
            $rows .= "<tr{$highlightClass}>"
                . '<td class="line-number">' . $lineNum . '</td>'
                . '<td class="line-code">' . $lineContent . '</td>'
                . '</tr>';
        }

        return <<<HTML
        <div class="code-snippet">
          <div class="code-snippet-title">
            {$this->escape($shortFile)} : {$line}
          </div>
          <div style="overflow-x:auto">
            <table class="code-table"><tbody>{$rows}</tbody></table>
          </div>
        </div>
        HTML;
    }

    /**
     * @param list<array<string, mixed>> $trace
     */
    private function renderTrace(array $trace, string $exceptionFile, int $exceptionLine): string
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

            // Location display
            $locHtml = '';
            if ($file !== '') {
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

            $html .= '<li class="trace-frame">'
                . '<div class="trace-header">'
                . '<span class="trace-index">#' . $index . '</span>'
                . '<span class="trace-function">' . $funcHtml . '</span>'
                . '<span class="trace-location">' . $locHtml . '</span>'
                . '<span class="trace-chevron">&#9654;</span>'
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
            return '<p class="empty-state">No previous exceptions.</p>';
        }

        $html = '';
        for ($i = 1, $count = count($chain); $i < $count; $i++) {
            $item = $chain[$i];
            $html .= '<div class="chain-item">'
                . '<div class="chain-item-class">' . $this->escape($item['class']) . '</div>'
                . '<div class="chain-item-message">' . $this->escape($item['message']) . '</div>'
                . '<div class="chain-item-location">'
                . $this->escape($item['file']) . ':' . $item['line']
                . '</div>'
                . '</div>';
        }

        return $html;
    }

    private function renderRequestTab(): string
    {
        $html = '';

        // URL & Method
        $html .= '<div class="info-section">';
        $html .= '<div class="info-section-title">Request</div>';
        $html .= '<table class="info-table"><tbody>';
        $html .= $this->infoRow('Method', $_SERVER['REQUEST_METHOD'] ?? 'N/A');
        $html .= $this->infoRow('URL', ($_SERVER['REQUEST_SCHEME'] ?? 'http') . '://'
            . ($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost'))
            . ($_SERVER['REQUEST_URI'] ?? '/'));
        $html .= $this->infoRow('Script', $_SERVER['SCRIPT_FILENAME'] ?? 'N/A');
        $html .= $this->infoRow('Remote Address', $_SERVER['REMOTE_ADDR'] ?? 'N/A');
        $html .= $this->infoRow('Time', date('Y-m-d H:i:s T'));
        $html .= '</tbody></table></div>';

        // Headers
        $headers = $this->getRequestHeaders();
        if ($headers !== []) {
            $html .= '<div class="info-section">';
            $html .= '<div class="info-section-title">Headers</div>';
            $html .= '<table class="info-table"><tbody>';
            foreach ($headers as $name => $value) {
                $html .= $this->infoRow($name, $value);
            }
            $html .= '</tbody></table></div>';
        }

        // GET
        if (!empty($_GET)) {
            $html .= '<div class="info-section">';
            $html .= '<div class="info-section-title">GET Parameters</div>';
            $html .= '<table class="info-table"><tbody>';
            foreach ($_GET as $key => $value) {
                $html .= $this->infoRow((string) $key, $this->formatValue($value));
            }
            $html .= '</tbody></table></div>';
        }

        // POST
        if (!empty($_POST)) {
            $html .= '<div class="info-section">';
            $html .= '<div class="info-section-title">POST Parameters</div>';
            $html .= '<table class="info-table"><tbody>';
            foreach ($_POST as $key => $value) {
                $html .= $this->infoRow((string) $key, $this->formatValue($value));
            }
            $html .= '</tbody></table></div>';
        }

        // Cookies
        if (!empty($_COOKIE)) {
            $html .= '<div class="info-section">';
            $html .= '<div class="info-section-title">Cookies</div>';
            $html .= '<table class="info-table"><tbody>';
            foreach ($_COOKIE as $key => $value) {
                $html .= $this->infoRow((string) $key, $this->formatValue($value));
            }
            $html .= '</tbody></table></div>';
        }

        // Server
        $html .= '<div class="info-section">';
        $html .= '<div class="info-section-title">Server</div>';
        $html .= '<table class="info-table"><tbody>';
        $serverKeys = [
            'SERVER_SOFTWARE', 'SERVER_NAME', 'SERVER_ADDR', 'SERVER_PORT',
            'DOCUMENT_ROOT', 'GATEWAY_INTERFACE', 'SERVER_PROTOCOL',
            'QUERY_STRING', 'PATH_INFO', 'SCRIPT_NAME',
        ];
        foreach ($serverKeys as $key) {
            if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
                $html .= $this->infoRow($key, (string) $_SERVER[$key]);
            }
        }
        $html .= '</tbody></table></div>';

        return $html;
    }

    private function renderEnvironmentTab(): string
    {
        $html = '';

        // PHP Info
        $html .= '<div class="info-section">';
        $html .= '<div class="info-section-title">PHP</div>';
        $html .= '<table class="info-table"><tbody>';
        $html .= $this->infoRow('Version', PHP_VERSION);
        $html .= $this->infoRow('SAPI', PHP_SAPI);
        $html .= $this->infoRow('OS', PHP_OS . ' (' . php_uname('r') . ')');
        $html .= $this->infoRow('Architecture', PHP_INT_SIZE === 8 ? '64-bit' : '32-bit');
        $html .= $this->infoRow('Zend Engine', zend_version());
        $html .= $this->infoRow('OPcache', function_exists('opcache_get_status') ? 'Available' : 'Not available');
        $html .= $this->infoRow('Xdebug', extension_loaded('xdebug') ? phpversion('xdebug') ?: 'Loaded' : 'Not loaded');
        $html .= '</tbody></table></div>';

        // WordPress
        $html .= '<div class="info-section">';
        $html .= '<div class="info-section-title">WordPress</div>';
        $html .= '<table class="info-table"><tbody>';
        if (function_exists('get_bloginfo')) {
            $html .= $this->infoRow('Version', get_bloginfo('version'));
            $html .= $this->infoRow('Site URL', get_bloginfo('url'));
        } else {
            $html .= $this->infoRow('Version', defined('ABSPATH') ? 'WordPress loaded' : 'Not loaded');
        }
        $html .= $this->infoRow('WP_DEBUG', defined('WP_DEBUG') && WP_DEBUG ? 'true' : 'false');
        $html .= $this->infoRow('WP_DEBUG_LOG', defined('WP_DEBUG_LOG') && WP_DEBUG_LOG ? 'true' : 'false');
        $html .= $this->infoRow('WP_DEBUG_DISPLAY', defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY ? 'true' : 'false');
        $html .= $this->infoRow('ABSPATH', defined('ABSPATH') ? ABSPATH : 'N/A');
        $html .= '</tbody></table></div>';

        // Extensions
        $html .= '<div class="info-section">';
        $html .= '<div class="info-section-title">Loaded Extensions</div>';
        $html .= '<table class="info-table"><tbody>';
        $extensions = get_loaded_extensions();
        sort($extensions);
        $html .= $this->infoRow('Count', (string) count($extensions));
        $html .= $this->infoRow('Extensions', implode(', ', $extensions));
        $html .= '</tbody></table></div>';

        // PHP Configuration
        $html .= '<div class="info-section">';
        $html .= '<div class="info-section-title">PHP Configuration</div>';
        $html .= '<table class="info-table"><tbody>';
        $iniValues = [
            'memory_limit', 'max_execution_time', 'upload_max_filesize',
            'post_max_size', 'display_errors', 'error_reporting',
            'date.timezone', 'default_charset', 'max_input_vars',
        ];
        foreach ($iniValues as $key) {
            $value = ini_get($key);
            if ($value !== false && $value !== '') {
                $html .= $this->infoRow($key, $value);
            }
        }
        $html .= '</tbody></table></div>';

        return $html;
    }

    private function renderPerformanceTab(): string
    {
        $memoryUsage = memory_get_usage(true);
        $memoryPeak = memory_get_peak_usage(true);
        $memoryLimit = $this->parseBytes(ini_get('memory_limit') ?: '128M');

        $elapsed = 'N/A';
        if (defined('WP_START_TIMESTAMP')) {
            $elapsed = number_format((microtime(true) - WP_START_TIMESTAMP) * 1000, 1) . ' ms';
        } elseif (isset($_SERVER['REQUEST_TIME_FLOAT'])) {
            $elapsed = number_format((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 1) . ' ms';
        }

        $html = '<div class="perf-grid">';
        $html .= $this->perfCard($this->formatBytes($memoryUsage), 'Memory Usage');
        $html .= $this->perfCard($this->formatBytes($memoryPeak), 'Peak Memory');
        $html .= $this->perfCard($this->formatBytes($memoryLimit), 'Memory Limit');
        $html .= $this->perfCard($elapsed, 'Elapsed Time');
        $html .= '</div>';

        // Included files
        $includedFiles = get_included_files();
        $html .= '<div class="info-section">';
        $html .= '<div class="info-section-title">Included Files (' . count($includedFiles) . ')</div>';
        $html .= '<table class="info-table"><tbody>';
        foreach ($includedFiles as $index => $filePath) {
            $html .= $this->infoRow((string) ($index + 1), $this->shortenPath($filePath));
        }
        $html .= '</tbody></table></div>';

        return $html;
    }

    private function perfCard(string $value, string $label): string
    {
        return '<div class="perf-card">'
            . '<div class="perf-value">' . $this->escape($value) . '</div>'
            . '<div class="perf-label">' . $this->escape($label) . '</div>'
            . '</div>';
    }

    private function infoRow(string $key, string $value): string
    {
        return '<tr><th>' . $this->escape($key) . '</th><td>' . $this->escape($value) . '</td></tr>';
    }

    /**
     * @return array<string, string>
     */
    private function getRequestHeaders(): array
    {
        if (function_exists('getallheaders')) {
            return getallheaders();
        }

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', substr($key, 5));
                $name = ucwords(strtolower($name), '-');
                $headers[$name] = (string) $value;
            }
        }

        return $headers;
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

    private function formatValue(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: 'array';
        }

        return (string) $value;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $value = (float) $bytes;
        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        return number_format($value, $i === 0 ? 0 : 1) . ' ' . $units[$i];
    }

    private function parseBytes(string $value): int
    {
        $value = trim($value);
        $last = strtolower(substr($value, -1));
        $numericValue = (int) $value;

        return match ($last) {
            'g' => $numericValue * 1024 * 1024 * 1024,
            'm' => $numericValue * 1024 * 1024,
            'k' => $numericValue * 1024,
            default => $numericValue,
        };
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
