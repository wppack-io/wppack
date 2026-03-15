<?php
/**
 * Stopwatch panel template.
 *
 * @var float                                                        $totalTime Total elapsed time in ms
 * @var array                                                        $events    Stopwatch event records
 * @var \WpPack\Component\Debug\Toolbar\Panel\TemplateFormatters     $fmt       Template formatters
 */
?>
<div class="wpd-section">
<h4 class="wpd-section-title">Summary</h4>
<table class="wpd-table wpd-table-kv">
<?= $view->include('toolbar/partials/table-row', ['key' => 'Total Time', 'value' => $fmt->ms($totalTime)]) ?>
</table>
</div>
<?php if (!empty($events)): ?>
<div class="wpd-section">
<h4 class="wpd-section-title">Stopwatch Events</h4>
<table class="wpd-table wpd-table-full">
<thead><tr>
<th class="wpd-col-reltime">Time</th>
<th>Event</th>
<th>Category</th>
<th class="wpd-col-right">Duration</th>
<th class="wpd-col-right">Memory</th>
</tr></thead>
<tbody>
<?php foreach ($events as $event):
    $relTime = '+' . number_format(max(0, (float) $event['start_time']), 0) . ' ms';
    ?>
<tr>
<td class="wpd-col-reltime wpd-text-dim"><?= $view->e($relTime) ?></td>
<td><?= $view->e($event['name']) ?></td>
<td><span class="wpd-tag"><?= $view->e($event['category']) ?></span></td>
<td class="wpd-col-right"><?= $view->e($fmt->ms($event['duration'])) ?></td>
<td class="wpd-col-right"><?= $view->e($fmt->bytes($event['memory'])) ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
<?php endif; ?>
