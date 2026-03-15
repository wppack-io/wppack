<?php
/**
 * Dump panel template.
 *
 * @var list<array> $dumps      Dump call records
 * @var int         $totalCount Total dump count
 */
if (empty($dumps)): ?>
<div class="wpd-section"><p class="wpd-text-dim">No dump() calls recorded.</p></div>
<?php return; endif; ?>
<div class="wpd-section">
<h4 class="wpd-section-title">Dumps (<?= $this->e((string) $totalCount) ?>)</h4>
<?php foreach ($dumps as $index => $dump):
    $file = $dump['file'] ?? 'unknown';
    $line = $dump['line'] ?? 0;
    $dumpData = $dump['data'] ?? '';
?>
<div class="wpd-dump-item">
<div class="wpd-dump-file">#<?= $this->e((string) ($index + 1)) ?> <?= $this->e($file) ?>:<?= $this->e((string) $line) ?></div>
<pre class="wpd-dump-code"><?= $this->e($dumpData) ?></pre>
</div>
<?php endforeach; ?>
</div>
