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

namespace WPPack\Component\Media;

use WPPack\Component\Media\Exception\AttachmentException;

interface AttachmentManagerInterface
{
    /**
     * @param array<string, mixed> $data
     *
     * @throws AttachmentException
     *
     * @see wp_insert_attachment()
     */
    public function insert(array $data, string $file, int $parentId = 0): int;

    /**
     * @see wp_delete_attachment()
     */
    public function delete(int $id, bool $force = false): ?\WP_Post;

    /**
     * @return array<string, mixed>|null
     *
     * @see wp_prepare_attachment_for_js()
     */
    public function prepareForJs(int $id): ?array;

    /**
     * @see get_attached_file()
     */
    public function getFile(int $id, bool $unfiltered = false): ?string;

    /**
     * @return array<string, mixed>
     *
     * @see wp_generate_attachment_metadata()
     */
    public function generateMetadata(int $id, string $file): array;

    /**
     * @param array<string, mixed> $data
     *
     * @see wp_update_attachment_metadata()
     */
    public function updateMetadata(int $id, array $data): bool;

    /**
     * @return array<string, mixed>|null
     *
     * @see wp_get_attachment_metadata()
     */
    public function getMetadata(int $id, bool $unfiltered = false): ?array;

    public function findByMeta(string $metaKey, string $metaValue): ?int;
}
