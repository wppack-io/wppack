<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\DataCollector;

use WpPack\Component\Debug\Attribute\AsDataCollector;

#[AsDataCollector(name: 'mail', priority: 75)]
final class MailDataCollector extends AbstractDataCollector
{
    /** @var list<array{to: string|list<string>, subject: string, headers: string|list<string>, message: string, attachments: list<string>, status: string, error: string}> */
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
        $this->emails[] = [
            'to' => $args['to'],
            'subject' => $args['subject'],
            'headers' => $args['headers'],
            'message' => $args['message'],
            'attachments' => $args['attachments'],
            'status' => 'pending',
            'error' => '',
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
        for ($i = count($this->emails) - 1; $i >= 0; $i--) {
            if ($this->emails[$i]['status'] === 'pending') {
                $this->emails[$i]['status'] = 'sent';
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

        for ($i = count($this->emails) - 1; $i >= 0; $i--) {
            if ($this->emails[$i]['status'] === 'pending') {
                $this->emails[$i]['status'] = 'failed';
                $this->emails[$i]['error'] = $errorMessage;
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
            $masked['message'] = mb_substr($email['message'], 0, 500);

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

    public function getBadgeValue(): string
    {
        $total = $this->data['total_count'] ?? 0;

        return $total > 0 ? (string) $total : '';
    }

    public function getBadgeColor(): string
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

        return 'green';
    }

    public function reset(): void
    {
        parent::reset();
        $this->emails = [];
    }

    private function registerHooks(): void
    {
        if (function_exists('add_filter')) {
            add_filter('wp_mail', [$this, 'captureMailAttempt'], \PHP_INT_MAX, 1);
        }

        if (function_exists('add_action')) {
            add_action('wp_mail_succeeded', [$this, 'captureMailSuccess'], 10, 1);
            add_action('wp_mail_failed', [$this, 'captureMailFailure'], 10, 1);
        }
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
