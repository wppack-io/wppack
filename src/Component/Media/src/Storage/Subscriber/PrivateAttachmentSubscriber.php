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

namespace WPPack\Component\Media\Storage\Subscriber;

use Psr\Log\LoggerInterface;
use WPPack\Component\EventDispatcher\Attribute\AsEventListener;
use WPPack\Component\EventDispatcher\WordPressEvent;
use WPPack\Component\Media\Storage\PrivateAttachmentChecker;
use WPPack\Component\Media\Storage\SignedUrlCache;
use WPPack\Component\Media\Storage\StorageConfiguration;
use WPPack\Component\Storage\Adapter\StorageAdapterInterface;

final readonly class PrivateAttachmentSubscriber
{
    /** Default signed URL expiration: 6 hours */
    private const DEFAULT_EXPIRATION_SECONDS = 21600;

    /** Cache TTL buffer: 1 hour shorter than expiration */
    private const CACHE_TTL_BUFFER_SECONDS = 3600;

    public function __construct(
        private StorageConfiguration $config,
        private StorageAdapterInterface $adapter,
        private PrivateAttachmentChecker $checker,
        private SignedUrlCache $cache,
        private ?LoggerInterface $logger = null,
    ) {}

    /**
     * Convert attachment URL to signed URL for private attachments.
     *
     * Runs after AttachmentSubscriber (priority 10) to transform the storage URL.
     */
    #[AsEventListener(event: 'wp_get_attachment_url', priority: 20)]
    public function filterAttachmentUrl(WordPressEvent $event): void
    {
        /** @var int $attachmentId */
        $attachmentId = $event->args[1];

        if (!$this->checker->isPrivate($attachmentId)) {
            return;
        }

        $file = get_post_meta($attachmentId, '_wp_attached_file', true);
        if (!\is_string($file) || $file === '') {
            return;
        }

        $key = $this->config->prefix . '/' . ltrim($file, '/');
        $signedUrl = $this->getSignedUrl($key);

        if ($signedUrl !== null) {
            $event->filterValue = $signedUrl;
        }
    }

    /**
     * Convert image src URL to signed URL for private attachments.
     */
    #[AsEventListener(event: 'wp_get_attachment_image_src')]
    public function filterAttachmentImageSrc(WordPressEvent $event): void
    {
        /** @var array{0: string, 1: int, 2: int, 3: bool}|false $image */
        $image = $event->filterValue;

        if ($image === false) {
            return;
        }

        /** @var int $attachmentId */
        $attachmentId = $event->args[1];

        if (!$this->checker->isPrivate($attachmentId)) {
            return;
        }

        $file = get_post_meta($attachmentId, '_wp_attached_file', true);
        if (!\is_string($file) || $file === '') {
            return;
        }

        // Determine the key from the image URL
        $key = $this->resolveKeyFromUrl($image[0], $file);
        $signedUrl = $this->getSignedUrl($key);

        if ($signedUrl !== null) {
            $image[0] = $signedUrl;
            $event->filterValue = $image;
        }
    }

    /**
     * Convert srcset source URLs to signed URLs for private attachments.
     */
    #[AsEventListener(event: 'wp_calculate_image_srcset')]
    public function filterImageSrcset(WordPressEvent $event): void
    {
        /** @var array<int, array{url: string, descriptor: string, value: int}>|false $sources */
        $sources = $event->filterValue;

        if ($sources === false || $sources === []) {
            return;
        }

        /** @var array{0: string, 1: int, 2: int, 3: bool} $sizeArray */
        $sizeArray = $event->args[1];

        /** @var string $imageSrc */
        $imageSrc = $event->args[2];

        /** @var array<string, mixed> $imageMeta */
        $imageMeta = $event->args[3];

        /** @var int $attachmentId */
        $attachmentId = $event->args[4];

        if (!$this->checker->isPrivate($attachmentId)) {
            return;
        }

        $file = get_post_meta($attachmentId, '_wp_attached_file', true);
        if (!\is_string($file) || $file === '') {
            return;
        }

        foreach ($sources as $width => $source) {
            $key = $this->resolveKeyFromUrl($source['url'], $file);
            $signedUrl = $this->getSignedUrl($key);

            if ($signedUrl !== null) {
                $sources[$width]['url'] = $signedUrl;
            }
        }

        $event->filterValue = $sources;
    }

    private function getSignedUrl(string $key): ?string
    {
        $cached = $this->cache->get($key);
        if ($cached !== null) {
            return $cached;
        }

        $expiration = new \DateTimeImmutable(
            sprintf('+%d seconds', self::DEFAULT_EXPIRATION_SECONDS),
        );

        try {
            $signedUrl = $this->adapter->temporaryUrl($key, $expiration);
        } catch (\Throwable $e) {
            $this->logger?->warning('Failed to generate signed URL for private attachment', ['key' => $key, 'error' => $e->getMessage()]);

            return null;
        }

        $cacheTtl = self::DEFAULT_EXPIRATION_SECONDS - self::CACHE_TTL_BUFFER_SECONDS;
        $this->cache->set($key, $signedUrl, $cacheTtl);

        return $signedUrl;
    }

    /**
     * Resolve a storage key from a URL, using the attachment's base file path.
     */
    private function resolveKeyFromUrl(string $url, string $attachedFile): string
    {
        $baseDir = \dirname($attachedFile);
        $filename = basename((string) parse_url($url, \PHP_URL_PATH));

        if ($baseDir !== '.' && $baseDir !== '') {
            return $this->config->prefix . '/' . $baseDir . '/' . $filename;
        }

        return $this->config->prefix . '/' . $filename;
    }
}
