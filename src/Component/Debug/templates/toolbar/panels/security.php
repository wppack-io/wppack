<?php
/**
 * Security panel template.
 *
 * @var bool                                                         $isLoggedIn       Whether the user is logged in
 * @var string                                                       $username         WordPress username
 * @var string                                                       $displayName      User display name
 * @var string                                                       $email            User email address
 * @var list<string>                                                 $roles            User roles
 * @var bool                                                         $isSuperAdmin     Whether the user is a super admin
 * @var string                                                       $auth             Authentication method
 * @var array<string,bool>                                           $capabilities     User capabilities
 * @var list<array>                                                  $nonceOps         Nonce operation log
 * @var int                                                          $nonceVerifyCount Total nonce verifications
 * @var int                                                          $nonceFailures    Failed nonce verifications
 * @var \WPPack\Component\Debug\Toolbar\Panel\TemplateFormatters     $fmt              Template formatters
 * @var float                                                        $requestTimeFloat Request start timestamp
 */
?>
<div class="wpd-section">
<h4 class="wpd-section-title">User</h4>
<table class="wpd-table wpd-table-kv">
<?= $view->include('toolbar/partials/table-row', ['key' => 'Logged In', 'value' => $fmt->value($isLoggedIn)]) ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Username', 'value' => $view->e($username ?: '-')]) ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Display Name', 'value' => $view->e($displayName ?: '-')]) ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Email', 'value' => $view->e($email ?: '-')]) ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Authentication', 'value' => $view->e($auth)]) ?>
<?php if ($isSuperAdmin): ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Super Admin', 'value' => '<span class="wpd-text-yellow">Yes</span>']) ?>
<?php endif; ?>
</table>
</div>
<?php if (!empty($roles)): ?>
<div class="wpd-section">
<h4 class="wpd-section-title">Roles</h4>
<div class="wpd-tag-list">
<?php foreach ($roles as $role): ?>
<span class="wpd-tag"><?= $view->e($role) ?></span>
<?php endforeach; ?>
</div>
</div>
<?php endif; ?>
<?php if (!empty($capabilities)): ?>
<div class="wpd-section">
<h4 class="wpd-section-title">Capabilities (<?= $view->e((string) count($capabilities)) ?>)</h4>
<div class="wpd-tag-list">
<?php foreach ($capabilities as $cap => $granted): ?>
<span class="wpd-tag <?= $granted ? 'wpd-text-green' : 'wpd-text-red' ?>"><?= $view->e($cap) ?></span>
<?php endforeach; ?>
</div>
</div>
<?php endif; ?>
<div class="wpd-section">
<h4 class="wpd-section-title">Nonce Operations</h4>
<table class="wpd-table wpd-table-kv">
<?= $view->include('toolbar/partials/table-row', ['key' => 'Total Verifications', 'value' => (string) $nonceVerifyCount]) ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Failures', 'value' => (string) $nonceFailures, 'valueClass' => $nonceFailures > 0 ? 'wpd-text-red' : '']) ?>
</table>
<?php if (!empty($nonceOps)): ?>
<table class="wpd-table wpd-table-full" style="margin-top:8px">
<thead><tr>
<th class="wpd-col-reltime">Time</th>
<th>Action</th>
<th>Operation</th>
<th>Result</th>
</tr></thead>
<tbody>
<?php foreach ($nonceOps as $op): ?>
<tr>
<td class="wpd-col-reltime wpd-text-dim"><?= $view->e($fmt->relativeTime($op['timestamp'], $requestTimeFloat)) ?></td>
<td><code><?= $view->e($op['action']) ?></code></td>
<td><?= $view->e($op['operation']) ?></td>
<td><?php if ($op['result']): ?><span class="wpd-text-green">pass</span><?php else: ?><span class="wpd-text-red">fail</span><?php endif; ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
<?php endif; ?>
</div>
