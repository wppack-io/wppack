<?php

declare(strict_types=1);

namespace WpPack\Component\PostType;

interface PostRepositoryInterface
{
    public function find(int $postId): ?\WP_Post;

    /**
     * @param array<string, mixed> $args
     *
     * @return list<\WP_Post>
     */
    public function findAll(array $args = []): array;

    /**
     * @param array<string, mixed> $data
     *
     * @return int|\WP_Error
     */
    public function insert(array $data): int|\WP_Error;

    /**
     * @param array<string, mixed> $data
     *
     * @return int|\WP_Error
     */
    public function update(array $data): int|\WP_Error;

    public function delete(int $postId, bool $force = false): ?\WP_Post;

    public function trash(int $postId): ?\WP_Post;

    public function untrash(int $postId): ?\WP_Post;

    public function getMeta(int $postId, string $key = '', bool $single = false): mixed;

    public function addMeta(int $postId, string $key, mixed $value, bool $unique = false): int|false;

    public function updateMeta(int $postId, string $key, mixed $value, mixed $previousValue = ''): int|bool;

    public function deleteMeta(int $postId, string $key, mixed $value = ''): bool;

    public function findOneByMeta(string $metaKey, string $metaValue, string $postType = 'any', string $postStatus = 'any'): ?int;
}
