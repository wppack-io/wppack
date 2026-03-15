<?php
/**
 * Translation panel template.
 *
 * @var int              $totalLookups  Total translation lookups
 * @var int              $missingCount  Missing translation count
 * @var list<string>     $loadedDomains Loaded text domains
 * @var array<string,int> $domainUsage  Lookup count per domain
 * @var list<array>      $missing       Missing translation entries
 */
?>
<div class="wpd-section">
<h4 class="wpd-section-title">Summary</h4>
<table class="wpd-table wpd-table-kv">
<?= $this->include('toolbar/partials/table-row', ['key' => 'Total Lookups', 'value' => (string) $totalLookups]) ?>
<?= $this->include('toolbar/partials/table-row', ['key' => 'Loaded Domains', 'value' => (string) count($loadedDomains)]) ?>
<?= $this->include('toolbar/partials/table-row', ['key' => 'Missing Translations', 'value' => (string) $missingCount, 'valueClass' => $missingCount > 0 ? 'wpd-text-yellow' : '']) ?>
</table>
</div>
<?php if (!empty($loadedDomains)): ?>
<div class="wpd-section">
<h4 class="wpd-section-title">Loaded Domains</h4>
<div class="wpd-tag-list">
<?php foreach ($loadedDomains as $domain): ?>
<span class="wpd-tag"><?= $this->e($domain) ?></span>
<?php endforeach; ?>
</div>
</div>
<?php endif; ?>
<?php if (!empty($domainUsage)): ?>
<div class="wpd-section">
<h4 class="wpd-section-title">Domain Usage</h4>
<table class="wpd-table wpd-table-full">
<thead><tr><th>Domain</th><th class="wpd-col-right">Lookups</th></tr></thead>
<tbody>
<?php foreach ($domainUsage as $domain => $count): ?>
<tr>
<td><code><?= $this->e((string) $domain) ?></code></td>
<td class="wpd-col-right"><?= $this->e((string) $count) ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
<?php endif; ?>
<?php if (!empty($missing)): ?>
<div class="wpd-section">
<h4 class="wpd-section-title">Missing Translations</h4>
<table class="wpd-table wpd-table-full">
<thead><tr>
<th class="wpd-col-num">#</th>
<th>Original</th>
<th>Domain</th>
</tr></thead>
<tbody>
<?php foreach ($missing as $index => $entry): ?>
<tr>
<td class="wpd-col-num"><?= $this->e((string) ($index + 1)) ?></td>
<td><code><?= $this->e($entry['original'] ?? '') ?></code></td>
<td><?= $this->e($entry['domain'] ?? '') ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
<?php endif; ?>
