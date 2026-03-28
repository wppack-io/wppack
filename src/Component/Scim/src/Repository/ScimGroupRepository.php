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

use WpPack\Component\Role\RoleProvider;
use WpPack\Component\Scim\Exception\InvalidValueException;
use WpPack\Component\User\UserRepositoryInterface;

final readonly class ScimGroupRepository
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private RoleProvider $roleProvider,
    ) {}

    /**
     * @return array{name: string, capabilities: array<string, bool>}|null
     */
    public function findByName(string $roleName): ?array
    {
        return $this->roleProvider->find($roleName);
    }

    /**
     * @return array{groups: list<array{roleName: string, role: array{name: string, capabilities: array<string, bool>}, members: list<\WP_User>}>, totalResults: int}
     */
    public function findAll(int $startIndex, int $count): array
    {
        $allRoles = $this->roleProvider->all();
        $totalResults = \count($allRoles);

        $roleNames = array_keys($allRoles);
        $sliced = \array_slice($roleNames, max(0, $startIndex - 1), $count);

        $groups = [];
        foreach ($sliced as $roleName) {
            $groups[] = [
                'roleName' => $roleName,
                'role' => $allRoles[$roleName],
                'members' => $this->getMembersOfRole($roleName),
            ];
        }

        return [
            'groups' => $groups,
            'totalResults' => $totalResults,
        ];
    }

    /**
     * @param array<string, bool> $capabilities
     */
    public function create(string $name, string $label, array $capabilities = []): void
    {
        $this->roleProvider->add($name, $label, $capabilities);
    }

    public function update(string $name, string $label): void
    {
        $this->roleProvider->updateLabel($name, $label);
    }

    public function delete(string $name): void
    {
        $this->roleProvider->remove($name);
    }

    /**
     * @param list<int> $userIds
     */
    public function setMembers(string $roleName, array $userIds): void
    {
        // Validate all user IDs upfront to prevent partial membership changes
        foreach ($userIds as $userId) {
            if ($this->userRepository->find($userId) === null) {
                throw new InvalidValueException(sprintf('User "%d" does not exist.', $userId));
            }
        }

        $currentMembers = $this->getMembersOfRole($roleName);
        $currentIds = array_map(static fn(\WP_User $u): int => $u->ID, $currentMembers);

        $toAdd = array_diff($userIds, $currentIds);
        $toRemove = array_diff($currentIds, $userIds);

        foreach ($toAdd as $userId) {
            $this->addMember($roleName, $userId);
        }

        foreach ($toRemove as $userId) {
            $this->removeMember($roleName, $userId);
        }
    }

    public function addMember(string $roleName, int $userId): void
    {
        $user = $this->userRepository->find($userId);

        if ($user === null) {
            throw new InvalidValueException(sprintf('User "%d" does not exist.', $userId));
        }

        $user->add_role($roleName);
    }

    public function removeMember(string $roleName, int $userId): void
    {
        $user = $this->userRepository->find($userId);

        if ($user === null) {
            throw new InvalidValueException(sprintf('User "%d" does not exist.', $userId));
        }

        $user->remove_role($roleName);
    }

    /**
     * @return list<\WP_User>
     */
    public function getMembersOfRole(string $roleName): array
    {
        return $this->userRepository->findAll(['role' => $roleName]);
    }
}
