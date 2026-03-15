<?php
/**
 * Widget panel template.
 *
 * @var int                                                          $totalWidgets   Total registered widgets
 * @var int                                                          $totalSidebars  Total sidebars
 * @var int                                                          $activeWidgets  Active widget count
 * @var float                                                        $renderTime     Widget render time in ms
 * @var array                                                        $sidebars       Sidebar data
 * @var list<array>                                                  $sidebarTimings Sidebar render timing records
 * @var \WpPack\Component\Debug\Toolbar\Panel\TemplateFormatters     $fmt            Template formatters
 */
?>
<div class="wpd-section">
<h4 class="wpd-section-title">Summary</h4>
<table class="wpd-table wpd-table-kv">
<?= $this->include('toolbar/partials/table-row', ['key' => 'Total Sidebars', 'value' => (string) $totalSidebars]) ?>
<?= $this->include('toolbar/partials/table-row', ['key' => 'Total Widgets', 'value' => (string) $totalWidgets]) ?>
<?= $this->include('toolbar/partials/table-row', ['key' => 'Active Widgets', 'value' => (string) $activeWidgets]) ?>
<?php if ($renderTime > 0): ?>
<?= $this->include('toolbar/partials/table-row', ['key' => 'Render Time', 'value' => $fmt->ms($renderTime)]) ?>
<?php endif; ?>
</table>
</div>
<?php if (!empty($sidebarTimings)): ?>
<div class="wpd-section">
<h4 class="wpd-section-title">Sidebar Render Times</h4>
<table class="wpd-table wpd-table-full">
<thead><tr>
<th>Sidebar</th>
<th class="wpd-col-time">Duration</th>
</tr></thead>
<tbody>
<?php foreach ($sidebarTimings as $timing): ?>
<tr>
<td><?= $this->e($timing['name']) ?></td>
<td class="wpd-col-time"><?= $this->e($fmt->ms($timing['duration'])) ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
<?php endif; ?>
<?php if (!empty($sidebars)): ?>
<div class="wpd-section">
<h4 class="wpd-section-title">Sidebars</h4>
<table class="wpd-table wpd-table-full">
<thead><tr>
<th>Name</th>
<th>Widgets</th>
</tr></thead>
<tbody>
<?php foreach ($sidebars as $id => $sidebar):
    $widgetTags = '';
    if (!empty($sidebar['widgets'])) {
        foreach ($sidebar['widgets'] as $widget) {
            $widgetTags .= '<span class="wpd-tag">' . $this->e($widget) . '</span>';
        }
    } else {
        $widgetTags = '<span class="wpd-text-dim">empty</span>';
    }
?>
<tr>
<td><?= $this->e($sidebar['name']) ?></td>
<td><div class="wpd-tag-list"><?= $this->raw($widgetTags) ?></div></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
<?php endif; ?>
