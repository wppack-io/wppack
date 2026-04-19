<?php
/**
 * Theme panel template.
 *
 * @var array<string,mixed>                                          $data      Theme data
 * @var array                                                        $assetData Script/style asset data
 * @var \WPPack\Component\Debug\Toolbar\Panel\TemplateFormatters     $fmt       Template formatters
 */
$name = (string) ($data['name'] ?? '');
$version = (string) ($data['version'] ?? '');
$isChildTheme = (bool) ($data['is_child_theme'] ?? false);
$isBlockTheme = (bool) ($data['is_block_theme'] ?? false);
$setupTime = (float) ($data['setup_time'] ?? 0.0);
$renderTime = (float) ($data['render_time'] ?? 0.0);
$hookTime = (float) ($data['hook_time'] ?? 0.0);
$hookCount = (int) ($data['hook_count'] ?? 0);
$listenerCount = (int) ($data['listener_count'] ?? 0);
$templateFile = (string) ($data['template_file'] ?? '');
$templateParts = $data['template_parts'] ?? [];
$bodyClasses = $data['body_classes'] ?? [];
$conditionalTags = $data['conditional_tags'] ?? [];
$enqueuedStyles = $data['enqueued_styles'] ?? [];
$enqueuedScripts = $data['enqueued_scripts'] ?? [];
$hooks = $data['hooks'] ?? [];
$allScripts = $assetData['scripts'] ?? [];
$allStyles = $assetData['styles'] ?? [];
?>
<div class="wpd-section">
<h4 class="wpd-section-title">Info</h4>
<table class="wpd-table wpd-table-kv">
<?= $view->include('toolbar/partials/table-row', ['key' => 'Name', 'value' => $view->e($name ?: '-')]) ?>
<?php if ($version !== ''): ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Version', 'value' => $view->e($version)]) ?>
<?php endif; ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Child Theme', 'value' => $fmt->value($isChildTheme)]) ?>
<?php if ($isChildTheme): ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Child', 'value' => $view->e((string) ($data['child_theme'] ?? ''))]) ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Parent', 'value' => $view->e((string) ($data['parent_theme'] ?? ''))]) ?>
<?php endif; ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Block Theme', 'value' => $fmt->value($isBlockTheme)]) ?>
</table>
</div>
<div class="wpd-section">
<h4 class="wpd-section-title">Timing</h4>
<div class="wpd-perf-cards">
<?php
[$sv, $su] = $fmt->msCard($setupTime);
echo $view->include('toolbar/partials/perf-card', ['label' => 'Setup Time', 'value' => $sv, 'unit' => $su, 'sub' => '']);
[$rv, $ru] = $fmt->msCard($renderTime);
echo $view->include('toolbar/partials/perf-card', ['label' => 'Render Time', 'value' => $rv, 'unit' => $ru, 'sub' => '']);
[$hv, $hu] = $fmt->msCard($hookTime);
echo $view->include('toolbar/partials/perf-card', ['label' => 'Hook Time', 'value' => $hv, 'unit' => $hu, 'sub' => $view->e((string) $hookCount) . ' hooks, ' . $view->e((string) $listenerCount) . ' listeners']);
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
<td><code><?= $view->e($hookInfo['hook']) ?></code></td>
<td class="wpd-col-right"><?= $view->e((string) $hookInfo['listeners']) ?></td>
<td class="wpd-col-right"><?= $view->e($fmt->ms($hookInfo['time'])) ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
<?php endif; ?>
<?php if ($templateFile !== '' || !empty($templateParts)): ?>
<div class="wpd-section">
<h4 class="wpd-section-title">Template</h4>
<table class="wpd-table wpd-table-kv">
<?php if ($templateFile !== ''): ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Template File', 'value' => '<code>' . $view->e($templateFile) . '</code>']) ?>
<?php endif; ?>
<?php if (!empty($templateParts)): ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Template Parts', 'value' => '<code>' . $view->e(implode(', ', $templateParts)) . '</code>']) ?>
<?php endif; ?>
</table>
</div>
<?php endif; ?>
<?php if (!empty($conditionalTags)): ?>
<div class="wpd-section">
<h4 class="wpd-section-title">Conditional Tags</h4>
<div class="wpd-tag-list">
<?php foreach ($conditionalTags as $tag => $value): ?>
<span class="wpd-tag <?= $value ? 'wpd-text-green' : 'wpd-text-dim' ?>"><?= $view->e($tag) ?></span>
<?php endforeach; ?>
</div>
</div>
<?php endif; ?>
<?php if (!empty($enqueuedStyles) || !empty($enqueuedScripts)): ?>
<?= $view->include('toolbar/partials/asset-tables', [
    'styleHandles' => $enqueuedStyles,
    'scriptHandles' => $enqueuedScripts,
    'allStyles' => $allStyles,
    'allScripts' => $allScripts,
    'fmt' => $fmt,
]) ?>
<?php endif; ?>
<?php if (!empty($bodyClasses)): ?>
<div class="wpd-section">
<h4 class="wpd-section-title">Body Classes</h4>
<div class="wpd-tag-list">
<?php foreach ($bodyClasses as $class): ?>
<span class="wpd-tag"><?= $view->e($class) ?></span>
<?php endforeach; ?>
</div>
</div>
<?php endif; ?>
