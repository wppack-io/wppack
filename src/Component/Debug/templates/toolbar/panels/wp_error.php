<?php
/**
 * WP_Error panel template.
 *
 * @var int                                                          $totalCount       Total error count
 * @var int                                                          $uniqueObjects    Unique WP_Error object count
 * @var list<array>                                                  $errors           Error records
 * @var \WPPack\Component\Debug\Toolbar\Panel\TemplateFormatters     $fmt              Template formatters
 * @var float                                                        $requestTimeFloat Request start timestamp
 */
?>
<div class="wpd-section">
<h4 class="wpd-section-title">Summary</h4>
<table class="wpd-table wpd-table-kv">
<?= $view->include('toolbar/partials/table-row', ['key' => 'Total Errors', 'value' => (string) $totalCount, 'valueClass' => $totalCount > 0 ? 'wpd-text-yellow' : '']) ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Unique WP_Error Objects', 'value' => (string) $uniqueObjects]) ?>
</table>
</div>
<?php foreach ($errors as $index => $error):
    $code = (string) ($error['code'] ?? '');
    $message = (string) ($error['message'] ?? '');
    $data = (string) ($error['data'] ?? '(none)');
    $file = (string) ($error['file'] ?? '');
    $line = (int) ($error['line'] ?? 0);
    $timestamp = (float) ($error['timestamp'] ?? 0);
    $objectId = (int) ($error['object_id'] ?? 0);
    $trace = $error['trace'] ?? [];
    $timeDisplay = $fmt->relativeTime($timestamp, $requestTimeFloat);
    ?>
<div class="wpd-section">
<h4 class="wpd-section-title">#<?= $view->e((string) ($index + 1)) ?> <?= $view->include('toolbar/partials/badge', ['label' => $code, 'color' => 'yellow']) ?></h4>
<table class="wpd-table wpd-table-kv">
<?= $view->include('toolbar/partials/table-row', ['key' => 'Code', 'value' => '<code>' . $view->e($code) . '</code>']) ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Message', 'value' => $view->e($message)]) ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Data', 'value' => '<code>' . $view->e($data) . '</code>']) ?>
<?php if ($file !== ''): ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'File', 'value' => '<code>' . $view->e($file) . ':' . $view->e((string) $line) . '</code>']) ?>
<?php endif; ?>
<?php if ($timeDisplay !== ''): ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Time', 'value' => '<span class="wpd-text-dim">' . $view->e($timeDisplay) . '</span>']) ?>
<?php endif; ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Object ID', 'value' => '<span class="wpd-text-dim">#' . $view->e((string) $objectId) . '</span>']) ?>
</table>
<?php if (!empty($trace)): ?>
<details class="wpd-trace-toggle">
<summary>Stack Trace (<?= $view->e((string) count($trace)) ?> frames)</summary>
<table class="wpd-table wpd-table-full wpd-trace-table">
<thead><tr>
<th class="wpd-col-num">#</th>
<th>Function</th>
<th>File</th>
</tr></thead>
<tbody>
<?php foreach ($trace as $i => $frame):
    $frameClass = (string) ($frame['class'] ?? '');
    $frameType = (string) ($frame['type'] ?? '');
    $frameFunction = (string) ($frame['function'] ?? '');
    $frameFile = (string) ($frame['file'] ?? '');
    $frameLine = (int) ($frame['line'] ?? 0);
    $frameArgs = $frame['args'] ?? [];
    $callable = $frameClass !== '' ? $frameClass . $frameType . $frameFunction : $frameFunction;
    $argsStr = implode(', ', $frameArgs);
    ?>
<tr>
<td class="wpd-col-num"><?= $view->e((string) $i) ?></td>
<td><code><?= $view->e($callable) ?>(<?= $view->e($argsStr) ?>)</code></td>
<td class="wpd-text-dim"><?php if ($frameFile !== ''): ?><code><?= $view->e($frameFile) ?><?php if ($frameLine > 0): ?>:<?= $view->e((string) $frameLine) ?><?php endif; ?></code><?php endif; ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</details>
<?php endif; ?>
</div>
<?php endforeach; ?>
