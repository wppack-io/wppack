<?php
/**
 * @var array<string,mixed> $entry
 * @var string $color
 * @var float $totalTime
 * @var \WpPack\Component\Debug\Toolbar\Panel\TemplateFormatters $fmt
 * @var float $unmeasuredMs
 * @var float $unmeasuredPct
 */
?>
<div class="wpd-perf-wf-row">
<div class="wpd-perf-wf-label"><?= $view->e((string) ($entry['name'] ?? '')) ?></div>
<div class="wpd-perf-wf-track">
<?php if ($unmeasuredPct >= 0.5): ?>
<div class="wpd-perf-wf-unmeasured" data-tooltip="<?= $view->e('Before profiling started (' . $fmt->ms($unmeasuredMs) . ')') ?>" style="width:<?= $view->e(sprintf('%.2f', $unmeasuredPct)) ?>%"></div>
<?php endif; ?>
<?php
$bars = $entry['bars'] ?? null;
if ($bars !== null) {
    foreach ($bars as $bar) {
        $left = $totalTime > 0 ? ($bar['start'] / $totalTime) * 100 : 0;
        $width = $totalTime > 0 ? ($bar['duration'] / $totalTime) * 100 : 0;
        $width = max($width, 0.3);
        $tooltipAttr = isset($bar['title']) ? ' data-tooltip="' . $view->e($bar['title']) . '"' : '';
        echo '<div class="wpd-perf-wf-bar"' . $tooltipAttr . ' style="left:' . $view->e(sprintf('%.2f', $left)) . '%;width:' . $view->e(sprintf('%.2f', $width)) . '%;background:' . $view->e($color) . '"></div>';
    }
} else {
    $left = $totalTime > 0 ? ((float) ($entry['start'] ?? 0.0) / $totalTime) * 100 : 0;
    $width = $totalTime > 0 ? ((float) ($entry['duration'] ?? 0.0) / $totalTime) * 100 : 0;
    $width = max($width, 0.3);
    $barTitle = (string) ($entry['title'] ?? '');
    $tooltipAttr = $barTitle !== '' ? ' data-tooltip="' . $view->e($barTitle) . '"' : '';
    echo '<div class="wpd-perf-wf-bar"' . $tooltipAttr . ' style="left:' . $view->e(sprintf('%.2f', $left)) . '%;width:' . $view->e(sprintf('%.2f', $width)) . '%;background:' . $view->e($color) . '"></div>';
}
?>
</div>
<?php $displayValue = (float) ($entry['value'] ?? $entry['duration'] ?? 0.0); ?>
<div class="wpd-perf-wf-value"><?= $view->e($fmt->ms($displayValue)) ?></div>
</div>
