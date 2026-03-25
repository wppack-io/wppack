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

namespace WpPack\Component\Security\Bridge\OAuth\Pkce;

final class PkceGenerator
{
    /**
     * @return array{code_verifier: string, code_challenge: string, code_challenge_method: string}
     */
    public static function generate(): array
    {
        $verifier = self::generateVerifier();

        return [
            'code_verifier' => $verifier,
            'code_challenge' => self::computeChallenge($verifier),
            'code_challenge_method' => 'S256',
        ];
    }

    public static function generateVerifier(int $length = 128): string
    {
        if ($length < 43 || $length > 128) {
            throw new \InvalidArgumentException('Code verifier length must be between 43 and 128 characters.');
        }

        $bytes = random_bytes((int) ceil($length * 3 / 4));

        return substr(self::base64UrlEncode($bytes), 0, $length);
    }

    public static function computeChallenge(string $verifier): string
    {
        return self::base64UrlEncode(hash('sha256', $verifier, true));
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
