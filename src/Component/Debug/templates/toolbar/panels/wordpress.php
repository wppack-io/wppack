<?php
/**
 * WordPress panel template.
 *
 * @var array                                                        $wpData     WordPress environment data
 * @var array                                                        $themeData  Active theme data
 * @var array                                                        $pluginData Plugin data
 * @var \WPPack\Component\Debug\Toolbar\Panel\TemplateFormatters     $fmt        Template formatters
 */
?>
<div class="wpd-section">
<h4 class="wpd-section-title">WordPress</h4>
<table class="wpd-table wpd-table-kv">
<?= $view->include('toolbar/partials/table-row', ['key' => 'Version', 'value' => (string) ($wpData['wp_version'] ?? 'N/A')]) ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Environment', 'value' => (string) ($wpData['environment_type'] ?? 'N/A')]) ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Multisite', 'value' => ($wpData['is_multisite'] ?? false) ? 'Yes' : 'No']) ?>
</table>
</div>
<?php
$constants = $wpData['constants'] ?? [];
if (!empty($constants)):
    ?>
<div class="wpd-section">
<h4 class="wpd-section-title">Debug Constants</h4>
<table class="wpd-table wpd-table-kv">
<?php foreach ($constants as $constant => $value):
    $display = match ($value) {
        null => '<span class="wpd-text-dim">undefined</span>',
        true => '<span class="wpd-text-green">true</span>',
        false => '<span class="wpd-text-red">false</span>',
    };
    ?>
<tr><td class="wpd-kv-key"><?= $view->e($constant) ?></td><td class="wpd-kv-val"><?= $view->raw($display) ?></td></tr>
<?php endforeach; ?>
</table>
</div>
<?php endif; ?>
<div class="wpd-section">
<h4 class="wpd-section-title">Active Theme</h4>
<table class="wpd-table wpd-table-kv">
<?php
    $themeName = (string) ($themeData['name'] ?? 'N/A');
echo $view->include('toolbar/partials/table-row', ['key' => 'Name', 'value' => $themeName]);
if ($themeName !== 'N/A') {
    $isBlockTheme = (bool) ($themeData['is_block_theme'] ?? false);
    $themeExists = (bool) ($themeData['exists'] ?? true);
    $themeTypeLabel = match (true) {
        $isBlockTheme => 'Block (FSE)',
        $themeExists => 'Classic',
        default => 'Unknown',
    };
    echo $view->include('toolbar/partials/table-row', ['key' => 'Type', 'value' => '<span class="wpd-tag">' . $view->e($themeTypeLabel) . '</span>']);
}
$isChildTheme = (bool) ($themeData['is_child_theme'] ?? false);
if ($isChildTheme) {
    echo $view->include('toolbar/partials/table-row', ['key' => 'Parent Theme', 'value' => $view->e((string) ($themeData['parent_theme'] ?? ''))]);
}
$themeVersion = (string) ($themeData['version'] ?? '');
if ($themeVersion !== '') {
    echo $view->include('toolbar/partials/table-row', ['key' => 'Version', 'value' => $view->e($themeVersion)]);
}
?>
</table>
</div>
<?php
$allPlugins = $pluginData['plugins'] ?? [];
$muPlugins = [];
$activePlugins = [];
foreach ($allPlugins as $slug => $info) {
    if ($info['is_mu'] ?? false) {
        $muPlugins[$slug] = (string) ($info['name'] ?? $slug);
    } else {
        $activePlugins[$slug] = (string) ($info['name'] ?? $slug);
    }
}
if (!empty($muPlugins)):
    ?>
<div class="wpd-section">
<h4 class="wpd-section-title">Must-Use Plugins (<?= $view->e((string) count($muPlugins)) ?>)</h4>
<ul class="wpd-list">
<?php foreach ($muPlugins as $muPlugin): ?>
<li><?= $view->e($muPlugin) ?></li>
<?php endforeach; ?>
</ul>
</div>
<?php endif; ?>
<?php if (!empty($activePlugins)): ?>
<div class="wpd-section">
<h4 class="wpd-section-title">Active Plugins (<?= $view->e((string) count($activePlugins)) ?>)</h4>
<ul class="wpd-list">
<?php foreach ($activePlugins as $plugin): ?>
<li><?= $view->e($plugin) ?></li>
<?php endforeach; ?>
</ul>
</div>
<?php endif; ?>
