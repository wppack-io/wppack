<?php
/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WpPack\Component\Debug\DataCollector;

use WpPack\Component\Debug\Attribute\AsDataCollector;

#[AsDataCollector(name: 'mail', priority: 90)]
final class MailDataCollector extends AbstractDataCollector
{
    /** @var list<array{to: string|list<string>, subject: string, headers: string|list<string>, message: string, attachments: list<string>, status: string, error: string, from: string, cc: list<string>, bcc: list<string>, reply_to: string, content_type: string, charset: string, attachment_details: list<array{filename: string, size: int}>, start: float, duration: float}> */
    private array $emails = [];

    public function __construct()
    {
        $this->registerHooks();
    }

    public function getName(): string
    {
        return 'mail';
    }

    public function getLabel(): string
    {
        return 'Mail';
    }

    /**
     * Capture a mail attempt via the wp_mail filter.
     *
     * @param array{to: string|list<string>, subject: string, message: string, headers: string|list<string>, attachments: list<string>} $args
     * @return array{to: string|list<string>, subject: string, message: string, headers: string|list<string>, attachments: list<string>}
     */
    public function captureMailAttempt(array $args): array
    {
        $parsedHeaders = $this->parseHeaders($args['headers']);
        $attachmentDetails = $this->getAttachmentDetails($args['attachments']);

        $this->emails[] = [
            'to' => $args['to'],
            'subject' => $args['subject'],
            'headers' => $args['headers'],
            'message' => $args['message'],
            'attachments' => $args['attachments'],
            'status' => 'pending',
            'error' => '',
            'from' => $parsedHeaders['from'],
            'cc' => $parsedHeaders['cc'],
            'bcc' => $parsedHeaders['bcc'],
            'reply_to' => $parsedHeaders['reply_to'],
            'content_type' => $parsedHeaders['content_type'],
            'charset' => $parsedHeaders['charset'],
            'attachment_details' => $attachmentDetails,
            'start' => microtime(true),
            'duration' => 0.0,
        ];

        return $args;
    }

    /**
     * Mark the last pending email as sent.
     *
     * @param array{to: string|list<string>, subject: string, headers: string|list<string>, attachments: list<string>} $mailData
     */
    public function captureMailSuccess(array $mailData): void
    {
        $now = microtime(true);
        for ($i = count($this->emails) - 1; $i >= 0; $i--) {
            if ($this->emails[$i]['status'] === 'pending') {
                $this->emails[$i]['status'] = 'sent';
                $this->emails[$i]['duration'] = ($now - $this->emails[$i]['start']) * 1000;
                break;
            }
        }
    }

    /**
     * Mark the last pending email as failed.
     */
    public function captureMailFailure(mixed $error): void
    {
        $errorMessage = '';

        if (method_exists($error, 'get_error_message')) {
            $errorMessage = $error->get_error_message();
        }

        $now = microtime(true);
        for ($i = count($this->emails) - 1; $i >= 0; $i--) {
            if ($this->emails[$i]['status'] === 'pending') {
                $this->emails[$i]['status'] = 'failed';
                $this->emails[$i]['error'] = $errorMessage;
                $this->emails[$i]['duration'] = ($now - $this->emails[$i]['start']) * 1000;
                break;
            }
        }
    }

    public function collect(): void
    {
        $emails = [];
        $successCount = 0;
        $failureCount = 0;

        foreach ($this->emails as $email) {
            $masked = $email;
            $masked['to'] = $this->maskRecipients($email['to']);
            $masked['message'] = mb_substr($email['message'], 0, 2000);

            $emails[] = $masked;

            if ($email['status'] === 'sent') {
                $successCount++;
            } elseif ($email['status'] === 'failed') {
                $failureCount++;
            }
        }

        $this->data = [
            'emails' => $emails,
            'total_count' => count($emails),
            'success_count' => $successCount,
            'failure_count' => $failureCount,
        ];
    }

    public function getIndicatorValue(): string
    {
        $total = $this->data['total_count'] ?? 0;

        return $total > 0 ? (string) $total : '';
    }

    public function getIndicatorColor(): string
    {
        $failureCount = $this->data['failure_count'] ?? 0;
        $totalCount = $this->data['total_count'] ?? 0;
        $successCount = $this->data['success_count'] ?? 0;

        if ($failureCount > 0) {
            return 'red';
        }

        if ($totalCount > 0 && $totalCount > $successCount) {
            return 'yellow';
        }

        if ($totalCount > 0 && $totalCount === $successCount) {
            return 'green';
        }

        return 'default';
    }

    public function reset(): void
    {
        parent::reset();
        $this->emails = [];
    }

    private function registerHooks(): void
    {
        add_filter('wp_mail', [$this, 'captureMailAttempt'], \PHP_INT_MAX, 1);
        add_action('wp_mail_succeeded', [$this, 'captureMailSuccess'], 10, 1);
        add_action('wp_mail_failed', [$this, 'captureMailFailure'], 10, 1);
    }

    /**
     * Parse email headers to extract structured data.
     *
     * @param string|list<string> $headers
     * @return array{from: string, cc: list<string>, bcc: list<string>, reply_to: string, content_type: string, charset: string}
     */
    private function parseHeaders(string|array $headers): array
    {
        $result = [
            'from' => '',
            'cc' => [],
            'bcc' => [],
            'reply_to' => '',
            'content_type' => '',
            'charset' => '',
        ];

        $headerLines = is_array($headers) ? $headers : explode("\n", $headers);

        foreach ($headerLines as $header) {
            $header = trim((string) $header);
            if ($header === '') {
                continue;
            }

            $colonPos = strpos($header, ':');
            if ($colonPos === false) {
                continue;
            }

            $name = strtolower(trim(substr($header, 0, $colonPos)));
            $value = trim(substr($header, $colonPos + 1));

            match ($name) {
                'from' => $result['from'] = $value,
                'cc' => $result['cc'][] = $value,
                'bcc' => $result['bcc'][] = $value,
                'reply-to' => $result['reply_to'] = $value,
                'content-type' => $this->parseContentType($value, $result),
                default => null,
            };
        }

        return $result;
    }

    /**
     * @param array{from: string, cc: list<string>, bcc: list<string>, reply_to: string, content_type: string, charset: string} $result
     */
    private function parseContentType(string $value, array &$result): void
    {
        $parts = explode(';', $value);
        $result['content_type'] = trim($parts[0]);

        foreach ($parts as $part) {
            $part = trim($part);
            if (stripos($part, 'charset=') === 0) {
                $result['charset'] = trim(substr($part, 8), '"\'');
            }
        }
    }

    /**
     * Get details for each attachment file.
     *
     * @param list<string> $attachments
     * @return list<array{filename: string, size: int}>
     */
    private function getAttachmentDetails(array $attachments): array
    {
        $details = [];

        foreach ($attachments as $path) {
            $details[] = [
                'filename' => basename($path),
                'size' => file_exists($path) ? (int) filesize($path) : 0,
            ];
        }

        return $details;
    }

    /**
     * Mask email addresses to protect privacy: t***@example.com format.
     *
     * @param string|list<string> $recipients
     * @return string|list<string>
     */
    private function maskRecipients(string|array $recipients): string|array
    {
        if (is_array($recipients)) {
            return array_map($this->maskEmail(...), $recipients);
        }

        return $this->maskEmail($recipients);
    }

    private function maskEmail(string $email): string
    {
        $atPos = strpos($email, '@');

        if ($atPos === false || $atPos === 0) {
            return $email;
        }

        return $email[0] . '***' . substr($email, $atPos);
    }
}
