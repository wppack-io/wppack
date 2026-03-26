<?php
/**
 * Performance panel template.
 *
 * @var list<array{label: string, value: string, unit: string, sub: string}> $overviewCards
 * @var float                                                                $totalTime
 * @var list<array{label: string, time: float, color: string}>              $segments
 * @var float                                                               $timelineTotal
 * @var list<array>                                                         $timelineEntries
 * @var list<array>                                                         $pluginTimelineEntries
 * @var list<array>                                                         $themeTimelineEntries
 * @var array<string, string>                                               $categoryColors
 * @var array<string, string>                                               $categoryLabels
 * @var list<string>                                                        $usedCategories
 * @var \WpPack\Component\Debug\Toolbar\Panel\TemplateFormatters            $fmt
 * @var float                                                               $unmeasuredMs
 * @var float                                                               $unmeasuredPct
 */
?>
<div class="wpd-section">
<h4 class="wpd-section-title">Overview</h4>
<div class="wpd-perf-cards">
<?php foreach ($overviewCards as $card): ?>
<?= $view->include('toolbar/partials/perf-card', $card) ?>
<?php endforeach; ?>
</div>
</div>
<?php if ($segments !== []): ?>
<div class="wpd-section">
<h4 class="wpd-section-title">Time Distribution</h4>
<div class="wpd-perf-dist-bar">
<?php foreach ($segments as $seg):
    $pct = ($seg['time'] / $totalTime) * 100;
    if ($pct > 0):
        ?>
<div class="wpd-perf-dist-segment" style="width:<?= $view->e(sprintf('%.2f', $pct)) ?>%;background:<?= $view->e($seg['color']) ?>"></div>
<?php endif; endforeach; ?>
</div>
<div class="wpd-perf-dist-legend">
<?php foreach ($segments as $seg):
    if ($seg['time'] > 0):
        $pct = ($seg['time'] / $totalTime) * 100;
        ?>
<span class="wpd-perf-legend-item"><span class="wpd-perf-legend-color" style="background:<?= $view->e($seg['color']) ?>"></span> <?= $view->e($seg['label']) ?> <?= $view->e($fmt->ms($seg['time'])) ?> (<?= $view->e(sprintf('%.1f%%', $pct)) ?>)</span>
<?php endif; endforeach; ?>
</div>
</div>
<?php endif; ?>
<?php
$hasTimeline = $timelineEntries !== [] || $pluginTimelineEntries !== [] || $themeTimelineEntries !== [];
if ($hasTimeline):
    ?>
<div class="wpd-section">
<h4 class="wpd-section-title">Timeline</h4>
<div class="wpd-perf-waterfall">
<?php foreach ($timelineEntries as $entry):
    $color = $categoryColors[$entry['category']] ?? $categoryColors['default'];
    ?>
<?= $view->include('toolbar/partials/timeline-row', ['entry' => $entry, 'color' => $color, 'totalTime' => $timelineTotal, 'fmt' => $fmt, 'unmeasuredMs' => $unmeasuredMs, 'unmeasuredPct' => $unmeasuredPct]) ?>
<?php endforeach; ?>
<?php if ($pluginTimelineEntries !== []): ?>
<div class="wpd-perf-wf-divider"><span>Plugins</span></div>
<?php foreach ($pluginTimelineEntries as $entry): ?>
<?= $view->include('toolbar/partials/timeline-row', ['entry' => $entry, 'color' => $categoryColors['plugin'], 'totalTime' => $timelineTotal, 'fmt' => $fmt, 'unmeasuredMs' => $unmeasuredMs, 'unmeasuredPct' => $unmeasuredPct]) ?>
<?php endforeach; ?>
<?php endif; ?>
<?php if ($themeTimelineEntries !== []): ?>
<div class="wpd-perf-wf-divider"><span>Theme</span></div>
<?php foreach ($themeTimelineEntries as $entry): ?>
<?= $view->include('toolbar/partials/timeline-row', ['entry' => $entry, 'color' => $categoryColors['theme_hooks'], 'totalTime' => $timelineTotal, 'fmt' => $fmt, 'unmeasuredMs' => $unmeasuredMs, 'unmeasuredPct' => $unmeasuredPct]) ?>
<?php endforeach; ?>
<?php endif; ?>
</div>
<div class="wpd-perf-dist-legend">
<?php foreach ($usedCategories as $cat):
    $color = $categoryColors[$cat] ?? $categoryColors['default'];
    $label = $categoryLabels[$cat] ?? ucfirst($cat);
    ?>
<span class="wpd-perf-legend-item"><span class="wpd-perf-legend-color" style="background:<?= $view->e($color) ?>"></span> <?= $view->e($label) ?></span>
<?php endforeach; ?>
</div>
</div>
<?php endif; ?>
