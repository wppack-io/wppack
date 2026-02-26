<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer;

use PHPMailer\PHPMailer\PHPMailer;
use WpPack\Component\Mailer\Exception\TransportException;
use WpPack\Component\Mailer\Transport\Transport;
use WpPack\Component\Mailer\Transport\TransportInterface;

final class Mailer
{
    private static bool $booted = false;

    private readonly TransportInterface $transport;
    private ?WpPackPhpMailer $phpMailer = null;
    private ?TemplateRendererInterface $renderer = null;

    public function __construct(string|TransportInterface $transport)
    {
        $this->transport = \is_string($transport)
            ? Transport::fromDsn($transport)
            : $transport;
    }

    public function setTemplateRenderer(TemplateRendererInterface $renderer): void
    {
        $this->renderer = $renderer;
    }

    /**
     * Register WordPress hooks so wp_mail() uses the configured transport.
     */
    public function boot(): void
    {
        if (self::$booted) {
            return;
        }

        add_filter('wp_mail', [$this, 'onWpMail'], PHP_INT_MIN);
        add_action('phpmailer_init', [$this, 'onPhpMailerInit'], PHP_INT_MAX);
        self::$booted = true;
    }

    // --- Path 1: wp_mail hooks ---

    /**
     * @param array{to: string, subject: string, message: string, headers: string|string[], attachments: string[]} $args
     * @return array{to: string, subject: string, message: string, headers: string|string[], attachments: string[]}
     */
    public function onWpMail(array $args): array
    {
        global $phpmailer;
        $phpmailer = $this->getPhpMailer();

        return $args;
    }

    public function onPhpMailerInit(PHPMailer &$phpmailer): void
    {
        if ($phpmailer instanceof WpPackPhpMailer) {
            $this->transport->configure($phpmailer);
        }
    }

    // --- Path 2: Direct send (Symfony style) ---

    /**
     * Send an Email directly without going through wp_mail().
     * phpmailer_init + wp_mail_succeeded/failed are still fired for plugin compatibility.
     *
     * @throws TransportException On send failure
     */
    public function send(Email $email, ?Envelope $envelope = null): SentMessage
    {
        // 1. Render templates if needed
        if ($email instanceof TemplatedEmail) {
            $this->renderTemplatedEmail($email);
        }

        // 2. Create envelope
        $envelope ??= Envelope::create($email);

        // 3. Get WpPackPhpMailer and clear previous message state
        $phpMailer = $this->getPhpMailer();
        $this->clearPhpMailer($phpMailer);

        // 4. Populate PHPMailer from Email
        $this->populatePhpMailer($phpMailer, $email);

        // 5. Apply transport-specific configuration
        $this->transport->configure($phpMailer);

        // 6. Fire phpmailer_init action (plugin compatibility)
        do_action_ref_array('phpmailer_init', [&$phpMailer]);

        // 7. Send
        try {
            $phpMailer->send();
        } catch (\Throwable $e) {
            $errorData = [
                'to' => array_map(static fn(Address $a): string => $a->toString(), $envelope->getRecipients()),
                'subject' => $email->getSubject(),
                'message' => $email->getText() ?? $email->getHtml() ?? '',
                'phpmailer_exception_code' => $e->getCode(),
            ];
            do_action('wp_mail_failed', new \WP_Error('wp_mail_failed', $e->getMessage(), $errorData));

            throw $e instanceof TransportException ? $e : new TransportException($e->getMessage(), 0, $e);
        }

        $sentMessage = new SentMessage($email, $envelope, $phpMailer->getLastMessageID());

        do_action('wp_mail_succeeded', [
            'to' => array_map(static fn(Address $a): string => $a->toString(), $envelope->getRecipients()),
            'subject' => $email->getSubject(),
            'message' => $email->getText() ?? $email->getHtml() ?? '',
            'headers' => $this->flattenHeaders($email->getHeaders()),
            'attachments' => array_map(static fn(Attachment $a): string => $a->path, $email->getAttachments()),
        ]);

        return $sentMessage;
    }

    // --- Internal helpers ---

