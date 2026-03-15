<?php
/**
 * Admin panel template.
 *
 * @var bool        $isAdmin       Whether in admin context
 * @var string      $pageHook      Current admin page hook
 * @var array       $screen        Current screen info
 * @var list<array> $adminMenus    Admin menu items
 * @var list<array> $adminBarNodes Admin bar nodes
 * @var int         $totalMenus    Total menu count
 * @var int         $totalSubmenus Total submenu count
 */
if (!$isAdmin): ?>
<div class="wpd-section"><h4 class="wpd-section-title">Admin</h4><p class="wpd-text-dim">Not in admin context.</p></div>
<?php return; endif; ?>
<div class="wpd-section">
<h4 class="wpd-section-title">Current Screen</h4>
<table class="wpd-table wpd-table-kv">
<?= $view->include('toolbar/partials/table-row', ['key' => 'Page Hook', 'value' => $view->e($pageHook ?: '-')]) ?>
<?php if (!empty($screen)): ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Screen ID', 'value' => $view->e((string) ($screen['id'] ?? '-'))]) ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Base', 'value' => $view->e((string) ($screen['base'] ?? '-'))]) ?>
<?php if (($screen['post_type'] ?? '') !== ''): ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Post Type', 'value' => $view->e($screen['post_type'])]) ?>
<?php endif; ?>
<?php if (($screen['taxonomy'] ?? '') !== ''): ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Taxonomy', 'value' => $view->e($screen['taxonomy'])]) ?>
<?php endif; ?>
<?php endif; ?>
</table>
</div>
<?php if (!empty($adminMenus)): ?>
<div class="wpd-section">
<h4 class="wpd-section-title">Admin Menus (<?= $totalMenus ?> menus, <?= $totalSubmenus ?> submenus)</h4>
<table class="wpd-table wpd-table-full">
<thead><tr>
<th>Menu</th>
<th>Slug</th>
<th>Capability</th>
</tr></thead>
<tbody>
<?php foreach ($adminMenus as $menuItem): ?>
<tr>
<td><?= $view->e($menuItem['title']) ?></td>
<td><code><?= $view->e($menuItem['slug']) ?></code></td>
<td><?= $view->e($menuItem['capability']) ?></td>
</tr>
<?php if (isset($menuItem['submenu'])): ?>
<?php foreach ($menuItem['submenu'] as $subItem): ?>
<tr>
<td style="padding-left:24px" class="wpd-text-dim"><?= $view->e($subItem['title']) ?></td>
<td><code class="wpd-text-dim"><?= $view->e($subItem['slug']) ?></code></td>
<td></td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
<?php endforeach; ?>
</tbody></table>
</div>
<?php endif; ?>
<?php if (!empty($adminBarNodes)): ?>
<div class="wpd-section">
<h4 class="wpd-section-title">Admin Bar Nodes</h4>
<table class="wpd-table wpd-table-full">
<thead><tr>
<th>ID</th>
<th>Title</th>
</tr></thead>
<tbody>
<?php foreach ($adminBarNodes as $node): ?>
<tr>
<td><code><?= $view->e($node['id']) ?></code></td>
<td><?= $view->e($node['title']) ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
<?php endif; ?>
