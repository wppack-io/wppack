<?php
/**
 * Router panel template.
 *
 * @var array<string,mixed>                                          $data Router data
 * @var \WpPack\Component\Debug\Toolbar\Panel\TemplateFormatters     $fmt  Template formatters
 */
$template = (string) ($data['template'] ?? '');
$templatePath = (string) ($data['template_path'] ?? '');
$matchedRule = (string) ($data['matched_rule'] ?? '');
$matchedQuery = (string) ($data['matched_query'] ?? '');
$queryType = (string) ($data['query_type'] ?? '');
$is404 = (bool) ($data['is_404'] ?? false);
$rewriteRulesCount = (int) ($data['rewrite_rules_count'] ?? 0);
$isBlockTheme = (bool) ($data['is_block_theme'] ?? false);

if ($isBlockTheme):
    $blockTemplate = $data['block_template'] ?? [];
    $slug = (string) ($blockTemplate['slug'] ?? '');
    $templateId = (string) ($blockTemplate['id'] ?? '');
    $source = (string) ($blockTemplate['source'] ?? '');
    $hasThemeFile = (bool) ($blockTemplate['has_theme_file'] ?? false);
    $filePath = (string) ($blockTemplate['file_path'] ?? '');
    $sourceLabel = $source === 'theme' ? 'Theme file' : ($source !== '' ? 'User customized (DB)' : '-');
?>
<div class="wpd-section">
<h4 class="wpd-section-title">Block Template (FSE)</h4>
<table class="wpd-table wpd-table-kv">
<?= $this->include('toolbar/partials/table-row', ['key' => 'Template Slug', 'value' => $this->e($slug ?: '-')]) ?>
<?= $this->include('toolbar/partials/table-row', ['key' => 'Template ID', 'value' => $templateId !== '' ? '<code>' . $this->e($templateId) . '</code>' : '-']) ?>
<?= $this->include('toolbar/partials/table-row', ['key' => 'Source', 'value' => $this->e($sourceLabel)]) ?>
<?= $this->include('toolbar/partials/table-row', ['key' => 'Has Theme File', 'value' => $fmt->value($hasThemeFile)]) ?>
<?= $this->include('toolbar/partials/table-row', ['key' => 'File Path', 'value' => $this->e($filePath ?: '-')]) ?>
</table>
</div>
<?php
    $parts = $blockTemplate['parts'] ?? [];
    if (!empty($parts)):
?>
<div class="wpd-section">
<h4 class="wpd-section-title">Template Parts</h4>
<table class="wpd-table wpd-table-full">
<thead><tr><th>Slug</th><th>Area</th><th>Source</th></tr></thead>
<tbody>
<?php foreach ($parts as $part):
    $partSource = (string) ($part['source'] ?? '');
    $partSourceLabel = $partSource === 'theme' ? 'Theme file' : ($partSource !== '' ? 'User customized (DB)' : '-');
?>
<tr>
<td><code><?= $this->e((string) ($part['slug'] ?? '')) ?></code></td>
<td><?= $this->e((string) ($part['area'] ?? '')) ?></td>
<td><?= $this->e($partSourceLabel) ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
<?php endif; ?>
<?php else: ?>
<div class="wpd-section">
<h4 class="wpd-section-title">Template (Classic)</h4>
<table class="wpd-table wpd-table-kv">
<?= $this->include('toolbar/partials/table-row', ['key' => 'Template', 'value' => $this->e($template ?: '-')]) ?>
<?= $this->include('toolbar/partials/table-row', ['key' => 'Template Path', 'value' => $this->e($templatePath ?: '-')]) ?>
</table>
</div>
<?php endif; ?>
<div class="wpd-section">
<h4 class="wpd-section-title">Route</h4>
<table class="wpd-table wpd-table-kv">
<?= $this->include('toolbar/partials/table-row', ['key' => 'Query Type', 'value' => $this->e($queryType ?: '-')]) ?>
<?= $this->include('toolbar/partials/table-row', ['key' => 'Matched Rule', 'value' => $matchedRule !== '' ? '<code>' . $this->e($matchedRule) . '</code>' : '-']) ?>
<?= $this->include('toolbar/partials/table-row', ['key' => 'Matched Query', 'value' => $this->e($matchedQuery ?: '-')]) ?>
<?= $this->include('toolbar/partials/table-row', ['key' => '404', 'value' => $fmt->value($is404)]) ?>
<?= $this->include('toolbar/partials/table-row', ['key' => 'Rewrite Rules', 'value' => (string) $rewriteRulesCount]) ?>
</table>
</div>
<?php
$queryVars = $data['query_vars'] ?? [];
if (!empty($queryVars)) {
    echo $this->include('toolbar/partials/key-value-section', ['title' => 'Query Variables', 'items' => $queryVars, 'fmt' => $fmt]);
}
$conditionals = [];
if ($data['is_front_page'] ?? false) { $conditionals[] = 'is_front_page'; }
if ($data['is_singular'] ?? false) { $conditionals[] = 'is_singular'; }
if ($data['is_archive'] ?? false) { $conditionals[] = 'is_archive'; }
if ($is404) { $conditionals[] = 'is_404'; }
if (!empty($conditionals)):
?>
<div class="wpd-section">
<h4 class="wpd-section-title">Conditional Tags</h4>
<div class="wpd-tag-list">
<?php foreach ($conditionals as $tag): ?>
<span class="wpd-tag wpd-text-green"><?= $this->e($tag) ?></span>
<?php endforeach; ?>
</div>
</div>
<?php endif; ?>
