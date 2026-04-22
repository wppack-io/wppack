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

namespace WPPack\Component\PostType;

use WPPack\Component\PostType\Exception\PostException;

final readonly class PostRepository implements PostRepositoryInterface
{
    public function find(int $postId): ?\WP_Post
    {
        $post = get_post($postId);

        return $post instanceof \WP_Post ? $post : null;
    }

    public function findAll(array $args = []): array
    {
        return \array_values(\array_filter(
            get_posts($args),
            static fn ($p): bool => $p instanceof \WP_Post,
        ));
    }

    public function insert(array $data): int
    {
        $result = wp_insert_post($data, true);

        if ($result instanceof \WP_Error) {
            throw PostException::fromWpError($result);
        }

        return $result;
    }

    public function update(array $data): int
    {
        $result = wp_update_post($data, true);

        if ($result instanceof \WP_Error) {
            throw PostException::fromWpError($result);
        }

        return $result;
    }

    public function delete(int $postId, bool $force = false): ?\WP_Post
    {
        $result = wp_delete_post($postId, $force);

        return $result instanceof \WP_Post ? $result : null;
    }

    public function trash(int $postId): ?\WP_Post
    {
        $result = wp_trash_post($postId);

        return $result instanceof \WP_Post ? $result : null;
    }

    public function untrash(int $postId): ?\WP_Post
    {
        $result = wp_untrash_post($postId);

        return $result instanceof \WP_Post ? $result : null;
    }

    public function getMeta(int $postId, string $key = '', bool $single = false): mixed
    {
        return get_post_meta($postId, $key, $single);
    }

    public function addMeta(int $postId, string $key, mixed $value, bool $unique = false): ?int
    {
        $result = add_post_meta($postId, $key, $value, $unique);

        return $result === false ? null : $result;
    }

    public function updateMeta(int $postId, string $key, mixed $value, mixed $previousValue = ''): int|bool
    {
        return update_post_meta($postId, $key, $value, $previousValue);
    }

    public function deleteMeta(int $postId, string $key, mixed $value = ''): bool
    {
        return delete_post_meta($postId, $key, $value);
    }

    public function findOneByMeta(string $metaKey, string $metaValue, string $postType = 'any', string $postStatus = 'any'): ?int
    {
        $posts = get_posts([
            'post_type' => $postType,
            'post_status' => $postStatus,
            'meta_key' => $metaKey,
            'meta_value' => $metaValue,
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);

        $first = $posts[0] ?? null;

        return \is_int($first) ? $first : null;
    }
}
