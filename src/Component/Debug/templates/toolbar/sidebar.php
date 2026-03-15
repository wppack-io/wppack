<?php
/**
 * @var list<list<string>> $groups
 * @var list<string> $unknownNames
 * @var array $collectors
 * @var array<string, string> $iconMap
 * @var array<string, string> $labelMap
 */
$groupIndex = 0;
foreach ($groups as $visibleItems):
    if (empty($visibleItems)) {
        continue;
    }
    if ($groupIndex > 0):
        ?>
<div class="wpd-sidebar-divider"></div>
<?php endif; ?>
<?php foreach ($visibleItems as $key): ?>
<button class="wpd-sidebar-item" data-panel="<?= $view->e($key) ?>">
<span class="wpd-sidebar-icon"><?= $view->raw($iconMap[$key] ?? '') ?></span>
<span class="wpd-sidebar-label"><?= $view->e($labelMap[$key] ?? ucfirst($key)) ?></span>
</button>
<?php endforeach; ?>
<?php $groupIndex++; endforeach; ?>
<?php if (!empty($unknownNames)):
    if ($groupIndex > 0):
        ?>
<div class="wpd-sidebar-divider"></div>
<?php endif; ?>
<?php foreach ($unknownNames as $key): ?>
<button class="wpd-sidebar-item" data-panel="<?= $view->e($key) ?>">
<span class="wpd-sidebar-icon"><?= $view->raw($iconMap[$key] ?? '') ?></span>
<span class="wpd-sidebar-label"><?= $view->e($labelMap[$key] ?? ucfirst($key)) ?></span>
</button>
<?php endforeach; ?>
<?php endif; ?>
