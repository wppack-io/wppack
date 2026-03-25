<?php

/**
 * Redirect intercept page template.
 *
 * @var string $location       Redirect target URL
 * @var int    $status         HTTP status code (301, 302, etc.)
 * @var string $requestMethod  Original request method (POST, GET, etc.)
 * @var string $requestUri     Original request URI
 * @var string $toolbarHtml    Debug toolbar HTML
 * @var string $cssVariables   CSS custom property declarations
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HTTP <?= $view->e((string) $status) ?> Redirect - WpPack Debug</title>
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
    background: var(--wpd-gray-50);
    color: var(--wpd-gray-900);
    line-height: 1.5;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 16px 80px;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}

/* ── Card ──────────────────────────────────────────────── */
.redirect-card {
    background: var(--wpd-white);
    border: 1px solid var(--wpd-gray-200);
    border-radius: var(--wpd-radius);
    padding: 32px;
    max-width: 640px;
    width: 100%;
    text-align: center;
}

/* ── Status badge ──────────────────────────────────────── */
.status-badge {
    display: inline-block;
    font-family: var(--wpd-font-mono);
    font-size: 11px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: var(--wpd-radius-sm);
    margin-bottom: 16px;
}
.status-badge--301 {
    color: var(--wpd-blue);
    background: var(--wpd-blue-a10);
}
.status-badge--302,
.status-badge--303,
.status-badge--307,
.status-badge--308 {
    color: var(--wpd-green);
    background: var(--wpd-green-a10);
}

/* ── Request info ──────────────────────────────────────── */
.request-info {
    font-family: var(--wpd-font-mono);
    font-size: 12px;
    color: var(--wpd-gray-600);
    margin-bottom: 8px;
    word-break: break-all;
}
.request-method {
    font-weight: 600;
    color: var(--wpd-gray-900);
}

/* ── Target URL ────────────────────────────────────────── */
.redirect-target {
    font-size: 12px;
    color: var(--wpd-gray-500);
    margin-bottom: 24px;
    word-break: break-all;
}
.redirect-arrow {
    color: var(--wpd-gray-400);
    margin-right: 4px;
}
.redirect-target a {
    color: var(--wpd-primary);
    text-decoration: none;
}
.redirect-target a:hover {
    text-decoration: underline;
}

/* ── Button ────────────────────────────────────────────── */
.redirect-btn {
    display: inline-block;
    background: var(--wpd-primary);
    color: var(--wpd-white);
    font-family: var(--wpd-font-sans);
    font-size: 13px;
    font-weight: 500;
    padding: 8px 20px;
    border: none;
    border-radius: var(--wpd-radius-sm);
    text-decoration: none;
    cursor: pointer;
    transition: background 0.15s;
}
.redirect-btn:hover {
    background: var(--wpd-primary-hover);
}

/* ── Responsive ────────────────────────────────────────── */
@media (max-width: 480px) {
    .redirect-card { padding: 24px 16px; }
}
</style>
</head>
<body>

<div class="redirect-card">
  <div class="status-badge status-badge--<?= $view->e((string) $status) ?>">HTTP <?= $view->e((string) $status) ?></div>
  <div class="request-info">
    <span class="request-method"><?= $view->e($requestMethod) ?></span>
    <?= $view->e($requestUri) ?>
  </div>
  <div class="redirect-target">
    <span class="redirect-arrow">&rarr;</span>
<?php if ($location !== ''): ?>
    <a href="<?= $view->e($location) ?>"><?= $view->e($location) ?></a>
<?php else: ?>
    <span>(blocked: unsafe URL scheme)</span>
<?php endif; ?>
  </div>
<?php if ($location !== ''): ?>
  <a class="redirect-btn" href="<?= $view->e($location) ?>">Follow redirect &rarr;</a>
<?php endif; ?>
</div>

<?= $view->raw($toolbarHtml) ?>

</body>
</html>
