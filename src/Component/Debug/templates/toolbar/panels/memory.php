<?php
/**
 * Memory panel template.
 *
 * @var int                                                          $current         Current memory usage in bytes
 * @var int                                                          $peak            Peak memory usage in bytes
 * @var int                                                          $limit           Memory limit in bytes
 * @var float                                                        $usagePercentage Usage as percentage of limit
 * @var array<string,int>                                            $snapshots       Memory snapshots by checkpoint label
 * @var \WpPack\Component\Debug\Toolbar\Panel\TemplateFormatters     $fmt             Template formatters
 */
$usageColor = match (true) {
    $usagePercentage >= 90 => 'wpd-text-red',
    $usagePercentage >= 70 => 'wpd-text-yellow',
    default => 'wpd-text-green',
};
$barColor = match (true) {
    $usagePercentage >= 90 => 'var(--wpd-red)',
    $usagePercentage >= 70 => 'var(--wpd-yellow)',
    default => 'var(--wpd-green)',
};
$usageValue = $this->include('toolbar/partials/progress-bar', [
    'percentage' => $usagePercentage,
    'barColor' => $barColor,
    'textClass' => $usageColor,
    'label' => $fmt->percentage($usagePercentage),
]);
?>
<div class="wpd-section">
<h4 class="wpd-section-title">Summary</h4>
<table class="wpd-table wpd-table-kv">
<?= $this->include('toolbar/partials/table-row', ['key' => 'Current Usage', 'value' => $fmt->bytes($current)]) ?>
<?= $this->include('toolbar/partials/table-row', ['key' => 'Peak Usage', 'value' => $fmt->bytes($peak)]) ?>
<?= $this->include('toolbar/partials/table-row', ['key' => 'Memory Limit', 'value' => $limit > 0 ? $fmt->bytes($limit) : 'Unlimited']) ?>
<?= $this->include('toolbar/partials/table-row', ['key' => 'Usage', 'value' => $usageValue]) ?>
</table>
</div>
<?php if (!empty($snapshots)): ?>
<div class="wpd-section">
<h4 class="wpd-section-title">Memory Snapshots</h4>
<table class="wpd-table wpd-table-full">
<thead><tr>
<th>Checkpoint</th>
<th class="wpd-col-right">Memory</th>
<th class="wpd-col-right">Delta</th>
</tr></thead>
<tbody>
<?php
$previousMemory = 0;
foreach ($snapshots as $snapshotLabel => $snapshotMemory):
    $delta = $previousMemory > 0 ? $snapshotMemory - $previousMemory : 0;
    $deltaSign = $delta >= 0 ? '+' : '';
    $deltaClass = $delta > 1024 * 1024 ? ' wpd-text-yellow' : '';
?>
<tr>
<td><?= $this->e($snapshotLabel) ?></td>
<td class="wpd-col-right"><?= $this->e($fmt->bytes($snapshotMemory)) ?></td>
<td class="wpd-col-right<?= $deltaClass ?>"><?= $deltaSign ?><?= $this->e($fmt->bytes(abs($delta))) ?></td>
</tr>
<?php $previousMemory = $snapshotMemory; endforeach; ?>
</tbody></table>
</div>
<?php endif; ?>