    private function getPhpMailer(): WpPackPhpMailer
    {
        if ($this->phpMailer === null) {
            $this->phpMailer = new WpPackPhpMailer(true);
        }

        return $this->phpMailer;
    }

    /**
     * Clear per-message state (same as WordPress wp_mail() does before each send).
     */
    private function clearPhpMailer(WpPackPhpMailer $phpMailer): void
    {
        $phpMailer->clearAllRecipients();
        $phpMailer->clearAttachments();
        $phpMailer->clearCustomHeaders();
        $phpMailer->clearReplyTos();
        $phpMailer->Body = '';
        $phpMailer->AltBody = '';
        $phpMailer->Subject = '';
        $phpMailer->From = 'root@localhost';
        $phpMailer->FromName = 'Root User';
        $phpMailer->Sender = '';
        $phpMailer->Priority = null;
    }

    /**
     * @return list<string> Headers in "Name: Value" format for wp_mail_succeeded compatibility.
     */
    private function flattenHeaders(Header\Headers $headers): array
    {
        $flat = [];
        foreach ($headers->all() as $name => $values) {
            foreach ($values as $value) {
                $flat[] = $name . ': ' . $value;
            }
        }

        return $flat;
    }

    private function renderTemplatedEmail(TemplatedEmail $email): void
    {
        if ($this->renderer === null) {
            throw new Exception\InvalidArgumentException('A TemplateRendererInterface must be set to render TemplatedEmail. Call setTemplateRenderer() first.');
        }

        $context = $email->getContext();

        if ($email->getHtmlTemplate() !== null && $email->getHtml() === null) {
            $email->html($this->renderer->render($email->getHtmlTemplate(), $context));
        }

        if ($email->getTextTemplate() !== null && $email->getText() === null) {
            $email->text($this->renderer->render($email->getTextTemplate(), $context));
        }
    }

    private function populatePhpMailer(WpPackPhpMailer $phpMailer, Email $email): void
    {
        // From
        $from = $email->getFrom();
        if ($from !== null) {
            $phpMailer->setFrom($from->address, $from->name);
        }

        // To
        foreach ($email->getTo() as $to) {
            $phpMailer->addAddress($to->address, $to->name);
        }

        // Cc
        foreach ($email->getCc() as $cc) {
            $phpMailer->addCC($cc->address, $cc->name);
        }

        // Bcc
        foreach ($email->getBcc() as $bcc) {
            $phpMailer->addBCC($bcc->address, $bcc->name);
        }

        // Reply-To
        foreach ($email->getReplyTo() as $replyTo) {
            $phpMailer->addReplyTo($replyTo->address, $replyTo->name);
        }

        // Subject
        $phpMailer->Subject = $email->getSubject() ?? '';

        // Body
        $html = $email->getHtml();
        $text = $email->getText();
        if ($html !== null) {
            $phpMailer->isHTML(true);
            $phpMailer->Body = $html;
            if ($text !== null) {
                $phpMailer->AltBody = $text;
            }
        } elseif ($text !== null) {
            $phpMailer->isHTML(false);
            $phpMailer->Body = $text;
        }

        // Attachments
        foreach ($email->getAttachments() as $attachment) {
            if ($attachment->inline) {
                $phpMailer->addEmbeddedImage(
                    $attachment->path,
                    $attachment->name ?? basename($attachment->path),
                    basename($attachment->path),
                    'base64',
                    $attachment->contentType ?? '',
                );
            } else {
                $phpMailer->addAttachment(
                    $attachment->path,
                    $attachment->name ?? '',
                    'base64',
                    $attachment->contentType ?? '',
                );
            }
        }

        // Custom Headers
        foreach ($email->getHeaders()->all() as $name => $values) {
            foreach ($values as $value) {
                $phpMailer->addCustomHeader($name, $value);
            }
        }

        // Priority
        $phpMailer->Priority = $email->getPriority();

        // Return-Path
        $returnPath = $email->getReturnPath();
        if ($returnPath !== null) {
            $phpMailer->Sender = $returnPath->address;
        }
    }
}
