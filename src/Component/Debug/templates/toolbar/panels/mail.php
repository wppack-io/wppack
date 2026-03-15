<?php
/**
 * Mail panel template.
 *
 * @var int                                                          $totalCount   Total email count
 * @var int                                                          $successCount Successfully sent count
 * @var int                                                          $failureCount Failed send count
 * @var list<array>                                                  $emails       Email records
 * @var \WpPack\Component\Debug\Toolbar\Panel\TemplateFormatters     $fmt          Template formatters
 */
?>
<div class="wpd-section">
<h4 class="wpd-section-title">Summary</h4>
<table class="wpd-table wpd-table-kv">
<?= $this->include('toolbar/partials/table-row', ['key' => 'Total Emails', 'value' => (string) $totalCount]) ?>
<?= $this->include('toolbar/partials/table-row', ['key' => 'Sent', 'value' => (string) $successCount, 'valueClass' => $successCount > 0 ? 'wpd-text-green' : '']) ?>
<?= $this->include('toolbar/partials/table-row', ['key' => 'Failed', 'value' => (string) $failureCount, 'valueClass' => $failureCount > 0 ? 'wpd-text-red' : '']) ?>
</table>
</div>
<?php foreach ($emails as $index => $email):
    $statusTag = match ($email['status'] ?? 'pending') {
        'sent' => '<span class="wpd-status-tag wpd-status-sent">SENT</span>',
        'failed' => '<span class="wpd-status-tag wpd-status-failed">FAILED</span>',
        default => '<span class="wpd-status-tag wpd-status-pending">PENDING</span>',
    };
?>
<div class="wpd-section">
<h4 class="wpd-section-title">Email #<?= $this->e((string) ($index + 1)) ?> <?= $this->raw($statusTag) ?></h4>
<table class="wpd-table wpd-table-kv">
<?php
$to = $email['to'] ?? '';
$toDisplay = is_array($to) ? implode(', ', $to) : (string) $to;
echo $this->include('toolbar/partials/table-row', ['key' => 'To', 'value' => '<code>' . $this->e($toDisplay) . '</code>']);
echo $this->include('toolbar/partials/table-row', ['key' => 'Subject', 'value' => $this->e((string) ($email['subject'] ?? ''))]);
$from = (string) ($email['from'] ?? '');
if ($from !== '') {
    echo $this->include('toolbar/partials/table-row', ['key' => 'From', 'value' => '<code>' . $this->e($from) . '</code>']);
}
$cc = $email['cc'] ?? [];
if (!empty($cc)) {
    echo $this->include('toolbar/partials/table-row', ['key' => 'Cc', 'value' => '<code>' . $this->e(implode(', ', $cc)) . '</code>']);
}
$bcc = $email['bcc'] ?? [];
if (!empty($bcc)) {
    echo $this->include('toolbar/partials/table-row', ['key' => 'Bcc', 'value' => '<code>' . $this->e(implode(', ', $bcc)) . '</code>']);
}
$replyTo = (string) ($email['reply_to'] ?? '');
if ($replyTo !== '') {
    echo $this->include('toolbar/partials/table-row', ['key' => 'Reply-To', 'value' => '<code>' . $this->e($replyTo) . '</code>']);
}
$contentType = (string) ($email['content_type'] ?? '');
if ($contentType !== '') {
    $charset = (string) ($email['charset'] ?? '');
    $ctDisplay = $contentType . ($charset !== '' ? '; charset=' . $charset : '');
    echo $this->include('toolbar/partials/table-row', ['key' => 'Content-Type', 'value' => $this->e($ctDisplay)]);
}
echo $this->include('toolbar/partials/table-row', ['key' => 'Status', 'value' => $statusTag]);
$errorMsg = (string) ($email['error'] ?? '');
if ($errorMsg !== '') {
    echo $this->include('toolbar/partials/table-row', ['key' => 'Error', 'value' => '<span class="wpd-text-red">' . $this->e($errorMsg) . '</span>']);
}
?>
</table>
<?php
$message = (string) ($email['message'] ?? '');
if ($message !== ''):
?>
<div class="wpd-mail-body">
<pre class="wpd-dump-code"><?= $this->e($message) ?></pre>
</div>
<?php endif; ?>
<?php
$attachmentDetails = $email['attachment_details'] ?? [];
if (!empty($attachmentDetails)):
?>
<div class="wpd-mail-attachments">
<table class="wpd-table wpd-table-full">
<thead><tr><th>Attachment</th><th class="wpd-col-right">Size</th></tr></thead>
<tbody>
<?php foreach ($attachmentDetails as $att): ?>
<tr>
<td><code><?= $this->e($att['filename']) ?></code></td>
<td class="wpd-col-right"><?= $this->e($fmt->bytes($att['size'])) ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
<?php endif; ?>
</div>
<?php endforeach; ?>
