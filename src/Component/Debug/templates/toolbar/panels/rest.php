<?php
/**
 * REST API panel template.
 *
 * @var bool                                                         $isRestRequest   Whether this is a REST request
 * @var array|null                                                   $currentRequest  Current REST request data
 * @var int                                                          $totalRoutes     Total registered route count
 * @var int                                                          $totalNamespaces Total namespace count
 * @var array                                                        $routes          Routes grouped by namespace
 * @var \WpPack\Component\Debug\Toolbar\Panel\TemplateFormatters     $fmt             Template formatters
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
    $methodColor = match ($method) {
        'GET' => 'green',
        'POST' => 'primary',
        'PUT', 'PATCH' => 'yellow',
        'DELETE' => 'red',
        default => 'gray',
    };
    $statusColorClass = match (true) {
        $status >= 200 && $status < 300 => 'wpd-text-green',
        $status >= 300 && $status < 400 => 'wpd-text-yellow',
        default => 'wpd-text-red',
    };
    $authTag = match ($authentication) {
        'bearer' => $this->include('toolbar/partials/badge', ['label' => 'Bearer', 'color' => 'purple']),
        'basic' => $this->include('toolbar/partials/badge', ['label' => 'Basic', 'color' => 'yellow']),
        'nonce' => $this->include('toolbar/partials/badge', ['label' => 'Nonce', 'color' => 'primary']),
        'cookie' => $this->include('toolbar/partials/badge', ['label' => 'Cookie', 'color' => 'green']),
        default => '<span class="wpd-text-dim">' . $this->e($authentication) . '</span>',
    };
?>
<div class="wpd-section">
<h4 class="wpd-section-title">Current Request</h4>
<table class="wpd-table wpd-table-kv">
<?= $this->include('toolbar/partials/table-row', ['key' => 'Method', 'value' => $this->include('toolbar/partials/badge', ['label' => $method, 'color' => $methodColor])]) ?>
<?= $this->include('toolbar/partials/table-row', ['key' => 'Route', 'value' => $route !== '' ? '<code>' . $this->e($route) . '</code>' : '-']) ?>
<?php if ($path !== '' && $path !== $route): ?>
<?= $this->include('toolbar/partials/table-row', ['key' => 'Path', 'value' => '<code>' . $this->e($path) . '</code>']) ?>
<?php endif; ?>
<?= $this->include('toolbar/partials/table-row', ['key' => 'Namespace', 'value' => $namespace !== '' ? $this->e($namespace) : '-']) ?>
<?= $this->include('toolbar/partials/table-row', ['key' => 'Callback', 'value' => $callback !== '' ? '<code>' . $this->e($callback) . '</code>' : '-']) ?>
<?= $this->include('toolbar/partials/table-row', ['key' => 'Status', 'value' => (string) $status, 'valueClass' => $statusColorClass]) ?>
<?= $this->include('toolbar/partials/table-row', ['key' => 'Authentication', 'value' => $authTag]) ?>
</table>
</div>
<?php if (!empty($params)): ?>
<div class="wpd-section">
<h4 class="wpd-section-title">Request Parameters</h4>
<table class="wpd-table wpd-table-kv">
<?php foreach ($params as $key => $value): ?>
<?= $this->include('toolbar/partials/table-row', ['key' => $key, 'value' => $fmt->value($value)]) ?>
<?php endforeach; ?>
</table>
</div>
<?php endif; ?>
<?php endif; ?>
<div class="wpd-section">
<h4 class="wpd-section-title">Summary</h4>
<table class="wpd-table wpd-table-kv">
<?= $this->include('toolbar/partials/table-row', ['key' => 'REST Request', 'value' => $fmt->value($isRestRequest)]) ?>
<?= $this->include('toolbar/partials/table-row', ['key' => 'Total Routes', 'value' => (string) $totalRoutes]) ?>
<?= $this->include('toolbar/partials/table-row', ['key' => 'Namespaces', 'value' => (string) $totalNamespaces]) ?>
</table>
</div>
<?php foreach ($routes as $nsName => $nsRoutes): ?>
<div class="wpd-section">
<h4 class="wpd-section-title"><?= $this->e($nsName) ?> (<?= count($nsRoutes) ?>)</h4>
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
        $color = match ($m) {
            'GET' => 'green',
            'POST' => 'primary',
            'PUT', 'PATCH' => 'yellow',
            'DELETE' => 'red',
            default => 'gray',
        };
        $methodTags .= $this->include('toolbar/partials/badge', ['label' => $m, 'color' => $color]) . ' ';
    }
?>
<tr>
<td><code><?= $this->e($routeInfo['route']) ?></code></td>
<td><?= $this->raw($methodTags) ?></td>
<td class="wpd-text-dim"><?= $this->e($routeInfo['callback']) ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
<?php endforeach; ?>
