<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Plugin\AmazonMailerPlugin\Message;

final readonly class SesNotificationNormalizer
{
    /**
     * Parse an SES SNS notification into message objects.
     *
     * @param array<string, mixed> $notification
     * @return list<SesBounceMessage|SesComplaintMessage>
     */
    public function normalize(array $notification): array
    {
        $type = (string) ($notification['notificationType'] ?? '');
        $messageId = (string) ($notification['mail']['messageId'] ?? '');

        if ($messageId === '') {
            return [];
        }

        return match ($type) {
            'Bounce' => $this->normalizeBounce($notification, $messageId),
            'Complaint' => $this->normalizeComplaint($notification, $messageId),
            default => [],
        };
    }

    /**
     * @param array<string, mixed> $notification
     * @return list<SesBounceMessage>
     */
    private function normalizeBounce(array $notification, string $messageId): array
    {
        /** @var array<string, mixed> $bounce */
        $bounce = $notification['bounce'] ?? [];

        $bounceType = (string) ($bounce['bounceType'] ?? '');
        $bounceSubType = (string) ($bounce['bounceSubType'] ?? '');
        $timestamp = $this->parseTimestamp((string) ($bounce['timestamp'] ?? ''));

        if ($timestamp === null) {
            return [];
        }

        /** @var list<array<string, mixed>> $recipients */
        $recipients = $bounce['bouncedRecipients'] ?? [];
        $addresses = $this->extractRecipientAddresses($recipients);

        if ($addresses === []) {
            return [];
        }

        return [
            new SesBounceMessage(
                messageId: $messageId,
                bounceType: $bounceType,
                bounceSubType: $bounceSubType,
                bouncedRecipients: $addresses,
                timestamp: $timestamp,
            ),
        ];
    }

    /**
     * @param array<string, mixed> $notification
     * @return list<SesComplaintMessage>
     */
    private function normalizeComplaint(array $notification, string $messageId): array
    {
        /** @var array<string, mixed> $complaint */
        $complaint = $notification['complaint'] ?? [];

        $feedbackType = (string) ($complaint['complaintFeedbackType'] ?? '');
        $timestamp = $this->parseTimestamp((string) ($complaint['timestamp'] ?? ''));

        if ($timestamp === null) {
            return [];
        }

        /** @var list<array<string, mixed>> $recipients */
        $recipients = $complaint['complainedRecipients'] ?? [];
        $addresses = $this->extractRecipientAddresses($recipients);

        if ($addresses === []) {
            return [];
        }

        return [
            new SesComplaintMessage(
                messageId: $messageId,
                complaintFeedbackType: $feedbackType,
                complainedRecipients: $addresses,
                timestamp: $timestamp,
            ),
        ];
    }

    /**
     * @param list<array<string, mixed>> $recipients
     * @return list<string>
     */
    private function extractRecipientAddresses(array $recipients): array
    {
        $addresses = [];

        foreach ($recipients as $recipient) {
            $email = (string) ($recipient['emailAddress'] ?? '');

            if ($email !== '') {
                $addresses[] = $email;
            }
        }

        return $addresses;
    }

    private function parseTimestamp(string $timestamp): ?\DateTimeImmutable
    {
        if ($timestamp === '') {
            return null;
        }

        $dt = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $timestamp);

        if ($dt === false) {
            $dt = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.u\Z', $timestamp, new \DateTimeZone('UTC'));
        }

        return $dt ?: null;
    }
}
