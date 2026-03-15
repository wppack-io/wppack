<?php
/**
 * Logger panel template.
 *
 * @var int                                                          $totalCount       Total log entry count
 * @var int                                                          $errorCount       Error log count
 * @var int                                                          $deprecationCount Deprecation log count
 * @var int                                                          $warningCount     Warning log count
 * @var array<string,int>                                            $channelCounts    Log count per channel
 * @var list<array>                                                  $logs             Log entries
 * @var \WpPack\Component\Debug\Toolbar\Panel\TemplateFormatters     $fmt              Template formatters
 * @var float                                                        $requestTimeFloat Request start timestamp
 */
?>
<div class="wpd-section">
<h4 class="wpd-section-title">Summary</h4>
<table class="wpd-table wpd-table-kv">
<?= $view->include('toolbar/partials/table-row', ['key' => 'Total Entries', 'value' => (string) $totalCount]) ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Errors', 'value' => (string) $errorCount, 'valueClass' => $errorCount > 0 ? 'wpd-text-red' : '']) ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Deprecations', 'value' => (string) $deprecationCount, 'valueClass' => $deprecationCount > 0 ? 'wpd-text-orange' : '']) ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Warnings', 'value' => (string) $warningCount, 'valueClass' => $warningCount > 0 ? 'wpd-text-yellow' : '']) ?>
</table>
</div>
<?php if (!empty($channelCounts)): ?>
<div class="wpd-section">
<h4 class="wpd-section-title">Channels</h4>
<div class="wpd-tag-list">
<?php foreach ($channelCounts as $ch => $count): ?>
<span class="wpd-tag"><?= $view->e($ch) ?> (<?= $count ?>)</span>
<?php endforeach; ?>
</div>
</div>
<?php endif; ?>
<?php if (!empty($logs)):
    $errorTabCount = 0;
    $deprecationTabCount = 0;
    $warningTabCount = 0;
    $noticeTabCount = 0;
    $infoTabCount = 0;
    $debugTabCount = 0;
    foreach ($logs as $log) {
        $lvl = $log['level'] ?? 'debug';
        if (($log['context']['_type'] ?? null) === 'deprecation') {
            $deprecationTabCount++;
        } elseif (in_array($lvl, ['emergency', 'alert', 'critical', 'error'], true)) {
            $errorTabCount++;
        } elseif ($lvl === 'warning') {
            $warningTabCount++;
        } elseif ($lvl === 'notice') {
            $noticeTabCount++;
        } elseif ($lvl === 'info') {
            $infoTabCount++;
        } else {
            $debugTabCount++;
        }
    }
?>
<div class="wpd-section">
<h4 class="wpd-section-title">Log Entries</h4>
<div class="wpd-log-tabs">
<button class="wpd-log-tab wpd-active" data-log-filter="all">All (<?= $view->e((string) count($logs)) ?>)</button>
<button class="wpd-log-tab" data-log-filter="error"<?= $errorTabCount === 0 ? ' disabled' : '' ?>>Errors (<?= $errorTabCount ?>)</button>
<button class="wpd-log-tab" data-log-filter="warning"<?= $warningTabCount === 0 ? ' disabled' : '' ?>>Warnings (<?= $warningTabCount ?>)</button>
<button class="wpd-log-tab" data-log-filter="notice"<?= $noticeTabCount === 0 ? ' disabled' : '' ?>>Notices (<?= $noticeTabCount ?>)</button>
<button class="wpd-log-tab" data-log-filter="info"<?= $infoTabCount === 0 ? ' disabled' : '' ?>>Info (<?= $infoTabCount ?>)</button>
<button class="wpd-log-tab" data-log-filter="debug"<?= $debugTabCount === 0 ? ' disabled' : '' ?>>Debug (<?= $debugTabCount ?>)</button>
<button class="wpd-log-tab" data-log-filter="deprecation"<?= $deprecationTabCount === 0 ? ' disabled' : '' ?>>Deprecations (<?= $deprecationTabCount ?>)</button>
</div>
<table class="wpd-table wpd-table-full">
<thead><tr>
<th class="wpd-col-num">#</th>
<th class="wpd-col-reltime">Time</th>
<th>Level</th>
<th>Channel</th>
<th>Message</th>
<th>File</th>
<th></th>
</tr></thead>
<tbody>
<?php foreach ($logs as $index => $log):
    $level = $log['level'] ?? 'debug';
    $effectiveLevel = ($log['context']['_type'] ?? null) === 'deprecation' ? 'deprecation' : $level;
    $levelColor = match ($effectiveLevel) {
        'emergency', 'alert', 'critical' => 'wpd-log-critical',
        'error' => 'wpd-log-error',
        'warning' => 'wpd-log-warning',
        'notice' => 'wpd-log-notice',
        'info' => 'wpd-log-info',
        'deprecation' => 'wpd-log-deprecation',
        default => 'wpd-log-debug',
    };
    $file = (string) ($log['file'] ?? '');
    $line = (int) ($log['line'] ?? 0);
    $fileDisplay = '';
    if ($file !== '') {
        $basename = basename($file);
        $fileDisplay = $line > 0 ? $basename . ':' . $line : $basename;
    }
    $timestamp = (float) ($log['timestamp'] ?? 0);
    $timeDisplay = $fmt->relativeTime($timestamp, $requestTimeFloat);
    $context = $log['context'] ?? [];
    $hasContext = is_array($context) && !empty($context);
    $rowClass = $hasContext ? ' class="wpd-log-toggle"' : '';
    $toggleIcon = $hasContext ? '<span class="wpd-log-indicator">+</span>' : '';
    ?>
<tr data-log-level="<?= $view->e($effectiveLevel) ?>"<?= $rowClass ?>>
<td class="wpd-col-num"><?= $view->e((string) ($index + 1)) ?></td>
<td class="wpd-col-reltime wpd-text-dim"><?= $view->e($timeDisplay) ?></td>
<td><span class="wpd-tag <?= $levelColor ?>"><?= $view->e($effectiveLevel) ?></span></td>
<td><span class="wpd-tag"><?= $view->e($log['channel'] ?? 'app') ?></span></td>
<td><code><?= $view->e($log['message'] ?? '') ?></code></td>
<td title="<?= $view->e($file) ?>"><?= $view->e($fileDisplay) ?></td>
<td class="wpd-col-toggle"><?= $view->raw($toggleIcon) ?></td>
</tr>
<?php if ($hasContext): ?>
<tr class="wpd-log-context" style="display:none" data-log-level="<?= $view->e($effectiveLevel) ?>">
<td colspan="7"><pre><?= $view->e(json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}') ?></pre></td>
</tr>
<?php endif; ?>
<?php endforeach; ?>
</tbody></table>
</div>
<?php endif; ?>
