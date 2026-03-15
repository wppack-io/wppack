<?php
/**
 * Scheduler panel template.
 *
 * @var int                                                          $cronTotal     Total WP-Cron events
 * @var int                                                          $cronOverdue   Overdue cron event count
 * @var bool                                                         $asAvailable   Whether Action Scheduler is available
 * @var string                                                       $asVersion     Action Scheduler version
 * @var int                                                          $asPending     AS pending action count
 * @var int                                                          $asFailed      AS failed action count
 * @var int                                                          $asComplete    AS completed action count
 * @var bool                                                         $cronDisabled  Whether WP-Cron is disabled
 * @var bool                                                         $alternateCron Whether alternate cron is enabled
 * @var list<array>                                                  $cronEvents    Cron event entries
 * @var \WpPack\Component\Debug\Toolbar\Panel\TemplateFormatters     $fmt           Template formatters
 */
?>
<div class="wpd-section">
<h4 class="wpd-section-title">Summary</h4>
<table class="wpd-table wpd-table-kv">
<?= $view->include('toolbar/partials/table-row', ['key' => 'WP-Cron Events', 'value' => (string) $cronTotal]) ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Overdue', 'value' => (string) $cronOverdue, 'valueClass' => $cronOverdue > 0 ? 'wpd-text-red' : '']) ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Action Scheduler', 'value' => $asAvailable ? 'Available' . ($asVersion !== '' ? ' (v' . $view->e($asVersion) . ')' : '') : 'Not available']) ?>
<?php if ($asAvailable): ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'AS Pending', 'value' => (string) $asPending, 'valueClass' => $asPending > 0 ? 'wpd-text-yellow' : '']) ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'AS Failed', 'value' => (string) $asFailed, 'valueClass' => $asFailed > 0 ? 'wpd-text-red' : '']) ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'AS Complete', 'value' => (string) $asComplete]) ?>
<?php endif; ?>
</table>
</div>
<div class="wpd-section">
<h4 class="wpd-section-title">Configuration</h4>
<table class="wpd-table wpd-table-kv">
<?= $view->include('toolbar/partials/table-row', ['key' => 'DISABLE_WP_CRON', 'value' => $fmt->value($cronDisabled)]) ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'ALTERNATE_WP_CRON', 'value' => $fmt->value($alternateCron)]) ?>
</table>
</div>
<?php if (!empty($cronEvents)): ?>
<div class="wpd-section">
<h4 class="wpd-section-title">WP-Cron Events</h4>
<table class="wpd-table wpd-table-full">
<thead><tr>
<th>Hook</th>
<th>Schedule</th>
<th>Next Run</th>
<th class="wpd-col-right">Callbacks</th>
</tr></thead>
<tbody>
<?php foreach ($cronEvents as $event):
    $isOverdue = (bool) ($event['is_overdue'] ?? false);
    $rowClass = $isOverdue ? 'wpd-row-slow' : '';
    ?>
<tr class="<?= $rowClass ?>">
<td><code><?= $view->e((string) ($event['hook'] ?? '')) ?></code></td>
<td><span class="wpd-tag"><?= $view->e((string) ($event['schedule'] ?? '')) ?></span></td>
<td><?= $view->e((string) ($event['next_run_relative'] ?? '')) ?><?php if ($isOverdue): ?> <?= $view->include('toolbar/partials/badge', ['label' => 'OVERDUE', 'color' => 'red']) ?><?php endif; ?></td>
<td class="wpd-col-right"><?= $view->e((string) ($event['callbacks'] ?? 0)) ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
<?php endif; ?>
