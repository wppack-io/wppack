<?php
/**
 * REST API panel template.
 *
 * @var bool                                                         $isRestRequest   Whether this is a REST request
 * @var array|null                                                   $currentRequest  Current REST request data
 * @var int                                                          $totalRoutes     Total registered route count
 * @var int                                                          $totalNamespaces Total namespace count
 * @var array                                                        $routes          Routes grouped by namespace
 * @var \WPPack\Component\Debug\Toolbar\Panel\TemplateFormatters     $fmt             Template formatters
 */
if ($isRestRequest && is_array($currentRequest)):
    $method = (string) ($currentRequest['method'] ?? '');
    $route = (string) ($currentRequest['route'] ?? '');
    $path = (string) ($currentRequest['path'] ?? '');
    $namespace = (string) ($currentRequest['namespace'] ?? '');
    $callback = (string) ($currentRequest['callback'] ?? '');
    $status = (int) ($currentRequest['status'] ?? 200);
    $authentication = (string) ($currentRequest['authentication'] ?? 'none');
    $params = $currentRequest['params'] ?? [];
    $methodColor = $fmt->methodColor($method);
    $statusColorClass = $fmt->statusColor($status);
    $authTag = match ($authentication) {
        'bearer' => $view->include('toolbar/partials/badge', ['label' => 'Bearer', 'color' => 'purple']),
        'basic' => $view->include('toolbar/partials/badge', ['label' => 'Basic', 'color' => 'yellow']),
        'nonce' => $view->include('toolbar/partials/badge', ['label' => 'Nonce', 'color' => 'primary']),
        'cookie' => $view->include('toolbar/partials/badge', ['label' => 'Cookie', 'color' => 'green']),
        default => '<span class="wpd-text-dim">' . $view->e($authentication) . '</span>',
    };
    ?>
<div class="wpd-section">
<h4 class="wpd-section-title">Current Request</h4>
<table class="wpd-table wpd-table-kv">
<?= $view->include('toolbar/partials/table-row', ['key' => 'Method', 'value' => $view->include('toolbar/partials/badge', ['label' => $method, 'color' => $methodColor])]) ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Route', 'value' => $route !== '' ? '<code>' . $view->e($route) . '</code>' : '-']) ?>
<?php if ($path !== '' && $path !== $route): ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Path', 'value' => '<code>' . $view->e($path) . '</code>']) ?>
<?php endif; ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Namespace', 'value' => $namespace !== '' ? $view->e($namespace) : '-']) ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Callback', 'value' => $callback !== '' ? '<code>' . $view->e($callback) . '</code>' : '-']) ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Status', 'value' => (string) $status, 'valueClass' => $statusColorClass]) ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Authentication', 'value' => $authTag]) ?>
</table>
</div>
<?php if (!empty($params)): ?>
<div class="wpd-section">
<h4 class="wpd-section-title">Request Parameters</h4>
<table class="wpd-table wpd-table-kv">
<?php foreach ($params as $key => $value): ?>
<?= $view->include('toolbar/partials/table-row', ['key' => $key, 'value' => $fmt->value($value)]) ?>
<?php endforeach; ?>
</table>
</div>
<?php endif; ?>
<?php endif; ?>
<div class="wpd-section">
<h4 class="wpd-section-title">Summary</h4>
<table class="wpd-table wpd-table-kv">
<?= $view->include('toolbar/partials/table-row', ['key' => 'REST Request', 'value' => $fmt->value($isRestRequest)]) ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Total Routes', 'value' => (string) $totalRoutes]) ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Namespaces', 'value' => (string) $totalNamespaces]) ?>
</table>
</div>
<?php foreach ($routes as $nsName => $nsRoutes): ?>
<div class="wpd-section">
<h4 class="wpd-section-title"><?= $view->e($nsName) ?> (<?= count($nsRoutes) ?>)</h4>
<table class="wpd-table wpd-table-full">
<thead><tr>
<th>Route</th>
<th>Methods</th>
<th>Callback</th>
</tr></thead>
<tbody>
<?php foreach ($nsRoutes as $routeInfo):
    $methodTags = '';
    foreach ($routeInfo['methods'] as $m) {
        $methodTags .= $view->include('toolbar/partials/method-badge', ['method' => $m, 'fmt' => $fmt]) . ' ';
    }
    ?>
<tr>
<td><code><?= $view->e($routeInfo['route']) ?></code></td>
<td><?= $view->raw($methodTags) ?></td>
<td class="wpd-text-dim"><?= $view->e($routeInfo['callback']) ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
<?php endforeach; ?>
