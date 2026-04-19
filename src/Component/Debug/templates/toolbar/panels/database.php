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
 * @var array<string,array{count:int,total_time:float}>              $callerStats      Caller statistics (sorted)
 * @var array<string,string>                                         $shortCallers     Short caller names
 * @var array<string,int>                                            $sqlCounts        SQL duplicate counts
 * @var \WPPack\Component\Debug\Toolbar\Panel\TemplateFormatters     $fmt              Template formatters
 * @var float                                                        $requestTimeFloat Request start timestamp
 */
?>
<div class="wpd-section">
<h4 class="wpd-section-title">Summary</h4>
<table class="wpd-table wpd-table-kv">
<?= $view->include('toolbar/partials/table-row', ['key' => 'Total Queries', 'value' => (string) $totalCount]) ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Total Time', 'value' => $fmt->ms($totalTime)]) ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Duplicate Queries', 'value' => (string) $duplicateCount, 'valueClass' => $duplicateCount > 0 ? 'wpd-text-yellow' : '']) ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Slow Queries', 'value' => (string) $slowCount, 'valueClass' => $slowCount > 0 ? 'wpd-text-red' : '']) ?>
</table>
</div>
<?php if (!empty($suggestions)): ?>
<div class="wpd-section">
<h4 class="wpd-section-title">Suggestions</h4>
<ul class="wpd-suggestions">
<?php foreach ($suggestions as $suggestion): ?>
<li class="wpd-suggestion-item"><?= $view->e($suggestion) ?></li>
<?php endforeach; ?>
</ul>
</div>
<?php endif; ?>
<?php if (!empty($queries)): ?>
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
    ?>
<tr>
<td title="<?= $view->e($caller) ?>"><span class="wpd-caller"><?= $view->e($shortCallers[$caller] ?? $caller) ?></span></td>
<td class="wpd-col-right<?= $countClass ?>"><?= $view->e((string) $stats['count']) ?></td>
<td class="wpd-col-right"><?= $view->e($fmt->ms($stats['total_time'])) ?></td>
<td class="wpd-col-right"><?= $view->e($fmt->ms($avgTime)) ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
<?php /* sqlCounts provided by renderer */ ?>
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
    $dupKey = \WPPack\Component\Debug\DataCollector\DatabaseDataCollector::dupKey($sql, $query['params'] ?? []);
    $isDuplicate = ($sqlCounts[$dupKey] ?? 0) > 1;
    $rowClass = $isSlow ? 'wpd-row-slow' : ($isDuplicate ? 'wpd-row-duplicate' : '');
    $startTime = (float) ($query['start'] ?? 0);
    $relTime = $fmt->relativeTime($startTime, $requestTimeFloat);
    ?>
<tr class="<?= $rowClass ?>">
<td class="wpd-col-num"><?= $view->e((string) ($index + 1)) ?></td>
<td class="wpd-col-reltime wpd-text-dim"><?= $view->e($relTime) ?></td>
<td class="wpd-col-sql">
<code><?= $view->e($sql) ?></code>
<?php if ($isSlow): ?><?= $view->include('toolbar/partials/badge', ['label' => 'SLOW', 'color' => 'red']) ?><?php endif; ?>
<?php if ($isDuplicate): ?><?= $view->include('toolbar/partials/badge', ['label' => 'DUP', 'color' => 'yellow']) ?><?php endif; ?>
<?php $params = $query['params'] ?? [];
    if (!empty($params)): ?>
<div class="wpd-params">
<span class="wpd-params-label">params</span>
<?php foreach ($params as $pi => $pv): ?>
<span class="wpd-param">
<span class="wpd-param-index">#<?= $view->e((string) ($pi + 1)) ?></span>
<span class="wpd-param-type"><?= $view->e($fmt->paramType($pv)) ?></span>
<code class="wpd-param-value"><?= $view->e($fmt->paramValue($pv)) ?></code>
</span>
<?php endforeach; ?>
</div>
<?php endif; ?>
</td>
<td class="wpd-col-time"><?= $view->e($fmt->ms($timeMs)) ?></td>
<td class="wpd-col-caller"><span class="wpd-caller"><?= $view->e($query['caller']) ?></span></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
<?php endif; ?>
