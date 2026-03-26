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

/**
 * Persists SAML session data (nameId, sessionIndex) in WordPress user meta
 * for use during Single Logout (SLO).
 */
final class SamlSessionManager
{
    private const META_NAME_ID = '_saml_name_id';
    private const META_SESSION_INDEX = '_saml_session_index';

    public function save(int $userId, string $nameId, ?string $sessionIndex): void
    {
        update_user_meta($userId, self::META_NAME_ID, $nameId);

        if ($sessionIndex !== null) {
            update_user_meta($userId, self::META_SESSION_INDEX, $sessionIndex);
        } else {
            delete_user_meta($userId, self::META_SESSION_INDEX);
        }
    }

    public function getNameId(int $userId): ?string
    {
        $value = get_user_meta($userId, self::META_NAME_ID, true);

        return \is_string($value) && $value !== '' ? $value : null;
    }

    public function getSessionIndex(int $userId): ?string
    {
        $value = get_user_meta($userId, self::META_SESSION_INDEX, true);

        return \is_string($value) && $value !== '' ? $value : null;
    }

    public function clear(int $userId): void
    {
        delete_user_meta($userId, self::META_NAME_ID);
        delete_user_meta($userId, self::META_SESSION_INDEX);
    }
}
