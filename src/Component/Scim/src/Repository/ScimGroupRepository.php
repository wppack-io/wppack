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

namespace WPPack\Component\Scim\Repository;

use WPPack\Component\Role\RoleProvider;
use WPPack\Component\Scim\Exception\InvalidValueException;
use WPPack\Component\Scim\Schema\ScimConstants;
use WPPack\Component\Site\BlogSwitcherInterface;
use WPPack\Component\Site\SiteRepositoryInterface;
use WPPack\Component\User\UserRepositoryInterface;

final readonly class ScimGroupRepository
{
    use MultisiteAwareTrait;

    public function __construct(
        private UserRepositoryInterface $userRepository,
        private RoleProvider $roleProvider,
        private ?BlogSwitcherInterface $blogSwitcher = null,
        private ?SiteRepositoryInterface $siteRepository = null,
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
        $this->forEachSite(fn() => $this->roleProvider->add($name, $label, $capabilities));
    }

    public function update(string $name, string $label): void
    {
        $this->forEachSite(fn() => $this->roleProvider->updateLabel($name, $label));
    }

    public function delete(string $name): void
    {
        $members = $this->getMembersOfRole($name);
        foreach ($members as $member) {
            $this->userRepository->deleteMeta($member->ID, ScimConstants::META_GROUP_PREFIX . $name);
        }

        $this->forEachSite(fn() => $this->roleProvider->remove($name));
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

        $this->userRepository->updateMeta($userId, ScimConstants::META_GROUP_PREFIX . $roleName, '1');
    }

    public function removeMember(string $roleName, int $userId): void
    {
        $user = $this->userRepository->find($userId);

        if ($user === null) {
            throw new InvalidValueException(sprintf('User "%d" does not exist.', $userId));
        }

        $this->userRepository->deleteMeta($userId, ScimConstants::META_GROUP_PREFIX . $roleName);
    }

    /**
     * @return list<\WP_User>
     */
    public function getMembersOfRole(string $roleName): array
    {
        return $this->userRepository->findAll([
            'meta_key' => ScimConstants::META_GROUP_PREFIX . $roleName,
            'meta_value' => '1',
        ]);
    }

    /**
     * @return list<string> Role names the user belongs to via SCIM
     */
    public function getGroupNamesForUser(int $userId): array
    {
        $allMeta = $this->userRepository->getMeta($userId);
        if (!\is_array($allMeta)) {
            return [];
        }

        $prefix = ScimConstants::META_GROUP_PREFIX;
        $prefixLen = \strlen($prefix);
        $groups = [];

        foreach ($allMeta as $key => $values) {
            if (str_starts_with($key, $prefix) && ($values[0] ?? '') === '1') {
                $groups[] = substr($key, $prefixLen);
            }
        }

        return $groups;
    }
}
