<?php
/**
 * Plugin panel template.
 *
 * @var int                                                          $totalPlugins  Active plugin count
 * @var float                                                        $totalHookTime Total hook time in ms
 * @var string                                                       $slowestPlugin Slowest plugin slug
 * @var array                                                        $plugins       Plugin data keyed by slug
 * @var list<string>                                                 $dropins       Drop-in file names
 * @var array                                                        $assetData     Script/style asset data
 * @var \WpPack\Component\Debug\Toolbar\Panel\TemplateFormatters     $fmt           Template formatters
 */
$muPluginsList = array_filter($plugins, static fn(array $info): bool => (bool) ($info['is_mu'] ?? false));
$regularPluginsList = array_filter($plugins, static fn(array $info): bool => !((bool) ($info['is_mu'] ?? false)));
$allScripts = $assetData['scripts'] ?? [];
$allStyles = $assetData['styles'] ?? [];
?>
<div class="wpd-plugin-list">
<div class="wpd-section">
<h4 class="wpd-section-title">Summary</h4>
<table class="wpd-table wpd-table-kv">
<?= $this->include('toolbar/partials/table-row', ['key' => 'Active Plugins', 'value' => (string) $totalPlugins]) ?>
<?= $this->include('toolbar/partials/table-row', ['key' => 'Total Hook Time', 'value' => $fmt->ms($totalHookTime)]) ?>
</table>
</div>
<?php if (!empty($muPluginsList)): ?>
<div class="wpd-section">
<h4 class="wpd-section-title">Must-Use Plugins</h4>
<table class="wpd-table wpd-table-full">
<thead><tr>
<th>Plugin</th>
<th>Version</th>
<th class="wpd-col-right">Load</th>
<th class="wpd-col-right">Hook Time</th>
<th class="wpd-col-right">Queries</th>
</tr></thead>
<tbody>
<?php foreach ($muPluginsList as $slug => $info):
    $name = (string) ($info['name'] ?? $slug);
    $version = (string) ($info['version'] ?? '');
    $loadTime = (float) ($info['load_time'] ?? 0.0);
    $hookTime = (float) ($info['hook_time'] ?? 0.0);
    $queryCount = (int) ($info['query_count'] ?? 0);
    $slowTag = ($slug === $slowestPlugin) ? ' ' . $this->include('toolbar/partials/badge', ['label' => 'Slow', 'color' => 'yellow']) : '';
?>
<tr>
<td><span class="wpd-plugin-detail-link" data-plugin="<?= $this->e($slug) ?>"><?= $this->e($name) ?></span><?= $this->raw($slowTag) ?></td>
<td><?= $version !== '' ? $this->e($version) : '-' ?></td>
<td class="wpd-col-right"><?= $loadTime > 0 ? $this->e($fmt->ms($loadTime)) : '-' ?></td>
<td class="wpd-col-right"><?= $this->e($fmt->ms($hookTime)) ?></td>
<td class="wpd-col-right"><?= $queryCount > 0 ? $this->e((string) $queryCount) : '-' ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
<?php endif; ?>
<?php if (!empty($regularPluginsList)): ?>
<div class="wpd-section">
<h4 class="wpd-section-title">Plugins</h4>
<table class="wpd-table wpd-table-full">
<thead><tr>
<th>Plugin</th>
<th>Version</th>
<th class="wpd-col-right">Load</th>
<th class="wpd-col-right">Hook Time</th>
<th class="wpd-col-right">Queries</th>
</tr></thead>
<tbody>
<?php foreach ($regularPluginsList as $slug => $info):
    $name = (string) ($info['name'] ?? $slug);
    $version = (string) ($info['version'] ?? '');
    $loadTime = (float) ($info['load_time'] ?? 0.0);
    $hookTime = (float) ($info['hook_time'] ?? 0.0);
    $queryCount = (int) ($info['query_count'] ?? 0);
    $slowTag = ($slug === $slowestPlugin) ? ' ' . $this->include('toolbar/partials/badge', ['label' => 'Slow', 'color' => 'yellow']) : '';
