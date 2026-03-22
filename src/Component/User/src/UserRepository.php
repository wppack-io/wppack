<?php

declare(strict_types=1);

namespace WpPack\Component\User;

final readonly class UserRepository implements UserRepositoryInterface
{
    public function findAll(array $args = []): array
    {
        if (!\function_exists('get_users')) {
            return [];
        }

        return get_users($args);
    }

    public function find(int $userId): ?\WP_User
    {
        if (!\function_exists('get_userdata')) {
            return null;
        }

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
        if (!\function_exists('wp_insert_user')) {
            return new \WP_Error('missing_function', 'wp_insert_user() is not available.');
        }

        return wp_insert_user($data);
    }

    public function update(array $data): int|\WP_Error
    {
        if (!\function_exists('wp_update_user')) {
            return new \WP_Error('missing_function', 'wp_update_user() is not available.');
        }

        return wp_update_user($data);
    }

    public function delete(int $userId, ?int $reassignTo = null): bool
    {
        if (!\function_exists('wp_delete_user')) {
            require_once \ABSPATH . 'wp-admin/includes/user.php';
        }

        if (!\function_exists('wp_delete_user')) {
            return false;
        }

        return wp_delete_user($userId, $reassignTo);
    }

    public function getMeta(int $userId, string $key = '', bool $single = false): mixed
    {
        if (!\function_exists('get_user_meta')) {
            return $single ? '' : [];
        }

        return get_user_meta($userId, $key, $single);
    }

    public function addMeta(int $userId, string $key, mixed $value, bool $unique = false): int|false
    {
        if (!\function_exists('add_user_meta')) {
            return false;
        }

        return add_user_meta($userId, $key, $value, $unique);
    }

    public function updateMeta(int $userId, string $key, mixed $value, mixed $previousValue = ''): int|bool
    {
        if (!\function_exists('update_user_meta')) {
            return false;
        }

        return update_user_meta($userId, $key, $value, $previousValue);
    }

    public function deleteMeta(int $userId, string $key, mixed $value = ''): bool
    {
        if (!\function_exists('delete_user_meta')) {
            return false;
        }

        return delete_user_meta($userId, $key, $value);
    }

    private function findBy(string $field, string $value): ?\WP_User
    {
        if (!\function_exists('get_user_by')) {
            return null;
        }

        $user = get_user_by($field, $value);

        return $user instanceof \WP_User ? $user : null;
    }
}
