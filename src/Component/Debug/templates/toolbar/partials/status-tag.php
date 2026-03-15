<?php
/**
 * Status tag partial for mail status display.
 *
 * @var string $status Status string: "sent", "failed", or "pending"
 */
$tagClass = match ($status) {
    'sent' => 'wpd-status-sent',
    'failed' => 'wpd-status-failed',
    default => 'wpd-status-pending',
};
$tagLabel = match ($status) {
    'sent' => 'SENT',
    'failed' => 'FAILED',
    default => 'PENDING',
};
?>
<span class="wpd-status-tag <?= $tagClass ?>"><?= $tagLabel ?></span>
