<?php

declare(strict_types=1);

namespace WpPack\Component\User;

use WpPack\Component\User\Exception\UserException;

interface UserRepositoryInterface
{
    /**
     * @param array<string, mixed> $args get_users() arguments
     *
     * @return list<\WP_User>
     */
    public function findAll(array $args = []): array;

    public function find(int $userId): ?\WP_User;

    public function findByEmail(string $email): ?\WP_User;

    public function findByLogin(string $login): ?\WP_User;

    public function findBySlug(string $slug): ?\WP_User;

    /**
     * @param array<string, mixed> $data
     *
     * @throws UserException
     */
    public function insert(array $data): int;

    /**
     * @param array<string, mixed> $data
     *
     * @throws UserException
     */
    public function update(array $data): int;

    public function delete(int $userId, ?int $reassignTo = null): bool;

    public function getMeta(int $userId, string $key = '', bool $single = false): mixed;

    public function addMeta(int $userId, string $key, mixed $value, bool $unique = false): ?int;

    public function updateMeta(int $userId, string $key, mixed $value, mixed $previousValue = ''): int|bool;

    public function deleteMeta(int $userId, string $key, mixed $value = ''): bool;
}
