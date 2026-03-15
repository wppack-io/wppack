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
<?php foreach ($taggedServices as $tag => $serviceIds):
    $tags = '';
    foreach ($serviceIds as $id) {
        $tags .= '<span class="wpd-tag">' . $this->e($id) . '</span>';
    }
?>
<tr>
<td><code><?= $this->e($tag) ?></code></td>
<td><div class="wpd-tag-list"><?= $this->raw($tags) ?></div></td>
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
    $scope = $isPublic
        ? '<span class="wpd-text-green">public</span>'
        : '<span class="wpd-text-dim">private</span>';
    $flags = '';
    if ($isAutowired) {
        $flags .= $this->include('toolbar/partials/badge', ['label' => 'autowired', 'color' => 'primary']) . ' ';
    }
    if ($isLazy) {
        $flags .= $this->include('toolbar/partials/badge', ['label' => 'lazy', 'color' => 'yellow']) . ' ';
    }
?>
<tr>
<td><code><?= $this->e($id) ?></code></td>
<td class="wpd-text-dim"><?= $this->e($class) ?></td>
<td><?= $this->raw($scope) ?></td>
<td><?= $flags !== '' ? $this->raw($flags) : '-' ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
<?php endif; ?>
<?php if (!empty($parameters)): ?>
<?= $this->include('toolbar/partials/key-value-section', ['title' => 'Parameters', 'items' => $parameters, 'fmt' => $fmt]) ?>
<?php endif; ?>
