<?php
/**
 * HTTP client panel template.
 *
 * @var int                                                          $totalCount       Total HTTP request count
 * @var float                                                        $totalTime        Total request time in ms
 * @var int                                                          $errorCount       Error count
 * @var int                                                          $slowCount        Slow request count
 * @var list<array>                                                  $requests         HTTP request records
 * @var \WpPack\Component\Debug\Toolbar\Panel\TemplateFormatters     $fmt              Template formatters
 * @var float                                                        $requestTimeFloat Request start timestamp
 */
?>
<div class="wpd-section">
<h4 class="wpd-section-title">Summary</h4>
<table class="wpd-table wpd-table-kv">
<?= $this->include('toolbar/partials/table-row', ['key' => 'Total Requests', 'value' => (string) $totalCount]) ?>
<?= $this->include('toolbar/partials/table-row', ['key' => 'Total Time', 'value' => $fmt->ms($totalTime)]) ?>
<?= $this->include('toolbar/partials/table-row', ['key' => 'Errors', 'value' => (string) $errorCount, 'valueClass' => $errorCount > 0 ? 'wpd-text-red' : '']) ?>
<?= $this->include('toolbar/partials/table-row', ['key' => 'Slow Requests', 'value' => (string) $slowCount, 'valueClass' => $slowCount > 0 ? 'wpd-text-yellow' : '']) ?>
</table>
</div>
<?php if (!empty($requests)): ?>
<div class="wpd-section">
<h4 class="wpd-section-title">Requests</h4>
<table class="wpd-table wpd-table-full">
<thead><tr>
<th class="wpd-col-num">#</th>
<th class="wpd-col-right">Time</th>
<th>Method</th>
<th>URL</th>
<th>Status</th>
<th class="wpd-col-right">Duration</th>
<th class="wpd-col-right">Size</th>
</tr></thead>
<tbody>
<?php foreach ($requests as $index => $request):
    $statusCode = (int) ($request['status_code'] ?? 0);
    $statusColor = $fmt->statusColor($statusCode);
    $startTime = (float) ($request['start'] ?? 0);
    $relTime = $fmt->relativeTime($startTime, $requestTimeFloat);
    $method = (string) ($request['method'] ?? 'GET');
?>
<tr>
<td class="wpd-col-num"><?= $this->e((string) ($index + 1)) ?></td>
<td class="wpd-col-reltime wpd-text-dim"><?= $this->e($relTime) ?></td>
<td><?= $this->include('toolbar/partials/method-badge', ['method' => $method, 'fmt' => $fmt]) ?></td>
<td><code><?= $this->e($request['url'] ?? '') ?></code></td>
<td class="<?= $statusColor ?>"><?= $statusCode > 0 ? $this->e((string) $statusCode) : '-' ?><?php if (($request['error'] ?? '') !== ''): ?><br><small class="wpd-text-red"><?= $this->e($request['error']) ?></small><?php endif; ?></td>
<td class="wpd-col-right"><?= $this->e($fmt->ms((float) ($request['duration'] ?? 0.0))) ?></td>
<td class="wpd-col-right"><?= $this->e($fmt->bytes((int) ($request['response_size'] ?? 0))) ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
<?php endif; ?>
