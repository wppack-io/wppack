<?php
/**
 * Container panel template.
 *
 * @var int                                                          $serviceCount   Total service count
 * @var int                                                          $publicCount    Public service count
 * @var int                                                          $privateCount   Private service count
 * @var int                                                          $autowiredCount Autowired service count
 * @var int                                                          $lazyCount      Lazy service count
 * @var array                                                        $services       Service definitions
 * @var list<string>                                                 $compilerPasses Compiler pass class names
 * @var array                                                        $taggedServices Tagged service groups
 * @var array                                                        $parameters     Container parameters
 * @var \WPPack\Component\Debug\Toolbar\Panel\TemplateFormatters     $fmt            Template formatters
 */
?>
<div class="wpd-section">
<h4 class="wpd-section-title">Summary</h4>
<div class="wpd-perf-cards">
<?= $view->include('toolbar/partials/perf-card', ['label' => 'Services', 'value' => (string) $serviceCount, 'unit' => '', 'sub' => '']) ?>
<?= $view->include('toolbar/partials/perf-card', ['label' => 'Public', 'value' => (string) $publicCount, 'unit' => '', 'sub' => '']) ?>
<?= $view->include('toolbar/partials/perf-card', ['label' => 'Private', 'value' => (string) $privateCount, 'unit' => '', 'sub' => '']) ?>
<?= $view->include('toolbar/partials/perf-card', ['label' => 'Autowired', 'value' => (string) $autowiredCount, 'unit' => '', 'sub' => '']) ?>
<?= $view->include('toolbar/partials/perf-card', ['label' => 'Lazy', 'value' => (string) $lazyCount, 'unit' => '', 'sub' => '']) ?>
</div>
</div>
<?php if (!empty($compilerPasses)): ?>
<div class="wpd-section">
<h4 class="wpd-section-title">Compiler Passes (<?= count($compilerPasses) ?>)</h4>
<div class="wpd-tag-list">
<?php foreach ($compilerPasses as $pass):
    $shortName = substr(strrchr($pass, '\\') ?: $pass, 1) ?: $pass;
    ?>
<span class="wpd-tag"><?= $view->e($shortName) ?></span>
<?php endforeach; ?>
</div>
</div>
<?php endif; ?>
<?php if (!empty($taggedServices)): ?>
<div class="wpd-section">
<h4 class="wpd-section-title">Tagged Services</h4>
<table class="wpd-table wpd-table-full">
<thead><tr>
<th>Tag</th>
<th>Services</th>
</tr></thead>
<tbody>
<?php foreach ($taggedServices as $tag => $serviceIds): ?>
<tr>
<td><code><?= $view->e($tag) ?></code></td>
<td><div class="wpd-tag-list"><?php foreach ($serviceIds as $id): ?><span class="wpd-tag"><?= $view->e($id) ?></span><?php endforeach; ?></div></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
<?php endif; ?>
<?php if (!empty($services)): ?>
<div class="wpd-section">
<h4 class="wpd-section-title">Services (<?= $serviceCount ?>)</h4>
<table class="wpd-table wpd-table-full">
<thead><tr>
<th>Service ID</th>
<th>Class</th>
<th>Scope</th>
<th>Flags</th>
</tr></thead>
<tbody>
<?php foreach ($services as $id => $info):
    $class = (string) ($info['class'] ?? $id);
    $isPublic = (bool) ($info['public'] ?? false);
    $isAutowired = (bool) ($info['autowired'] ?? false);
    $isLazy = (bool) ($info['lazy'] ?? false);
    ?>
<tr>
<td><code><?= $view->e($id) ?></code></td>
<td class="wpd-text-dim"><?= $view->e($class) ?></td>
<td><?php if ($isPublic): ?><span class="wpd-text-green">public</span><?php else: ?><span class="wpd-text-dim">private</span><?php endif; ?></td>
<td><?php if ($isAutowired): ?><?= $view->include('toolbar/partials/badge', ['label' => 'autowired', 'color' => 'primary']) ?> <?php endif; ?><?php if ($isLazy): ?><?= $view->include('toolbar/partials/badge', ['label' => 'lazy', 'color' => 'yellow']) ?><?php endif; ?><?php if (!$isAutowired && !$isLazy): ?>-<?php endif; ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
<?php endif; ?>
<?php if (!empty($parameters)): ?>
<?= $view->include('toolbar/partials/key-value-section', ['title' => 'Parameters', 'items' => $parameters, 'fmt' => $fmt]) ?>
<?php endif; ?>
