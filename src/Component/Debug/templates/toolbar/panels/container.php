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
 * @var \WpPack\Component\Debug\Toolbar\Panel\TemplateFormatters     $fmt            Template formatters
 */
?>
<div class="wpd-section">
<h4 class="wpd-section-title">Summary</h4>
<div class="wpd-perf-cards">
<?= $this->include('toolbar/partials/perf-card', ['label' => 'Services', 'value' => (string) $serviceCount, 'unit' => '', 'sub' => '']) ?>
<?= $this->include('toolbar/partials/perf-card', ['label' => 'Public', 'value' => (string) $publicCount, 'unit' => '', 'sub' => '']) ?>
<?= $this->include('toolbar/partials/perf-card', ['label' => 'Private', 'value' => (string) $privateCount, 'unit' => '', 'sub' => '']) ?>
<?= $this->include('toolbar/partials/perf-card', ['label' => 'Autowired', 'value' => (string) $autowiredCount, 'unit' => '', 'sub' => '']) ?>
<?= $this->include('toolbar/partials/perf-card', ['label' => 'Lazy', 'value' => (string) $lazyCount, 'unit' => '', 'sub' => '']) ?>
</div>
</div>
<?php if (!empty($compilerPasses)): ?>
<div class="wpd-section">
<h4 class="wpd-section-title">Compiler Passes (<?= count($compilerPasses) ?>)</h4>
<div class="wpd-tag-list">
<?php foreach ($compilerPasses as $pass):
    $shortName = substr(strrchr($pass, '\\') ?: $pass, 1) ?: $pass;
?>
<span class="wpd-tag"><?= $this->e($shortName) ?></span>
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
<td><code><?= $this->e($tag) ?></code></td>
<td><div class="wpd-tag-list"><?php foreach ($serviceIds as $id): ?><span class="wpd-tag"><?= $this->e($id) ?></span><?php endforeach; ?></div></td>
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
<td><code><?= $this->e($id) ?></code></td>
<td class="wpd-text-dim"><?= $this->e($class) ?></td>
<td><?php if ($isPublic): ?><span class="wpd-text-green">public</span><?php else: ?><span class="wpd-text-dim">private</span><?php endif; ?></td>
<td><?php if ($isAutowired): ?><?= $this->include('toolbar/partials/badge', ['label' => 'autowired', 'color' => 'primary']) ?> <?php endif; ?><?php if ($isLazy): ?><?= $this->include('toolbar/partials/badge', ['label' => 'lazy', 'color' => 'yellow']) ?><?php endif; ?><?php if (!$isAutowired && !$isLazy): ?>-<?php endif; ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
<?php endif; ?>
<?php if (!empty($parameters)): ?>
<?= $this->include('toolbar/partials/key-value-section', ['title' => 'Parameters', 'items' => $parameters, 'fmt' => $fmt]) ?>
<?php endif; ?>
