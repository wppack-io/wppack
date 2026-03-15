<?php
/**
 * Error page template.
 *
 * @var string $shortClass       Short exception class name
 * @var string $class            Full exception class name
 * @var string $message          Exception message
 * @var string $codeLabel        Exception code label HTML
 * @var string $cssVariables     CSS custom property declarations
 * @var string $traceHtml        Stack trace HTML
 * @var string $chainHtml        Previous exception chain HTML
 * @var int    $chainCount       Total chain count (including primary exception)
 * @var string $toolbarHtml      Debug toolbar HTML
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $view->e($shortClass) ?> - WpPack Debug</title>
<style>
/* ── Reset ─────────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

/* ── Variables / Base ──────────────────────────────────── */
:root {
<?= $view->raw($cssVariables) ?>
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
.chain-item { margin-bottom: 20px; }
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
    <div class="exception-class"><?= $view->e($class) ?><?= $view->raw($codeLabel) ?></div>
    <div class="exception-message"><?= $view->e($message) ?></div>
  </div>
</div>

<div class="container">

<!-- ═══ Stack Trace ═══ -->
<div class="section">
  <div class="section-title">Stack Trace</div>
  <?= $view->raw($traceHtml) ?>
</div>

<?php if ($chainCount > 1): ?>
<!-- ═══ Previous Exceptions ═══ -->
<div class="section">
  <div class="section-title">Previous Exceptions (<?= $view->e((string) ($chainCount - 1)) ?>)</div>
  <?= $view->raw($chainHtml) ?>
</div>
<?php endif; ?>

</div><!-- .container -->

<?= $view->raw($toolbarHtml) ?>

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
