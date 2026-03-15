<?php
/**
 * Cache panel template.
 *
 * @var int                                                          $hits             Cache hit count
 * @var int                                                          $misses           Cache miss count
 * @var float                                                        $hitRate          Cache hit rate percentage
 * @var int                                                          $transientSets    Transient set count
 * @var int                                                          $transientDeletes Transient delete count
 * @var string                                                       $dropin           Object cache drop-in name
 * @var list<array>                                                  $transientOps     Transient operation log
 * @var array<string,int>                                            $cacheGroups      Cache group entry counts
 * @var \WpPack\Component\Debug\Toolbar\Panel\TemplateFormatters     $fmt              Template formatters
 */
$hitRateColor = match (true) {
    $hitRate >= 80 => 'wpd-text-green',
    $hitRate >= 50 => 'wpd-text-yellow',
    default => 'wpd-text-red',
};
$barColor = match (true) {
    $hitRate >= 80 => 'var(--wpd-green)',
    $hitRate >= 50 => 'var(--wpd-yellow)',
    default => 'var(--wpd-red)',
};
$hitRateValue = $view->include('toolbar/partials/progress-bar', [
    'percentage' => $hitRate,
    'barColor' => $barColor,
    'textClass' => $hitRateColor,
    'label' => $fmt->percentage($hitRate),
]);
?>
<div class="wpd-section">
<h4 class="wpd-section-title">Object Cache</h4>
<table class="wpd-table wpd-table-kv">
<?php if ($dropin !== ''): ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Drop-in', 'value' => $view->e($dropin)]) ?>
<?php endif; ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Cache Hits', 'value' => (string) $hits]) ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Cache Misses', 'value' => (string) $misses]) ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Hit Rate', 'value' => $hitRateValue]) ?>
</table>
</div>
<div class="wpd-section">
<h4 class="wpd-section-title">Transients</h4>
<?php if (!empty($transientOps)): ?>
<table class="wpd-table wpd-table-full">
<thead><tr>
<th class="wpd-col-num">#</th>
<th>Name</th>
<th>Operation</th>
<th class="wpd-col-right">Expiration</th>
<th>Caller</th>
</tr></thead>
<tbody>
<?php foreach ($transientOps as $index => $op):
    $expDisplay = match (true) {
        $op['operation'] === 'delete' => "\xe2\x80\x94",
        $op['expiration'] === 0 => 'none',
        default => $view->e((string) $op['expiration']) . ' s',
    };
    $opTag = $op['operation'] === 'set'
        ? $view->include('toolbar/partials/badge', ['label' => 'SET', 'color' => 'green'])
        : $view->include('toolbar/partials/badge', ['label' => 'DELETE', 'color' => 'red']);
    ?>
<tr>
<td class="wpd-col-num"><?= $view->e((string) ($index + 1)) ?></td>
<td><code><?= $view->e($op['name']) ?></code></td>
<td><?= $view->raw($opTag) ?></td>
<td class="wpd-col-right"><?= $view->raw($expDisplay) ?></td>
<td><span class="wpd-caller"><?= $view->e($op['caller']) ?></span></td>
</tr>
<?php endforeach; ?>
</tbody></table>
<?php else: ?>
<table class="wpd-table wpd-table-kv">
<?= $view->include('toolbar/partials/table-row', ['key' => 'Transient Sets', 'value' => (string) $transientSets]) ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Transient Deletes', 'value' => (string) $transientDeletes]) ?>
</table>
<?php endif; ?>
</div>
<?php if (!empty($cacheGroups)): ?>
<div class="wpd-section">
<h4 class="wpd-section-title">Cache Groups</h4>
<table class="wpd-table wpd-table-full">
<thead><tr><th>Group</th><th class="wpd-col-right">Entries</th></tr></thead>
<tbody>
<?php foreach ($cacheGroups as $group => $count): ?>
<tr>
<td><code><?= $view->e($group) ?></code></td>
<td class="wpd-col-right"><?= $view->e((string) $count) ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
<?php endif; ?>
