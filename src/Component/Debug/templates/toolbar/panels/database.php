<?php
/**
 * Database panel template.
 *
 * @var int                                                          $totalCount       Total query count
 * @var float                                                        $totalTime        Total query time in ms
 * @var int                                                          $duplicateCount   Duplicate query count
 * @var int                                                          $slowCount        Slow query count
 * @var list<string>                                                 $suggestions      Optimization suggestions
 * @var list<array>                                                  $queries          Query records
 * @var \WpPack\Component\Debug\Toolbar\Panel\TemplateFormatters     $fmt              Template formatters
 * @var float                                                        $requestTimeFloat Request start timestamp
 */
?>
<div class="wpd-section">
<h4 class="wpd-section-title">Summary</h4>
<table class="wpd-table wpd-table-kv">
<?= $this->include('toolbar/partials/table-row', ['key' => 'Total Queries', 'value' => (string) $totalCount]) ?>
<?= $this->include('toolbar/partials/table-row', ['key' => 'Total Time', 'value' => $fmt->ms($totalTime)]) ?>
<?= $this->include('toolbar/partials/table-row', ['key' => 'Duplicate Queries', 'value' => (string) $duplicateCount, 'valueClass' => $duplicateCount > 0 ? 'wpd-text-yellow' : '']) ?>
<?= $this->include('toolbar/partials/table-row', ['key' => 'Slow Queries', 'value' => (string) $slowCount, 'valueClass' => $slowCount > 0 ? 'wpd-text-red' : '']) ?>
</table>
</div>
<?php if (!empty($suggestions)): ?>
<div class="wpd-section">
<h4 class="wpd-section-title">Suggestions</h4>
<ul class="wpd-suggestions">
<?php foreach ($suggestions as $suggestion): ?>
<li class="wpd-suggestion-item"><?= $this->e($suggestion) ?></li>
<?php endforeach; ?>
</ul>
</div>
<?php endif; ?>
<?php if (!empty($queries)):
    // Caller grouping
    $callerStats = [];
    foreach ($queries as $query) {
        $caller = $query['caller'];
        $callerStats[$caller] ??= ['count' => 0, 'total_time' => 0.0];
        $callerStats[$caller]['count']++;
        $callerStats[$caller]['total_time'] += $query['time'];
    }
    uasort($callerStats, static fn(array $a, array $b): int => $b['total_time'] <=> $a['total_time']);
?>
<div class="wpd-section">
<h4 class="wpd-section-title">Queries by Caller</h4>
<table class="wpd-table wpd-table-full">
<thead><tr>
<th>Caller</th>
<th class="wpd-col-right">Count</th>
<th class="wpd-col-right">Total Time</th>
<th class="wpd-col-right">Avg Time</th>
</tr></thead>
<tbody>
<?php foreach ($callerStats as $caller => $stats):
    $avgTime = $stats['total_time'] / $stats['count'];
    $countClass = $stats['count'] > 5 ? ' wpd-text-yellow' : '';
    $shortCaller = $caller;
    $parts = preg_split('/,\s*/', $caller);
    if ($parts !== false && count($parts) > 1) {
        $shortCaller = end($parts);
    }
?>
<tr>
<td title="<?= $this->e($caller) ?>"><span class="wpd-caller"><?= $this->e($shortCaller) ?></span></td>
<td class="wpd-col-right<?= $countClass ?>"><?= $this->e((string) $stats['count']) ?></td>
<td class="wpd-col-right"><?= $this->e($fmt->ms($stats['total_time'])) ?></td>
<td class="wpd-col-right"><?= $this->e($fmt->ms($avgTime)) ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
<?php
    // Count duplicates for highlighting
    $sqlCounts = [];
    foreach ($queries as $query) {
        $sql = $query['sql'];
        $sqlCounts[$sql] = ($sqlCounts[$sql] ?? 0) + 1;
    }
?>
<div class="wpd-section">
<h4 class="wpd-section-title">Queries</h4>
<table class="wpd-table wpd-table-full">
<thead><tr>
<th class="wpd-col-num">#</th>
<th class="wpd-col-reltime">Time</th>
<th class="wpd-col-sql">SQL</th>
<th class="wpd-col-time">Duration</th>
<th class="wpd-col-caller">Caller</th>
</tr></thead>
<tbody>
<?php foreach ($queries as $index => $query):
    $sql = $query['sql'];
    $timeMs = (float) $query['time'];
    $isSlow = $timeMs > 100.0;
    $isDuplicate = ($sqlCounts[$sql] ?? 0) > 1;
    $rowClass = '';
    if ($isSlow) { $rowClass = 'wpd-row-slow'; }
    elseif ($isDuplicate) { $rowClass = 'wpd-row-duplicate'; }
    $badges = '';
    if ($isSlow) { $badges .= $this->include('toolbar/partials/badge', ['label' => 'SLOW', 'color' => 'red']); }
    if ($isDuplicate) { $badges .= $this->include('toolbar/partials/badge', ['label' => 'DUP', 'color' => 'yellow']); }
    $startTime = (float) ($query['start'] ?? 0);
    $relTime = $fmt->relativeTime($startTime, $requestTimeFloat);
?>
<tr class="<?= $rowClass ?>">
<td class="wpd-col-num"><?= $this->e((string) ($index + 1)) ?></td>
<td class="wpd-col-reltime wpd-text-dim"><?= $this->e($relTime) ?></td>
<td class="wpd-col-sql"><code><?= $this->e($sql) ?></code><?= $this->raw($badges) ?></td>
<td class="wpd-col-time"><?= $this->e($fmt->ms($timeMs)) ?></td>
<td class="wpd-col-caller"><span class="wpd-caller"><?= $this->e($query['caller']) ?></span></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
<?php endif; ?>
