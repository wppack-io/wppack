<?php
/**
 * Ajax panel template.
 *
 * @var int                                                          $totalActions Total registered AJAX actions
 * @var array                                                        $actions      AJAX action definitions
 * @var \WpPack\Component\Debug\Toolbar\Panel\TemplateFormatters     $fmt          Template formatters
 */
?>
<div class="wpd-section">
<h4 class="wpd-section-title">Registered Actions (<?= $totalActions ?>)</h4>
<?php if (!empty($actions)): ?>
<table class="wpd-table wpd-table-full">
<thead><tr>
<th>Action</th>
<th>Callback</th>
<th>NoPriv</th>
</tr></thead>
<tbody>
<?php foreach ($actions as $action => $info): ?>
<tr>
<td><code><?= $this->e($action) ?></code></td>
<td class="wpd-text-dim"><?= $this->e($info['callback']) ?></td>
<td><?= $this->raw($fmt->value($info['nopriv'])) ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
<?php else: ?>
<p class="wpd-text-dim">No registered ajax actions.</p>
<?php endif; ?>
</div>
<div class="wpd-section">
<h4 class="wpd-section-title">Client-Side Requests</h4>
<table class="wpd-table wpd-table-full">
<thead><tr>
<th>Action</th>
<th>Method</th>
<th>Status</th>
<th class="wpd-col-right">Duration</th>
<th class="wpd-col-right">Size</th>
</tr></thead>
<tbody id="wpd-ajax-tbody">
</tbody></table>
<p class="wpd-text-dim" id="wpd-ajax-empty" style="margin-top:4px">No requests captured yet.</p>
</div>
