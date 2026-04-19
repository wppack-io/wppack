<?php
/**
 * Asset tables partial (Styles + Scripts).
 *
 * @var list<string>                        $styleHandles
 * @var list<string>                        $scriptHandles
 * @var array<string, array<string, mixed>> $allStyles
 * @var array<string, array<string, mixed>> $allScripts
 * @var \WPPack\Component\Debug\Toolbar\Panel\TemplateFormatters $fmt
 */
?>
<div class="wpd-section">
<h4 class="wpd-section-title">Enqueued Assets</h4>
<?php if (!empty($styleHandles)): ?>
<div class="wpd-table-label">Styles</div>
<table class="wpd-table wpd-table-full">
<thead><tr>
<th>Handle</th>
<th>Source</th>
<th>Version</th>
<th>Media</th>
</tr></thead>
<tbody>
<?php foreach ($styleHandles as $handle):
    $info = $allStyles[$handle] ?? [];
    $src = (string) ($info['src'] ?? '');
    $version = (string) ($info['version'] ?? '');
    $media = (string) ($info['media'] ?? '');
    ?>
<tr>
<td><code><?= $view->e($handle) ?></code></td>
<td class="wpd-text-dim" style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= $src !== '' ? $view->e($src) : '-' ?></td>
<td><?= $version !== '' ? $view->e($version) : '-' ?></td>
<td><?= $media !== '' ? $view->e($media) : '-' ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
<?php endif; ?>
<?php if (!empty($scriptHandles)): ?>
<?php if (!empty($styleHandles)): ?>
<div style="margin-top:8px"></div>
<?php endif; ?>
<div class="wpd-table-label">Scripts</div>
<table class="wpd-table wpd-table-full">
<thead><tr>
<th>Handle</th>
<th>Source</th>
<th>Version</th>
<th>Footer</th>
</tr></thead>
<tbody>
<?php foreach ($scriptHandles as $handle):
    $info = $allScripts[$handle] ?? [];
    $src = (string) ($info['src'] ?? '');
    $version = (string) ($info['version'] ?? '');
    $inFooter = (bool) ($info['in_footer'] ?? false);
    ?>
<tr>
<td><code><?= $view->e($handle) ?></code></td>
<td class="wpd-text-dim" style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= $src !== '' ? $view->e($src) : '-' ?></td>
<td><?= $version !== '' ? $view->e($version) : '-' ?></td>
<td><?= $view->raw($fmt->value($inFooter)) ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
<?php endif; ?>
</div>
