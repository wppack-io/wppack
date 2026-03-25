<?php
/**
 * WP_Error panel template.
 *
 * @var int                                                          $totalCount       Total error count
 * @var int                                                          $uniqueObjects    Unique WP_Error object count
 * @var list<array>                                                  $errors           Error records
 * @var \WpPack\Component\Debug\Toolbar\Panel\TemplateFormatters     $fmt              Template formatters
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
<details style="margin-top:6px">
<summary style="cursor:pointer;font-size:12px;color:var(--wpd-gray-600)">Stack Trace (<?= $view->e((string) count($trace)) ?> frames)</summary>
<div style="margin-top:4px;font-size:11px;line-height:1.6;overflow-x:auto">
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
<div style="padding:2px 0;border-bottom:1px solid var(--wpd-gray-200)">
<span class="wpd-text-dim">#<?= $view->e((string) $i) ?></span>
<strong><?= $view->e($callable) ?></strong>(<?= $view->e($argsStr) ?>)<?php if ($frameFile !== ''): ?>
 &mdash; <code style="font-size:11px"><?= $view->e($frameFile) ?><?php if ($frameLine > 0): ?>:<?= $view->e((string) $frameLine) ?><?php endif; ?></code><?php endif; ?>
</div>
<?php endforeach; ?>
</div>
</details>
<?php endif; ?>
</div>
<?php endforeach; ?>
