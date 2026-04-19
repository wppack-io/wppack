<?php
/**
 * Event panel template.
 *
 * @var int                                                          $totalFirings   Total hook firings
 * @var int                                                          $uniqueHooks    Unique hook count
 * @var int                                                          $registeredHooks Registered hook count
 * @var int                                                          $orphanHooks    Orphan hook count
 * @var array<string,int>                                            $topHooks       Top hooks by firing count
 * @var array                                                        $hookTimings    Hook timing data
 * @var array<string,int>                                            $listenerCounts Listener count per hook
 * @var array                                                        $componentSummary Component-level summary
 * @var \WPPack\Component\Debug\Toolbar\Panel\TemplateFormatters     $fmt            Template formatters
 */
?>
<div class="wpd-section">
<h4 class="wpd-section-title">Summary</h4>
<table class="wpd-table wpd-table-kv">
<?= $view->include('toolbar/partials/table-row', ['key' => 'Total Firings', 'value' => (string) $totalFirings]) ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Unique Hooks', 'value' => (string) $uniqueHooks]) ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Registered Hooks', 'value' => (string) $registeredHooks]) ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Orphan Hooks', 'value' => (string) $orphanHooks, 'valueClass' => $orphanHooks > 0 ? 'wpd-text-yellow' : '']) ?>
</table>
</div>
<?php if (!empty($componentSummary)): ?>
<div class="wpd-section">
<h4 class="wpd-section-title">Component Summary</h4>
<table class="wpd-table wpd-table-full">
<thead><tr>
<th>Component</th>
<th>Type</th>
<th class="wpd-col-right">Hooks</th>
<th class="wpd-col-right">Listeners</th>
<th class="wpd-col-right">Duration</th>
</tr></thead>
<tbody>
<?php foreach ($componentSummary as $component => $summary): ?>
<tr>
<td><code><?= $view->e((string) $component) ?></code></td>
<td><?php
    $typeColor = match ($summary['type']) {
        'plugin' => 'purple',
        'theme' => 'rust',
        'core' => 'primary',
        default => null,
    };
    if ($typeColor !== null) {
        echo $view->include('toolbar/partials/badge', ['label' => $summary['type'], 'color' => $typeColor]);
    } else {
        echo '<span class="wpd-tag">' . $view->e($summary['type']) . '</span>';
    }
    ?></td>
<td class="wpd-col-right"><?= $view->e((string) $summary['hooks']) ?></td>
<td class="wpd-col-right"><?= $view->e((string) $summary['listeners']) ?></td>
<td class="wpd-col-right"><?= $view->e($fmt->ms((float) $summary['total_time'])) ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
<?php endif; ?>
<?php if (!empty($topHooks)): ?>
<div class="wpd-section">
<h4 class="wpd-section-title">Top Hooks</h4>
<table class="wpd-table wpd-table-full">
<thead><tr>
<th class="wpd-col-num">#</th>
<th>Hook</th>
<th class="wpd-col-right">Firings</th>
<th class="wpd-col-right">Listeners</th>
<th class="wpd-col-right">Time</th>
<th class="wpd-col-right">Duration</th>
</tr></thead>
<tbody>
<?php $index = 0;
    foreach ($topHooks as $hook => $count):
        $listeners = $listenerCounts[$hook] ?? 0;
        $timing = $hookTimings[$hook] ?? null;
        $duration = $timing !== null ? $fmt->ms($timing['total_time']) : '-';
        $hookStart = $timing !== null ? '+' . number_format(max(0, $timing['start']), 0) . ' ms' : '-';
        ?>
<tr>
<td class="wpd-col-num"><?= $view->e((string) (++$index)) ?></td>
<td><code><?= $view->e($hook) ?></code></td>
<td class="wpd-col-right"><?= $view->e((string) $count) ?></td>
<td class="wpd-col-right"><?= $view->e((string) $listeners) ?></td>
<td class="wpd-col-right wpd-text-dim"><?= $view->e($hookStart) ?></td>
<td class="wpd-col-right"><?= $view->e($duration) ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
<?php endif; ?>
