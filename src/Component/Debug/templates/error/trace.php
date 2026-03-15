<?php
/**
 * Stack trace template.
 *
 * @var list<array>                                                $trace     Stack trace frames
 * @var bool                                                       $openFirst Whether to auto-expand the first frame
 * @var \WpPack\Component\Debug\ErrorHandler\ErrorRenderer         $renderer  Error renderer for path formatting
 */
if (empty($trace)): ?>
<p class="empty-state">No stack trace available.</p>
<?php return; endif; ?>
<ul class="trace-list">
<?php foreach ($trace as $index => $frame):
    $file = $frame['file'];
    $line = $frame['line'];
    $class = $frame['class'];
    $type = $frame['type'];
    $function = $frame['function'];
    $args = $frame['args'];
    $codeContext = $frame['code_context'];
    $highlightLine = $frame['highlight_line'];

    $funcHtml = '';
    if ($function === '') {
        if ($file !== '') {
            $funcHtml = '<span class="loc-file">' . $this->e($renderer->shortenPath($file)) . '</span>';
            if ($line > 0) {
                $funcHtml .= ':<span class="loc-line">' . $line . '</span>';
            }
        }
    } else {
        if ($class !== '') {
            $funcHtml .= '<span class="class-name">' . $this->e($renderer->shortClassName($class)) . '</span>';
            $funcHtml .= '<span class="type-sep">' . $this->e($type) . '</span>';
        }
        $funcHtml .= '<span class="method-name">' . $this->e($function) . '</span>';
        if (!empty($args)) {
            $funcHtml .= '<span class="args-list">(' . $this->e(implode(', ', $args)) . ')</span>';
        } else {
            $funcHtml .= '<span class="args-list">()</span>';
        }
    }

    $locHtml = '';
    if ($function !== '' && $file !== '') {
        $shortFile = $renderer->shortenPath($file);
        $locHtml = '<span class="loc-file">' . $this->e($shortFile) . '</span>';
        if ($line > 0) {
            $locHtml .= ':<span class="loc-line">' . $line . '</span>';
        }
    }

    $bodyHtml = '';
    if (!empty($codeContext) && $highlightLine > 0) {
        $startLine = max(1, $highlightLine - (int) floor(count($codeContext) / 2));
        if ($file !== '' && $line > 0) {
            $startLine = max(1, $line - 10);
        }
        $rows = '';
        foreach ($codeContext as $ci => $codeLine) {
            $currentLineNum = $startLine + $ci;
            $hl = $currentLineNum === $highlightLine ? ' class="highlight"' : '';
            $rows .= "<tr{$hl}>"
                . '<td class="line-number">' . $currentLineNum . '</td>'
                . '<td class="line-code">' . $this->e($codeLine) . '</td>'
                . '</tr>';
        }
        $bodyHtml = '<div style="overflow-x:auto"><table class="code-table"><tbody>'
            . $rows . '</tbody></table></div>';
    }

    $isOpen = $openFirst && $index === 0;
    $openClass = $isOpen ? ' open' : '';
    $indicator = $isOpen ? "\u{2212}" : '+';
?>
<li class="trace-frame<?= $openClass ?>">
<div class="trace-header">
<span class="trace-index">#<?= $index ?></span>
<span class="trace-function"><?= $this->raw($funcHtml) ?></span>
<span class="trace-location"><?= $this->raw($locHtml) ?></span>
<span class="trace-toggle"><span class="wpd-log-indicator"><?= $indicator ?></span></span>
</div>
<?php if ($bodyHtml !== ''): ?>
<div class="trace-body"><?= $this->raw($bodyHtml) ?></div>
<?php endif; ?>
</li>
<?php endforeach; ?>
</ul>
