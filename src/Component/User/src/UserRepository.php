<?php

declare(strict_types=1);

namespace WpPack\Component\User;

final readonly class UserRepository implements UserRepositoryInterface
{
    public function findAll(array $args = []): array
    {
        return get_users($args);
    }

    public function find(int $userId): ?\WP_User
    {
        $user = get_userdata($userId);

        return $user instanceof \WP_User ? $user : null;
    }

    public function findByEmail(string $email): ?\WP_User
    {
        return $this->findBy('email', $email);
    }

    public function findByLogin(string $login): ?\WP_User
    {
        return $this->findBy('login', $login);
    }

    public function findBySlug(string $slug): ?\WP_User
    {
        return $this->findBy('slug', $slug);
    }

    public function insert(array $data): int|\WP_Error
    {
        return wp_insert_user($data);
    }

    public function update(array $data): int|\WP_Error
    {
        return wp_update_user($data);
    }

    public function delete(int $userId, ?int $reassignTo = null): bool
    {
        require_once \ABSPATH . 'wp-admin/includes/user.php';

        return wp_delete_user($userId, $reassignTo);
    }

    public function getMeta(int $userId, string $key = '', bool $single = false): mixed
    {
        return get_user_meta($userId, $key, $single);
    }

    public function addMeta(int $userId, string $key, mixed $value, bool $unique = false): int|false
    {
        return add_user_meta($userId, $key, $value, $unique);
    }

    public function updateMeta(int $userId, string $key, mixed $value, mixed $previousValue = ''): int|bool
    {
        return update_user_meta($userId, $key, $value, $previousValue);
    }

    public function deleteMeta(int $userId, string $key, mixed $value = ''): bool
    {
        return delete_user_meta($userId, $key, $value);
    }

    private function findBy(string $field, string $value): ?\WP_User
    {
        $user = get_user_by($field, $value);

        return $user instanceof \WP_User ? $user : null;
    }
}
