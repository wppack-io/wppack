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

namespace WPPack\Component\User;

use WPPack\Component\User\Exception\UserException;

final readonly class UserRepository implements UserRepositoryInterface
{
    public function findAll(array $args = []): array
    {
        // get_users() returns array; the listing path always yields
        // WP_User instances. array_filter narrows + array_values reindexes
        // for the list<WP_User> contract.
        return array_values(array_filter(
            get_users($args),
            static fn($user): bool => $user instanceof \WP_User,
        ));
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
        if (!isset($data['user_login']) || '' === $data['user_login']) {
            throw new UserException('Cannot create a user with an empty login name.');
        }

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
