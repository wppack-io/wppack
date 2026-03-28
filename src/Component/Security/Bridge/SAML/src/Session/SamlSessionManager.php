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

namespace WpPack\Component\Security\Bridge\SAML\Session;

use WpPack\Component\User\UserRepositoryInterface;

/**
 * Persists SAML session data (nameId, sessionIndex) in WordPress user meta
 * for use during Single Logout (SLO).
 */
final class SamlSessionManager
{
    private const META_NAME_ID = '_saml_name_id';
    private const META_SESSION_INDEX = '_saml_session_index';

    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    public function save(int $userId, string $nameId, ?string $sessionIndex): void
    {
        $this->updateMeta($userId, self::META_NAME_ID, $nameId);

        if ($sessionIndex !== null) {
            $this->updateMeta($userId, self::META_SESSION_INDEX, $sessionIndex);
        } else {
            $this->deleteMeta($userId, self::META_SESSION_INDEX);
        }
    }

    public function getNameId(int $userId): ?string
    {
        $value = $this->getMeta($userId, self::META_NAME_ID);

        return \is_string($value) && $value !== '' ? $value : null;
    }

    public function getSessionIndex(int $userId): ?string
    {
        $value = $this->getMeta($userId, self::META_SESSION_INDEX);

        return \is_string($value) && $value !== '' ? $value : null;
    }

    public function clear(int $userId): void
    {
        $this->deleteMeta($userId, self::META_NAME_ID);
        $this->deleteMeta($userId, self::META_SESSION_INDEX);
    }

    private function getMeta(int $userId, string $key): mixed
    {
        return $this->userRepository->getMeta($userId, $key, true);
    }

    private function updateMeta(int $userId, string $key, string $value): void
    {
        $this->userRepository->updateMeta($userId, $key, $value);
    }

    private function deleteMeta(int $userId, string $key): void
    {
        $this->userRepository->deleteMeta($userId, $key);
    }
}
