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

namespace WPPack\Component\Security\Bridge\Passkey\Storage;

final class AaguidResolver
{
    /**
     * Known AAGUID to device name mapping.
     *
     * @var array<string, string>
     */
    private const KNOWN_AAGUIDS = [
        // Apple
        'fbfc3007-154e-4ecc-8c0b-6e020557d7bd' => 'iCloud Passkey',
        '00000000-0000-0000-0000-000000000000' => 'Passkey',
        // Google
        'ea9b8d66-4d01-1d21-3ce4-b6b48cb575d4' => 'Google Password Manager',
        'b5397571-f535-631a-ffb9-88f59a059b0e' => 'Google Password Manager',
        // Microsoft
        '0ea242b4-43c4-4a1b-8b17-dd6d0b6baec6' => 'Windows Hello',
        '6028b017-b1d4-4c02-b4b3-afcdafc96bb2' => 'Windows Hello',
        '9ddd1817-af5a-4672-a2b9-3e3dd95000a9' => 'Windows Hello',
        // YubiKey
        'cb69481e-8ff7-4039-93ec-0a2729a154a8' => 'YubiKey 5 NFC',
        'ee882879-721c-4913-9775-3dfcce97072a' => 'YubiKey 5 Nano',
        'fa2b99dc-9e39-4257-8f92-4a30d23c4118' => 'YubiKey 5 NFC FIPS',
        'c5ef55ff-ad9a-4b9f-b580-adebafe026d0' => 'YubiKey 5Ci',
        '2fc0579f-8113-47ea-b116-bb5a8db9202a' => 'YubiKey 5 NFC',
        '73bb0cd4-e502-49b8-9c6f-b59445bf720b' => 'YubiKey 5 FIPS',
        'd8522d9f-575b-4866-88a9-ba99fa02f35b' => 'YubiKey Bio',
        // 1Password
        'bada5566-a7aa-401f-bd96-45619a55120d' => '1Password',
        // Bitwarden
        'd548826e-79b4-db40-a3d8-11116f7e8349' => 'Bitwarden',
    ];

    public static function resolve(string $aaguid): string
    {
        return self::KNOWN_AAGUIDS[strtolower($aaguid)] ?? 'Passkey';
    }
}
