<?php
/**
 * Asset panel template.
 *
 * @var int                                                          $enqueuedScripts   Enqueued script count
 * @var int                                                          $enqueuedStyles    Enqueued style count
 * @var int                                                          $registeredScripts Registered script count
 * @var int                                                          $registeredStyles  Registered style count
 * @var array                                                        $scripts           Script asset data
 * @var array                                                        $styles            Style asset data
 * @var \WpPack\Component\Debug\Toolbar\Panel\TemplateFormatters     $fmt               Template formatters
 */
?>
<div class="wpd-section">
<h4 class="wpd-section-title">Summary</h4>
<table class="wpd-table wpd-table-kv">
<?= $view->include('toolbar/partials/table-row', ['key' => 'Enqueued Scripts', 'value' => (string) $enqueuedScripts]) ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Enqueued Styles', 'value' => (string) $enqueuedStyles]) ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Registered Scripts', 'value' => (string) $registeredScripts]) ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Registered Styles', 'value' => (string) $registeredStyles]) ?>
</table>
</div>
<?php
$enqueuedScriptsList = array_filter($scripts, static fn(array $s): bool => (bool) ($s['enqueued'] ?? false));
if (!empty($enqueuedScriptsList)):
    ?>
<div class="wpd-section">
<h4 class="wpd-section-title">Enqueued Scripts (<?= count($enqueuedScriptsList) ?>)</h4>
<table class="wpd-table wpd-table-full">
<thead><tr>
<th>Handle</th>
<th>Source</th>
<th>Version</th>
<th>Footer</th>
</tr></thead>
<tbody>
<?php foreach ($enqueuedScriptsList as $handle => $info):
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
</div>
<?php endif; ?>
<?php
$enqueuedStylesList = array_filter($styles, static fn(array $s): bool => (bool) ($s['enqueued'] ?? false));
if (!empty($enqueuedStylesList)):
    ?>
<div class="wpd-section">
<h4 class="wpd-section-title">Enqueued Styles (<?= count($enqueuedStylesList) ?>)</h4>
<table class="wpd-table wpd-table-full">
<thead><tr>
<th>Handle</th>
<th>Source</th>
<th>Version</th>
<th>Media</th>
</tr></thead>
<tbody>
<?php foreach ($enqueuedStylesList as $handle => $info):
    $src = (string) ($info['src'] ?? '');
    $version = (string) ($info['version'] ?? '');
    $media = (string) ($info['media'] ?? 'all');
    ?>
<tr>
<td><code><?= $view->e($handle) ?></code></td>
<td class="wpd-text-dim" style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= $src !== '' ? $view->e($src) : '-' ?></td>
<td><?= $version !== '' ? $view->e($version) : '-' ?></td>
<td><?= $view->e($media) ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
<?php endif; ?>
