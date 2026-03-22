<?php

declare(strict_types=1);

namespace WpPack\Component\User;

interface UserRepositoryInterface
{
    public function find(int $userId): ?\WP_User;

    public function findByEmail(string $email): ?\WP_User;

    public function findByLogin(string $login): ?\WP_User;

    public function findBySlug(string $slug): ?\WP_User;

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

    public function delete(int $userId, ?int $reassignTo = null): bool;

    public function getMeta(int $userId, string $key = '', bool $single = false): mixed;

    public function addMeta(int $userId, string $key, mixed $value, bool $unique = false): int|false;

    public function updateMeta(int $userId, string $key, mixed $value, mixed $previousValue = ''): int|bool;

    public function deleteMeta(int $userId, string $key, mixed $value = ''): bool;
}
