<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer;

use WpPack\Component\Mailer\Exception\TransportException;
use WpPack\Component\Mailer\Transport\Transport;
use WpPack\Component\Mailer\Transport\TransportInterface;
use WpPack\Component\Mime\MimeTypes;
use WpPack\Component\Mime\MimeTypesInterface;

final class Mailer
{
    private static bool $booted = false;
    private static ?self $bootedInstance = null;

    private readonly PhpMailer $phpMailer;
    private ?TemplateRendererInterface $renderer = null;

    private readonly TransportInterface $transport;
    private readonly MimeTypesInterface $mimeTypes;

    public function __construct(
        string|TransportInterface $transport,
        ?PhpMailer $phpMailer = null,
        ?MimeTypesInterface $mimeTypes = null,
    ) {
        $this->transport = \is_string($transport)
            ? Transport::fromDsn($transport)
            : $transport;

        $this->phpMailer = $phpMailer ?? new PhpMailer(true);
        $this->phpMailer->setTransport($this->transport);
        $this->mimeTypes = $mimeTypes ?? MimeTypes::getDefault();
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
        self::$booted = true;
        self::$bootedInstance = $this;
    }

    /**
     * Reset the boot state. Intended for testing only.
     *
     * @internal
     */
    public static function reset(): void
    {
        if (self::$bootedInstance !== null) {
            @remove_filter('wp_mail', [self::$bootedInstance, 'onWpMail'], PHP_INT_MIN);
        }

        self::$booted = false;
        self::$bootedInstance = null;
    }

    // --- Path 1: wp_mail hooks ---

    /**
     * @param array{to: string, subject: string, message: string, headers: string|string[], attachments: string[]} $args
     * @return array{to: string, subject: string, message: string, headers: string|string[], attachments: string[]}
     */
    public function onWpMail(array $args): array
    {
        global $phpmailer;
        $phpmailer = $this->phpMailer;

        return $args;
    }

    // --- Path 2: Direct send (Symfony style) ---

    /**
     * Send an Email directly without going through wp_mail().
     * phpmailer_init + wp_mail_succeeded/failed are still fired for plugin compatibility.
     *
     * @throws TransportException On send failure
     */
    public function send(Email $email, ?Envelope $envelope = null): void
    {
        // 1. Render templates if needed
        if ($email instanceof TemplatedEmail) {
            $this->renderTemplatedEmail($email);
        }

        // 2. Create envelope
        $envelope ??= Envelope::create($email);

        // 3. Clear previous message state
        $this->clearPhpMailer($this->phpMailer);

        // 4. Populate PHPMailer from Email
        $this->populatePhpMailer($this->phpMailer, $email);

        // 5. Fire phpmailer_init action (plugin compatibility)
        $phpMailer = $this->phpMailer;
        do_action_ref_array('phpmailer_init', [&$phpMailer]);

        // 6. Send
        try {
            $this->phpMailer->send();
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

        $sentMessage = new SentMessage($email, $envelope, $this->phpMailer->getLastMessageID());

        do_action('wp_mail_succeeded', [
            'to' => array_map(static fn(Address $a): string => $a->toString(), $envelope->getRecipients()),
            'subject' => $email->getSubject(),
            'message' => $email->getText() ?? $email->getHtml() ?? '',
            'headers' => $this->flattenHeaders($email->getHeaders()),
            'attachments' => array_map(static fn(Attachment $a): string => $a->path, $email->getAttachments()),
            'sent_message' => $sentMessage,
        ]);
    }

    // --- Internal helpers ---

    /**
     * Clear per-message state (same as WordPress wp_mail() does before each send).
     */
    private function clearPhpMailer(PhpMailer $phpMailer): void
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
        $phpMailer->ContentType = PhpMailer::CONTENT_TYPE_PLAINTEXT;
        $phpMailer->CharSet = PhpMailer::CHARSET_UTF8;
        $phpMailer->Encoding = PhpMailer::ENCODING_8BIT;
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

    private function populatePhpMailer(PhpMailer $phpMailer, Email $email): void
    {
        // From — apply wp_mail_from / wp_mail_from_name filters (same as wp_mail())
        $from = $email->getFrom();
        $fromAddress = apply_filters('wp_mail_from', $from?->address ?? $phpMailer->From);
        $fromName = apply_filters('wp_mail_from_name', $from?->name ?? $phpMailer->FromName);

        $phpMailer->setFrom($fromAddress, $fromName);

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
            $contentType = $attachment->contentType
                ?? $this->mimeTypes->guessMimeType($attachment->path)
                ?? 'application/octet-stream';

            if ($attachment->inline) {
                $phpMailer->addEmbeddedImage(
                    $attachment->path,
                    $attachment->name ?? basename($attachment->path),
                    basename($attachment->path),
                    'base64',
                    $contentType,
                );
            } else {
                $phpMailer->addAttachment(
                    $attachment->path,
                    $attachment->name ?? '',
                    'base64',
                    $contentType,
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
