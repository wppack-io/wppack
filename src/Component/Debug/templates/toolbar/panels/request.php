<?php
/**
 * Request panel template.
 *
 * @var array<string,mixed>                                          $data Request data
 * @var \WpPack\Component\Debug\Toolbar\Panel\TemplateFormatters     $fmt  Template formatters
 */
$serverVars = $data['server_vars'] ?? [];
$method = (string) ($data['method'] ?? '');
$methodColor = match ($method) {
    'GET' => 'green',
    'POST' => 'primary',
    'PUT', 'PATCH' => 'yellow',
    'DELETE' => 'red',
    default => 'gray',
};
?>
<div class="wpd-section">
<h4 class="wpd-section-title">Request</h4>
<table class="wpd-table wpd-table-kv">
<?= $this->include('toolbar/partials/table-row', ['key' => 'Method', 'value' => $this->include('toolbar/partials/badge', ['label' => $method, 'color' => $methodColor])]) ?>
<?= $this->include('toolbar/partials/table-row', ['key' => 'URL', 'value' => (string) ($data['url'] ?? '')]) ?>
<?php
$script = (string) ($serverVars['SCRIPT_FILENAME'] ?? '');
if ($script !== '') {
    echo $this->include('toolbar/partials/table-row', ['key' => 'Script', 'value' => $script]);
}
$remoteAddr = (string) ($serverVars['REMOTE_ADDR'] ?? '');
if ($remoteAddr !== '') {
    echo $this->include('toolbar/partials/table-row', ['key' => 'Remote Address', 'value' => $remoteAddr]);
}
$requestTimeFloat = $serverVars['REQUEST_TIME_FLOAT'] ?? null;
if ($requestTimeFloat !== null) {
    echo $this->include('toolbar/partials/table-row', ['key' => 'Time', 'value' => date('Y-m-d H:i:s', (int) $requestTimeFloat)]);
}
?>
</table>
</div>
<?php
$statusCode = (int) ($data['status_code'] ?? 200);
$contentType = (string) ($data['content_type'] ?? '');
$statusColorClass = match (true) {
    $statusCode >= 200 && $statusCode < 300 => 'wpd-text-green',
    $statusCode >= 300 && $statusCode < 400 => 'wpd-text-yellow',
    default => 'wpd-text-red',
};
?>
<div class="wpd-section">
<h4 class="wpd-section-title">Response</h4>
<table class="wpd-table wpd-table-kv">
<?= $this->include('toolbar/partials/table-row', ['key' => 'Status Code', 'value' => (string) $statusCode, 'valueClass' => $statusColorClass]) ?>
<?php if ($contentType !== ''): ?>
<?= $this->include('toolbar/partials/table-row', ['key' => 'Content-Type', 'value' => $contentType]) ?>
<?php endif; ?>
</table>
</div>
<?php
$requestHeaders = $data['request_headers'] ?? [];
if (!empty($requestHeaders)) {
    echo $this->include('toolbar/partials/key-value-section', ['title' => 'Request Headers', 'items' => $requestHeaders, 'fmt' => $fmt]);
}
$responseHeaders = $data['response_headers'] ?? [];
if (!empty($responseHeaders)) {
    echo $this->include('toolbar/partials/key-value-section', ['title' => 'Response Headers', 'items' => $responseHeaders, 'fmt' => $fmt]);
}
$getParams = $data['get_params'] ?? [];
if (!empty($getParams)) {
    echo $this->include('toolbar/partials/key-value-section', ['title' => 'GET Parameters', 'items' => $getParams, 'fmt' => $fmt]);
}
$postParams = $data['post_params'] ?? [];
if (!empty($postParams)) {
    echo $this->include('toolbar/partials/key-value-section', ['title' => 'POST Parameters', 'items' => $postParams, 'fmt' => $fmt]);
}
$cookies = $data['cookies'] ?? [];
if (!empty($cookies)) {
    echo $this->include('toolbar/partials/key-value-section', ['title' => 'Cookies', 'items' => $cookies, 'fmt' => $fmt]);
}
$excludeFromServerVars = ['SCRIPT_FILENAME', 'REMOTE_ADDR', 'REQUEST_TIME_FLOAT'];
$filteredServerVars = array_diff_key($serverVars, array_flip($excludeFromServerVars));
if (!empty($filteredServerVars)) {
    echo $this->include('toolbar/partials/key-value-section', ['title' => 'Server Variables', 'items' => $filteredServerVars, 'fmt' => $fmt]);
}
$httpApiCalls = $data['http_api_calls'] ?? [];
if (!empty($httpApiCalls)):
?>
<div class="wpd-section">
<h4 class="wpd-section-title">HTTP API Calls (<?= $this->e((string) count($httpApiCalls)) ?>)</h4>
<table class="wpd-table wpd-table-full">
<thead><tr><th>#</th><th>URL</th></tr></thead>
<tbody>
<?php foreach ($httpApiCalls as $index => $call): ?>
<tr>
<td><?= $this->e((string) ($index + 1)) ?></td>
<td><code><?= $this->e($call['url']) ?></code></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
<?php endif; ?>
