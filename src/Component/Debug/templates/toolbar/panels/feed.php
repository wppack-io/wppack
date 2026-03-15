<?php
/**
 * Feed panel template.
 *
 * @var int                                                          $totalCount    Total feed count
 * @var int                                                          $customCount   Custom feed count
 * @var bool                                                         $feedDiscovery Whether feed discovery is enabled
 * @var list<array>                                                  $feeds         Feed entries
 * @var \WpPack\Component\Debug\Toolbar\Panel\TemplateFormatters     $fmt           Template formatters
 */
?>
<div class="wpd-section">
<h4 class="wpd-section-title">Summary</h4>
<table class="wpd-table wpd-table-kv">
<?= $view->include('toolbar/partials/table-row', ['key' => 'Total Feeds', 'value' => (string) $totalCount]) ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Custom Feeds', 'value' => (string) $customCount]) ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Feed Discovery', 'value' => $fmt->value($feedDiscovery)]) ?>
</table>
</div>
<?php if (!empty($feeds)): ?>
<div class="wpd-section">
<h4 class="wpd-section-title">Feeds</h4>
<table class="wpd-table wpd-table-full">
<thead><tr>
<th>Type</th>
<th>URL</th>
<th>Custom</th>
</tr></thead>
<tbody>
<?php foreach ($feeds as $feed):
    $customHtml = $feed['is_custom']
        ? '<span class="wpd-text-yellow">Yes</span>'
        : '<span class="wpd-text-dim">No</span>';
    ?>
<tr>
<td><code><?= $view->e($feed['type']) ?></code></td>
<td class="wpd-text-dim" style="max-width:400px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= $view->e($feed['url']) ?></td>
<td><?= $view->raw($customHtml) ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
<?php endif; ?>
