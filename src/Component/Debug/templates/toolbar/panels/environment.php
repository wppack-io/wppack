<?php
/**
 * Environment panel template.
 *
 * @var array                                                        $data Environment data
 * @var \WPPack\Component\Debug\Toolbar\Panel\TemplateFormatters     $fmt  Template formatters
 */
$php = $data['php'] ?? [];
$extensions = $data['extensions'] ?? [];
$ini = $data['ini'] ?? [];
$opcache = $data['opcache'] ?? [];
$server = $data['server'] ?? [];
?>
<div class="wpd-section">
<h4 class="wpd-section-title">PHP Runtime</h4>
<table class="wpd-table wpd-table-kv">
<?= $view->include('toolbar/partials/table-row', ['key' => 'Version', 'value' => $view->e((string) ($php['version'] ?? ''))]) ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'SAPI', 'value' => $view->e((string) ($data['sapi'] ?? ''))]) ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Zend Engine', 'value' => $view->e((string) ($php['zend_version'] ?? ''))]) ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Architecture', 'value' => $view->e((string) ($data['architecture'] ?? 64)) . '-bit']) ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Thread Safe', 'value' => $fmt->value($php['zts'] ?? false)]) ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Debug Build', 'value' => $fmt->value($php['debug'] ?? false)]) ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'GC Enabled', 'value' => $fmt->value($php['gc_enabled'] ?? false)]) ?>
</table>
</div>
<?php
$opcacheEnabled = (bool) ($opcache['enabled'] ?? false);
?>
<div class="wpd-section">
<h4 class="wpd-section-title">OPcache</h4>
<table class="wpd-table wpd-table-kv">
<?= $view->include('toolbar/partials/table-row', ['key' => 'Enabled', 'value' => $fmt->value($opcacheEnabled)]) ?>
<?php if ($opcacheEnabled):
    $hitRate = (float) ($opcache['hit_rate'] ?? 0);
    $hitColor = $hitRate >= 95 ? 'wpd-text-green' : ($hitRate >= 80 ? 'wpd-text-yellow' : 'wpd-text-red');
    $usedMb = (int) ($opcache['used_memory'] ?? 0) / 1048576;
    $freeMb = (int) ($opcache['free_memory'] ?? 0) / 1048576;
    $wastedPct = (float) ($opcache['wasted_percentage'] ?? 0);
    $oomRestarts = (int) ($opcache['oom_restarts'] ?? 0);
    ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'JIT', 'value' => $fmt->value($opcache['jit'] ?? false)]) ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Cached Scripts', 'value' => $view->e((string) ($opcache['cached_scripts'] ?? 0))]) ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Hit Rate', 'value' => '<span class="' . $hitColor . '">' . $view->e(sprintf('%.1f%%', $hitRate)) . '</span>']) ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Memory', 'value' => $view->e(sprintf('%.1f MB used / %.1f MB free', $usedMb, $freeMb))]) ?>
<?php if ($wastedPct > 0): ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Wasted', 'value' => '<span class="wpd-text-yellow">' . $view->e(sprintf('%.1f%%', $wastedPct)) . '</span>']) ?>
<?php endif; ?>
<?php if ($oomRestarts > 0): ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'OOM Restarts', 'value' => '<span class="wpd-text-red">' . $view->e((string) $oomRestarts) . '</span>']) ?>
<?php endif; ?>
<?php endif; ?>
</table>
</div>
<div class="wpd-section">
<h4 class="wpd-section-title">PHP Configuration</h4>
<table class="wpd-table wpd-table-kv">
<?php foreach ($ini as $key => $value):
    $displayValue = $value;
    if ($key === 'disable_functions' && $value !== '') {
        $count = count(explode(',', $value));
        $displayValue = $count . ' functions disabled';
    }
    ?>
<?= $view->include('toolbar/partials/table-row', ['key' => $key, 'value' => $view->e($displayValue !== '' ? $displayValue : '(empty)')]) ?>
<?php endforeach; ?>
</table>
</div>
<?php if (!empty($extensions)): ?>
<div class="wpd-section">
<h4 class="wpd-section-title">PHP Extensions (<?= $view->e((string) count($extensions)) ?>)</h4>
<div class="wpd-tag-list">
<?php foreach ($extensions as $ext): ?>
<span class="wpd-tag"><?= $view->e($ext) ?></span>
<?php endforeach; ?>
</div>
</div>
<?php endif; ?>
<?php
$webServer = $server['web_server'] ?? ['name' => '', 'version' => '', 'raw' => ''];
$webServerName = (string) ($webServer['name'] ?? '');
$webServerVersion = (string) ($webServer['version'] ?? '');
?>
<div class="wpd-section">
<h4 class="wpd-section-title">Web Server</h4>
<table class="wpd-table wpd-table-kv">
<?php if ($webServerName !== ''):
    $softwareDisplay = $webServerVersion !== '' ? $webServerName . ' ' . $webServerVersion : $webServerName;
    ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Software', 'value' => $view->e($softwareDisplay)]) ?>
<?php else: ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Software', 'value' => '<span class="wpd-text-muted">(not available)</span>']) ?>
<?php endif; ?>
<?php
$protocol = (string) ($server['protocol'] ?? '');
if ($protocol !== '') {
    echo $view->include('toolbar/partials/table-row', ['key' => 'Protocol', 'value' => $view->e($protocol)]);
}
$documentRoot = (string) ($server['document_root'] ?? '');
if ($documentRoot !== '') {
    echo $view->include('toolbar/partials/table-row', ['key' => 'Document Root', 'value' => $view->e($documentRoot)]);
}
$port = (string) ($server['port'] ?? '');
if ($port !== '') {
    echo $view->include('toolbar/partials/table-row', ['key' => 'Port', 'value' => $view->e($port)]);
}
?>
</table>
</div>
<?php
$runtime = $data['runtime'] ?? ['type' => '', 'details' => []];
$runtimeType = (string) ($runtime['type'] ?? '');
$runtimeDetails = $runtime['details'] ?? [];
$runtimeLabels = [
    'lambda' => 'Lambda', 'ecs' => 'ECS', 'kubernetes' => 'Kubernetes',
    'docker' => 'Docker', 'ec2' => 'EC2',
];
$hostname = (string) ($data['hostname'] ?? '');
?>
<div class="wpd-section">
<h4 class="wpd-section-title">Infrastructure</h4>
<table class="wpd-table wpd-table-kv">
<?php if ($runtimeType !== '' && isset($runtimeLabels[$runtimeType])): ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Runtime', 'value' => '<span class="wpd-tag">' . $view->e($runtimeLabels[$runtimeType]) . '</span>']) ?>
<?php endif; ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'OS', 'value' => $view->e((string) ($data['os'] ?? ''))]) ?>
<?php if ($hostname !== ''): ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Hostname', 'value' => $view->e($hostname)]) ?>
<?php endif; ?>
<?php foreach ($runtimeDetails as $detailKey => $detailValue):
    if ($detailKey === 'Hostname' && $detailValue === $hostname) {
        continue;
    }
    ?>
<?= $view->include('toolbar/partials/table-row', ['key' => $detailKey, 'value' => $view->e($detailValue)]) ?>
<?php endforeach; ?>
</table>
</div>
