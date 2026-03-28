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

namespace WpPack\Component\Scim\Repository;

use WpPack\Component\Scim\Filter\FilterNode;
use WpPack\Component\Scim\Filter\WpUserQueryAdapter;
use WpPack\Component\Scim\Schema\ScimConstants;
use WpPack\Component\User\UserRepositoryInterface;

final readonly class ScimUserRepository
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private WpUserQueryAdapter $queryAdapter = new WpUserQueryAdapter(),
    ) {}

    public function find(int $userId): ?\WP_User
    {
        return $this->userRepository->find($userId);
    }

    public function findByLogin(string $login): ?\WP_User
    {
        return $this->userRepository->findByLogin($login);
    }

    public function findByExternalId(string $externalId): ?\WP_User
    {
        $users = $this->userRepository->findAll([
            'meta_key' => ScimConstants::META_EXTERNAL_ID,
            'meta_value' => $externalId,
            'number' => 1,
        ]);

        return $users[0] ?? null;
    }

    /**
     * @return array{users: list<\WP_User>, totalResults: int}
     */
    public function findFiltered(?FilterNode $filter, int $startIndex, int $count): array
    {
        $args = [
            'number' => $count,
            'offset' => max(0, $startIndex - 1),
            'count_total' => true,
        ];

        if ($filter !== null) {
            $filterArgs = $this->queryAdapter->toQueryArgs($filter);
            $args = array_merge($args, $filterArgs);
        }

        $query = new \WP_User_Query($args);
        $users = $query->get_results();
        $total = $query->get_total();

        return [
            'users' => $users,
            'totalResults' => $total,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $meta
     */
    public function create(array $data, array $meta): int
    {
        // Generate a random password for SCIM-provisioned users
        if (!isset($data['user_pass'])) {
            $data['user_pass'] = wp_generate_password(32, true, true);
        }

        $userId = $this->userRepository->insert($data);

        foreach ($meta as $key => $value) {
            $this->userRepository->updateMeta($userId, $key, $value);
        }

        // Mark as SCIM active by default
        if (!isset($meta[ScimConstants::META_ACTIVE])) {
            $this->userRepository->updateMeta($userId, ScimConstants::META_ACTIVE, '1');
        }

        return $userId;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $meta
     */
    public function update(int $userId, array $data, array $meta): void
    {
        if ($data !== []) {
            $data['ID'] = $userId;
            $this->userRepository->update($data);
        }

        foreach ($meta as $key => $value) {
            $this->userRepository->updateMeta($userId, $key, $value);
        }

        // Track lastModified as ISO 8601 for SCIM meta
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(\DateTimeInterface::ATOM);
        $this->userRepository->updateMeta($userId, ScimConstants::META_LAST_MODIFIED, $now);
    }

    public function reactivate(int $userId, string $defaultRole): void
    {
        $this->userRepository->updateMeta($userId, ScimConstants::META_ACTIVE, '1');

        $user = $this->find($userId);
        if ($user !== null && $user->roles === []) {
            $user->set_role($defaultRole);
        }
    }

    public function isActive(int $userId): bool
    {
        $meta = $this->userRepository->getMeta($userId, ScimConstants::META_ACTIVE, true);

        return $meta !== '0';
    }

    public function deactivate(int $userId): void
    {
        $this->userRepository->updateMeta($userId, ScimConstants::META_ACTIVE, '0');

        // Strip all roles
        $user = $this->find($userId);
        if ($user !== null) {
            $user->set_role('');
        }
    }

    public function delete(int $userId): void
    {
        $this->userRepository->delete($userId);
    }
}
