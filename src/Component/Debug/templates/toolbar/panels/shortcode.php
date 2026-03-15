<?php
/**
 * Shortcode panel template.
 *
 * @var int                                                          $totalCount     Total registered shortcodes
 * @var int                                                          $usedCount      Shortcodes used in content
 * @var float                                                        $executionTime  Total execution time in ms
 * @var list<string>                                                 $usedShortcodes Used shortcode tags
 * @var array                                                        $shortcodes     All shortcode info
 * @var list<array>                                                  $executions     Execution time records
 * @var \WpPack\Component\Debug\Toolbar\Panel\TemplateFormatters     $fmt            Template formatters
 */
?>
<div class="wpd-section">
<h4 class="wpd-section-title">Summary</h4>
<table class="wpd-table wpd-table-kv">
<?= $this->include('toolbar/partials/table-row', ['key' => 'Total Registered', 'value' => (string) $totalCount]) ?>
<?= $this->include('toolbar/partials/table-row', ['key' => 'Used in Content', 'value' => (string) $usedCount]) ?>
<?php if ($executionTime > 0): ?>
<?= $this->include('toolbar/partials/table-row', ['key' => 'Execution Time', 'value' => $fmt->ms($executionTime)]) ?>
<?php endif; ?>
</table>
</div>
<?php if (!empty($executions)): ?>
<div class="wpd-section">
<h4 class="wpd-section-title">Execution Times</h4>
<table class="wpd-table wpd-table-full">
<thead><tr>
<th>Tag</th>
<th class="wpd-col-time">Duration</th>
</tr></thead>
<tbody>
<?php foreach ($executions as $exec): ?>
<tr>
<td><code>[<?= $this->e($exec['tag']) ?>]</code></td>
<td class="wpd-col-time"><?= $this->e($fmt->ms($exec['duration'])) ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
<?php endif; ?>
<?php if (!empty($usedShortcodes)): ?>
<div class="wpd-section">
<h4 class="wpd-section-title">Used in Current Page</h4>
<div class="wpd-tag-list">
<?php foreach ($usedShortcodes as $tag): ?>
<span class="wpd-tag wpd-text-green"><?= $this->e($tag) ?></span>
<?php endforeach; ?>
</div>
</div>
<?php endif; ?>
<?php if (!empty($shortcodes)): ?>
<div class="wpd-section">
<h4 class="wpd-section-title">All Shortcodes</h4>
<table class="wpd-table wpd-table-full">
<thead><tr>
<th>Tag</th>
<th>Callback</th>
<th>Used</th>
</tr></thead>
<tbody>
<?php foreach ($shortcodes as $info):
    $usedHtml = $info['used']
        ? '<span class="wpd-text-green">Yes</span>'
        : '<span class="wpd-text-dim">No</span>';
?>
<tr>
<td><code><?= $this->e($info['tag']) ?></code></td>
<td class="wpd-text-dim"><?= $this->e($info['callback']) ?></td>
<td><?= $this->raw($usedHtml) ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
<?php endif; ?>
