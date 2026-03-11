<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

use WpPack\Component\Debug\Attribute\AsPanelRenderer;

#[AsPanelRenderer(name: 'mail')]
final class MailPanelRenderer extends AbstractPanelRenderer implements PanelRendererInterface
{
    public function getName(): string
    {
        return 'mail';
    }

    public function render(array $data): string
    {
        $totalCount = (int) ($data['total_count'] ?? 0);
        $successCount = (int) ($data['success_count'] ?? 0);
        $failureCount = (int) ($data['failure_count'] ?? 0);
        /** @var list<array<string, mixed>> $emails */
        $emails = $data['emails'] ?? [];

        $html = '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Summary</h4>';
        $html .= '<table class="wpd-table wpd-table-kv">';
        $html .= $this->renderTableRow('Total Emails', (string) $totalCount);
        $html .= $this->renderTableRow('Sent', (string) $successCount, $successCount > 0 ? 'wpd-text-green' : '');
        $html .= $this->renderTableRow('Failed', (string) $failureCount, $failureCount > 0 ? 'wpd-text-red' : '');
        $html .= '</table>';
        $html .= '</div>';

        if ($emails !== []) {
            foreach ($emails as $index => $email) {
                $statusTag = match ($email['status'] ?? 'pending') {
                    'sent' => '<span class="wpd-query-tag" style="background:rgba(166,227,161,0.2);color:#a6e3a1">SENT</span>',
                    'failed' => '<span class="wpd-query-tag" style="background:rgba(243,139,168,0.2);color:#f38ba8">FAILED</span>',
                    default => '<span class="wpd-query-tag" style="background:rgba(249,226,175,0.2);color:#f9e2af">PENDING</span>',
                };

                $html .= '<div class="wpd-section">';
                $html .= '<h4 class="wpd-section-title">Email #' . $this->esc((string) ($index + 1)) . ' ' . $statusTag . '</h4>';

                // Headers table
                $html .= '<table class="wpd-table wpd-table-kv">';
                $to = $email['to'] ?? '';
                $toDisplay = is_array($to) ? implode(', ', $to) : (string) $to;
                $html .= $this->renderTableRow('To', '<code>' . $this->esc($toDisplay) . '</code>');
                $html .= $this->renderTableRow('Subject', $this->esc((string) ($email['subject'] ?? '')));

                $from = (string) ($email['from'] ?? '');
                if ($from !== '') {
                    $html .= $this->renderTableRow('From', '<code>' . $this->esc($from) . '</code>');
                }

                /** @var list<string> $cc */
                $cc = $email['cc'] ?? [];
                if ($cc !== []) {
                    $html .= $this->renderTableRow('Cc', '<code>' . $this->esc(implode(', ', $cc)) . '</code>');
                }

                /** @var list<string> $bcc */
                $bcc = $email['bcc'] ?? [];
                if ($bcc !== []) {
                    $html .= $this->renderTableRow('Bcc', '<code>' . $this->esc(implode(', ', $bcc)) . '</code>');
                }

                $replyTo = (string) ($email['reply_to'] ?? '');
                if ($replyTo !== '') {
                    $html .= $this->renderTableRow('Reply-To', '<code>' . $this->esc($replyTo) . '</code>');
                }

                $contentType = (string) ($email['content_type'] ?? '');
                if ($contentType !== '') {
                    $charset = (string) ($email['charset'] ?? '');
                    $ctDisplay = $contentType . ($charset !== '' ? '; charset=' . $charset : '');
                    $html .= $this->renderTableRow('Content-Type', $this->esc($ctDisplay));
                }

                $html .= $this->renderTableRow('Status', $statusTag);

                $error = (string) ($email['error'] ?? '');
                if ($error !== '') {
                    $html .= $this->renderTableRow('Error', '<span class="wpd-text-red">' . $this->esc($error) . '</span>');
                }

                $html .= '</table>';

                // Body preview
                $message = (string) ($email['message'] ?? '');
                if ($message !== '') {
                    $html .= '<div style="margin-top:8px">';
                    $html .= '<pre style="background:#181825;padding:8px 12px;border-radius:4px;overflow-x:auto;font-size:12px;color:#cdd6f4;margin:0;max-height:200px;overflow-y:auto">';
                    $html .= $this->esc($message);
                    $html .= '</pre>';
                    $html .= '</div>';
                }

                // Attachments
                /** @var list<array{filename: string, size: int}> $attachmentDetails */
                $attachmentDetails = $email['attachment_details'] ?? [];
                if ($attachmentDetails !== []) {
                    $html .= '<div style="margin-top:8px">';
                    $html .= '<table class="wpd-table wpd-table-full">';
                    $html .= '<thead><tr><th>Attachment</th><th>Size</th></tr></thead>';
                    $html .= '<tbody>';
                    foreach ($attachmentDetails as $att) {
                        $html .= '<tr>';
                        $html .= '<td><code>' . $this->esc($att['filename']) . '</code></td>';
                        $html .= '<td>' . $this->formatBytes($att['size']) . '</td>';
                        $html .= '</tr>';
                    }
                    $html .= '</tbody></table>';
                    $html .= '</div>';
                }

                $html .= '</div>';
            }
        }

        return $html;
    }
}