?>
<tr>
<td><span class="wpd-plugin-detail-link" data-plugin="<?= $this->e($slug) ?>"><?= $this->e($name) ?></span><?= $this->raw($slowTag) ?></td>
<td><?= $version !== '' ? $this->e($version) : '-' ?></td>
<td class="wpd-col-right"><?= $loadTime > 0 ? $this->e($fmt->ms($loadTime)) : '-' ?></td>
<td class="wpd-col-right"><?= $this->e($fmt->ms($hookTime)) ?></td>
<td class="wpd-col-right"><?= $queryCount > 0 ? $this->e((string) $queryCount) : '-' ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
<?php endif; ?>
<?php if (!empty($dropins)): ?>
<div class="wpd-section">
<h4 class="wpd-section-title">Drop-ins</h4>
<ul class="wpd-list">
<?php foreach ($dropins as $dropin): ?>
<li><code><?= $this->e($dropin) ?></code></li>
<?php endforeach; ?>
</ul>
</div>
<?php endif; ?>
</div>
<?php foreach ($plugins as $slug => $info):
    $name = (string) ($info['name'] ?? $slug);
    $version = (string) ($info['version'] ?? '');
    $loadTime = (float) ($info['load_time'] ?? 0.0);
    $hookTimePl = (float) ($info['hook_time'] ?? 0.0);
    $queryTime = (float) ($info['query_time'] ?? 0.0);
    $hookCount = (int) ($info['hook_count'] ?? 0);
    $listenerCount = (int) ($info['listener_count'] ?? 0);
    $hooks = $info['hooks'] ?? [];
    $enqueuedStylesPl = $info['enqueued_styles'] ?? [];
    $enqueuedScriptsPl = $info['enqueued_scripts'] ?? [];
    $isMu = (bool) ($info['is_mu'] ?? false);
?>
<div class="wpd-plugin-detail" data-plugin="<?= $this->e($slug) ?>" style="display:none">
<div style="margin-bottom:12px">
<button class="wpd-plugin-back" data-action="plugin-back">&larr; Back to Plugins</button>
</div>
<div class="wpd-section">
<h4 class="wpd-section-title"><?= $isMu ? 'MU Plugin Info' : 'Plugin Info' ?></h4>
<table class="wpd-table wpd-table-kv">
<?= $this->include('toolbar/partials/table-row', ['key' => 'Name', 'value' => $this->e($name)]) ?>
<?php if ($version !== ''): ?>
<?= $this->include('toolbar/partials/table-row', ['key' => 'Version', 'value' => $this->e($version)]) ?>
<?php endif; ?>
</table>
</div>
<div class="wpd-section">
<h4 class="wpd-section-title">Timing</h4>
<div class="wpd-perf-cards">
<?php if ($loadTime > 0):
    [$lv, $lu] = $fmt->msCard($loadTime);
    echo $this->include('toolbar/partials/perf-card', ['label' => 'Load Time', 'value' => $lv, 'unit' => $lu, 'sub' => '']);
else:
    echo $this->include('toolbar/partials/perf-card', ['label' => 'Load Time', 'value' => '-', 'unit' => '', 'sub' => '']);
endif;
[$hv, $hu] = $fmt->msCard($hookTimePl);
echo $this->include('toolbar/partials/perf-card', ['label' => 'Hook Time', 'value' => $hv, 'unit' => $hu, 'sub' => $this->e((string) $hookCount) . ' hooks, ' . $this->e((string) $listenerCount) . ' listeners']);
if ($queryTime > 0):
    [$qv, $qu] = $fmt->msCard($queryTime);
    echo $this->include('toolbar/partials/perf-card', ['label' => 'Query Time', 'value' => $qv, 'unit' => $qu, 'sub' => '']);
else:
    echo $this->include('toolbar/partials/perf-card', ['label' => 'Query Time', 'value' => '-', 'unit' => '', 'sub' => '']);
endif;
?>
</div>
</div>
<?php if (!empty($hooks)): ?>
<div class="wpd-section">
<h4 class="wpd-section-title">Hook Breakdown</h4>
<table class="wpd-table wpd-table-full">
<thead><tr><th>Hook</th><th class="wpd-col-right">Listeners</th><th class="wpd-col-right">Time</th></tr></thead>
<tbody>
<?php foreach ($hooks as $hookInfo): ?>
<tr>
<td><code><?= $this->e($hookInfo['hook']) ?></code></td>
<td class="wpd-col-right"><?= $this->e((string) $hookInfo['listeners']) ?></td>
<td class="wpd-col-right"><?= $this->e($fmt->ms((float) $hookInfo['time'])) ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
<?php endif; ?>
<?php if (!empty($enqueuedStylesPl) || !empty($enqueuedScriptsPl)): ?>
<?= $this->include('toolbar/partials/asset-tables', [
    'styleHandles' => $enqueuedStylesPl,
    'scriptHandles' => $enqueuedScriptsPl,
    'allStyles' => $allStyles,
    'allScripts' => $allScripts,
    'fmt' => $fmt,
]) ?>
<?php endif; ?>
</div>
<?php endforeach; ?>
