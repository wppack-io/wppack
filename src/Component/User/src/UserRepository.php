<?php

/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WpPack\Component\User;

use WpPack\Component\User\Exception\UserException;

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

    public function insert(array $data): int
    {
        $result = wp_insert_user($data);

        if ($result instanceof \WP_Error) {
            throw UserException::fromWpError($result);
        }

        return $result;
    }

    public function update(array $data): int
    {
        $result = wp_update_user($data);

        if ($result instanceof \WP_Error) {
            throw UserException::fromWpError($result);
        }

        return $result;
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

    public function addMeta(int $userId, string $key, mixed $value, bool $unique = false): ?int
    {
        $result = add_user_meta($userId, $key, $value, $unique);

        return $result === false ? null : $result;
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
