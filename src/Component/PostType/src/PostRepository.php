<?php

declare(strict_types=1);

namespace WpPack\Component\PostType;

final readonly class PostRepository implements PostRepositoryInterface
{
    public function find(int $postId): ?\WP_Post
    {
        if (!\function_exists('get_post')) {
            return null;
        }

        $post = get_post($postId);

        return $post instanceof \WP_Post ? $post : null;
    }

    public function findAll(array $args = []): array
    {
        if (!\function_exists('get_posts')) {
            return [];
        }

        return get_posts($args);
    }

    public function insert(array $data): int|\WP_Error
    {
        if (!\function_exists('wp_insert_post')) {
            return new \WP_Error('missing_function', 'wp_insert_post() is not available.');
        }

        return wp_insert_post($data, true);
    }

    public function update(array $data): int|\WP_Error
    {
        if (!\function_exists('wp_update_post')) {
            return new \WP_Error('missing_function', 'wp_update_post() is not available.');
        }

        return wp_update_post($data, true);
    }

    public function delete(int $postId, bool $force = false): ?\WP_Post
    {
        if (!\function_exists('wp_delete_post')) {
            return null;
        }

        $result = wp_delete_post($postId, $force);

        return $result instanceof \WP_Post ? $result : null;
    }

    public function trash(int $postId): ?\WP_Post
    {
        if (!\function_exists('wp_trash_post')) {
            return null;
        }

        $result = wp_trash_post($postId);

        return $result instanceof \WP_Post ? $result : null;
    }

    public function untrash(int $postId): ?\WP_Post
    {
        if (!\function_exists('wp_untrash_post')) {
            return null;
        }

        $result = wp_untrash_post($postId);

        return $result instanceof \WP_Post ? $result : null;
    }

    public function getMeta(int $postId, string $key = '', bool $single = false): mixed
    {
        if (!\function_exists('get_post_meta')) {
            return $single ? '' : [];
        }

        return get_post_meta($postId, $key, $single);
    }

    public function addMeta(int $postId, string $key, mixed $value, bool $unique = false): int|false
    {
        if (!\function_exists('add_post_meta')) {
            return false;
        }

        return add_post_meta($postId, $key, $value, $unique);
    }

    public function updateMeta(int $postId, string $key, mixed $value, mixed $previousValue = ''): int|bool
    {
        if (!\function_exists('update_post_meta')) {
            return false;
        }

        return update_post_meta($postId, $key, $value, $previousValue);
    }

    public function deleteMeta(int $postId, string $key, mixed $value = ''): bool
    {
        if (!\function_exists('delete_post_meta')) {
            return false;
        }

        return delete_post_meta($postId, $key, $value);
    }

    public function findOneByMeta(string $metaKey, string $metaValue, string $postType = 'any', string $postStatus = 'any'): ?int
    {
        if (!\function_exists('get_posts')) {
            return null;
        }

        $posts = get_posts([
            'post_type' => $postType,
            'post_status' => $postStatus,
            'meta_key' => $metaKey,
            'meta_value' => $metaValue,
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);

        return $posts !== [] ? (int) $posts[0] : null;
    }
}
