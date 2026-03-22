<?php

declare(strict_types=1);

namespace WpPack\Component\Media;

final class AttachmentManager
{
    /**
     * @param array<string, mixed> $data
     *
     * @return int|\WP_Error
     *
     * @see wp_insert_attachment()
     */
    public function insert(array $data, string $file, int $parentId = 0): int|\WP_Error
    {
        return wp_insert_attachment($data, $file, $parentId);
    }

    /**
     * @return \WP_Post|false|null
     *
     * @see wp_delete_attachment()
     */
    public function delete(int $id, bool $force = false): \WP_Post|false|null
    {
        return wp_delete_attachment($id, $force);
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
     * @return string|false
     *
     * @see get_attached_file()
     */
    public function getAttachedFile(int $id, bool $unfiltered = false): string|false
    {
        return get_attached_file($id, $unfiltered);
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
     * @return int|false
     *
     * @see wp_update_attachment_metadata()
     */
    public function updateMetadata(int $id, array $data): int|false
    {
        return wp_update_attachment_metadata($id, $data);
    }

    /**
     * @return array<string, mixed>|false
     *
     * @see wp_get_attachment_metadata()
     */
    public function getMetadata(int $id, bool $unfiltered = false): array|false
    {
        return wp_get_attachment_metadata($id, $unfiltered);
    }

    /**
     * @see get_posts()
     */
    public function findByMeta(string $metaKey, string $metaValue): ?int
    {
        $existing = get_posts([
            'post_type' => 'attachment',
            'post_status' => 'any',
            'meta_key' => $metaKey,
            'meta_value' => $metaValue,
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);

        if ($existing !== []) {
            return (int) $existing[0];
        }

        return null;
    }
}
