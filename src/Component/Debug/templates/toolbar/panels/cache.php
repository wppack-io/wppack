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
$hitRateValue = '<span class="wpd-inline-bar"><span class="wpd-inline-bar-fill" style="width:' . $this->e(sprintf('%.1f', min($hitRate, 100))) . '%;background:' . $this->e($barColor) . '"></span></span>'
    . '<span class="' . $hitRateColor . '">' . $this->e(sprintf('%.1f%%', $hitRate)) . '</span>';
?>
<div class="wpd-section">
<h4 class="wpd-section-title">Object Cache</h4>
<table class="wpd-table wpd-table-kv">
<?php if ($dropin !== ''): ?>
<?= $this->include('toolbar/partials/table-row', ['key' => 'Drop-in', 'value' => $this->e($dropin)]) ?>
<?php endif; ?>
<?= $this->include('toolbar/partials/table-row', ['key' => 'Cache Hits', 'value' => (string) $hits]) ?>
<?= $this->include('toolbar/partials/table-row', ['key' => 'Cache Misses', 'value' => (string) $misses]) ?>
<?= $this->include('toolbar/partials/table-row', ['key' => 'Hit Rate', 'value' => $hitRateValue]) ?>
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
        default => $this->e((string) $op['expiration']) . ' s',
    };
    $opTag = $op['operation'] === 'set'
        ? $this->include('toolbar/partials/badge', ['label' => 'SET', 'color' => 'green'])
        : $this->include('toolbar/partials/badge', ['label' => 'DELETE', 'color' => 'red']);
?>
<tr>
<td class="wpd-col-num"><?= $this->e((string) ($index + 1)) ?></td>
<td><code><?= $this->e($op['name']) ?></code></td>
<td><?= $this->raw($opTag) ?></td>
<td class="wpd-col-right"><?= $this->raw($expDisplay) ?></td>
<td><span class="wpd-caller"><?= $this->e($op['caller']) ?></span></td>
</tr>
<?php endforeach; ?>
</tbody></table>
<?php else: ?>
<table class="wpd-table wpd-table-kv">
<?= $this->include('toolbar/partials/table-row', ['key' => 'Transient Sets', 'value' => (string) $transientSets]) ?>
<?= $this->include('toolbar/partials/table-row', ['key' => 'Transient Deletes', 'value' => (string) $transientDeletes]) ?>
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
<td><code><?= $this->e($group) ?></code></td>
<td class="wpd-col-right"><?= $this->e((string) $count) ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
<?php endif; ?>
