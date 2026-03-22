<?php

declare(strict_types=1);

namespace WpPack\Component\Media;

use WpPack\Component\Media\Exception\AttachmentException;
use WpPack\Component\PostType\PostRepositoryInterface;

final readonly class AttachmentManager implements AttachmentManagerInterface
{
    public function __construct(
        private PostRepositoryInterface $postRepository,
    ) {}

    /**
     * @param array<string, mixed> $data
     *
     * @throws AttachmentException
     *
     * @see wp_insert_attachment()
     */
    public function insert(array $data, string $file, int $parentId = 0): int
    {
        $result = wp_insert_attachment($data, $file, $parentId);

        if ($result instanceof \WP_Error) {
            throw AttachmentException::fromWpError($result);
        }

        return $result;
    }

    /**
     * @see wp_delete_attachment()
     */
    public function delete(int $id, bool $force = false): ?\WP_Post
    {
        $result = wp_delete_attachment($id, $force);

        if (!$result instanceof \WP_Post) {
            return null;
        }

        return $result;
    }

    /**
     * @return array<string, mixed>|null
     *
     * @see wp_prepare_attachment_for_js()
     */
    public function prepareForJs(int $id): ?array
    {
        return wp_prepare_attachment_for_js($id) ?: null;
    }

    /**
     * @see get_attached_file()
     */
    public function getFile(int $id, bool $unfiltered = false): ?string
    {
        $result = get_attached_file($id, $unfiltered);

        return \is_string($result) && $result !== '' ? $result : null;
    }

    /**
     * @return array<string, mixed>
     *
     * @see wp_generate_attachment_metadata()
     */
    public function generateMetadata(int $id, string $file): array
    {
        return wp_generate_attachment_metadata($id, $file);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @see wp_update_attachment_metadata()
     */
    public function updateMetadata(int $id, array $data): bool
    {
        return wp_update_attachment_metadata($id, $data) !== false;
    }

    /**
     * @return array<string, mixed>|null
     *
     * @see wp_get_attachment_metadata()
     */
    public function getMetadata(int $id, bool $unfiltered = false): ?array
    {
        $result = wp_get_attachment_metadata($id, $unfiltered);

        return \is_array($result) ? $result : null;
    }

    public function findByMeta(string $metaKey, string $metaValue): ?int
    {
        return $this->postRepository->findOneByMeta($metaKey, $metaValue, 'attachment', 'any');
    }
}
