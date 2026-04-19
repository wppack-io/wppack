<?php
/**
 * Mail panel template.
 *
 * @var int                                                          $totalCount   Total email count
 * @var int                                                          $successCount Successfully sent count
 * @var int                                                          $failureCount Failed send count
 * @var list<array>                                                  $emails       Email records
 * @var \WPPack\Component\Debug\Toolbar\Panel\TemplateFormatters     $fmt          Template formatters
 */
?>
<div class="wpd-section">
<h4 class="wpd-section-title">Summary</h4>
<table class="wpd-table wpd-table-kv">
<?= $view->include('toolbar/partials/table-row', ['key' => 'Total Emails', 'value' => (string) $totalCount]) ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Sent', 'value' => (string) $successCount, 'valueClass' => $successCount > 0 ? 'wpd-text-green' : '']) ?>
<?= $view->include('toolbar/partials/table-row', ['key' => 'Failed', 'value' => (string) $failureCount, 'valueClass' => $failureCount > 0 ? 'wpd-text-red' : '']) ?>
</table>
</div>
<?php foreach ($emails as $index => $email):
    $status = $email['status'] ?? 'pending';
    $statusTag = $view->include('toolbar/partials/status-tag', ['status' => $status]);
    ?>
<div class="wpd-section">
<h4 class="wpd-section-title">Email #<?= $view->e((string) ($index + 1)) ?> <?= $view->raw($statusTag) ?></h4>
<table class="wpd-table wpd-table-kv">
<?php
    $to = $email['to'] ?? '';
    $toDisplay = is_array($to) ? implode(', ', $to) : (string) $to;
    echo $view->include('toolbar/partials/table-row', ['key' => 'To', 'value' => '<code>' . $view->e($toDisplay) . '</code>']);
    echo $view->include('toolbar/partials/table-row', ['key' => 'Subject', 'value' => $view->e((string) ($email['subject'] ?? ''))]);
    $from = (string) ($email['from'] ?? '');
    if ($from !== '') {
        echo $view->include('toolbar/partials/table-row', ['key' => 'From', 'value' => '<code>' . $view->e($from) . '</code>']);
    }
    $cc = $email['cc'] ?? [];
    if (!empty($cc)) {
        echo $view->include('toolbar/partials/table-row', ['key' => 'Cc', 'value' => '<code>' . $view->e(implode(', ', $cc)) . '</code>']);
    }
    $bcc = $email['bcc'] ?? [];
    if (!empty($bcc)) {
        echo $view->include('toolbar/partials/table-row', ['key' => 'Bcc', 'value' => '<code>' . $view->e(implode(', ', $bcc)) . '</code>']);
    }
    $replyTo = (string) ($email['reply_to'] ?? '');
    if ($replyTo !== '') {
        echo $view->include('toolbar/partials/table-row', ['key' => 'Reply-To', 'value' => '<code>' . $view->e($replyTo) . '</code>']);
    }
    $contentType = (string) ($email['content_type'] ?? '');
    if ($contentType !== '') {
        $charset = (string) ($email['charset'] ?? '');
        $ctDisplay = $contentType . ($charset !== '' ? '; charset=' . $charset : '');
        echo $view->include('toolbar/partials/table-row', ['key' => 'Content-Type', 'value' => $view->e($ctDisplay)]);
    }
    echo $view->include('toolbar/partials/table-row', ['key' => 'Status', 'value' => $statusTag]);
    $errorMsg = (string) ($email['error'] ?? '');
    if ($errorMsg !== '') {
        echo $view->include('toolbar/partials/table-row', ['key' => 'Error', 'value' => '<span class="wpd-text-red">' . $view->e($errorMsg) . '</span>']);
    }
    ?>
</table>
<?php
    $message = (string) ($email['message'] ?? '');
    if ($message !== ''):
        ?>
<div class="wpd-mail-body">
<pre class="wpd-dump-code"><?= $view->e($message) ?></pre>
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
<td><code><?= $view->e($att['filename']) ?></code></td>
<td class="wpd-col-right"><?= $view->e($fmt->bytes($att['size'])) ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
<?php endif; ?>
</div>
<?php endforeach; ?>
