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

namespace WPPack\Component\Security\Bridge\OAuth\Token;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;

final class IdTokenValidator
{
    private const IAT_LEEWAY_SECONDS = 300; // 5 minutes

    /**
     * Validate and decode an ID token.
     *
     * @param array<int, array<string, mixed>> $jwks The JWKS keys array from JwksProvider
     * @return array<string, mixed> Decoded claims
     * @throws \RuntimeException on validation failure
     */
    public function validate(
        #[\SensitiveParameter]
        string $idToken,
        string $nonce,
        string $clientId,
        string $issuer,
        array $jwks,
    ): array {
        try {
            $keySet = JWK::parseKeySet(['keys' => $jwks]);
            $decoded = JWT::decode($idToken, $keySet);
        } catch (\Exception $e) {
            throw new \RuntimeException('ID token validation failed.', 0, $e);
        }

        /** @var array<string, mixed> $claims */
        $claims = (array) $decoded;

        $this->validateIssuer($claims, $issuer);
        $this->validateAudience($claims, $clientId);
        $this->validateExpiration($claims);
        $this->validateIssuedAt($claims);
        $this->validateNonce($claims, $nonce);
        $this->validateAuthorizedParty($claims, $clientId);

        return $claims;
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function validateIssuer(array $claims, string $issuer): void
    {
        if (!isset($claims['iss']) || $claims['iss'] !== $issuer) {
            throw new \RuntimeException('ID token issuer validation failed.');
        }
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function validateAudience(array $claims, string $clientId): void
    {
        if (!isset($claims['aud'])) {
            throw new \RuntimeException('ID token is missing the "aud" claim.');
        }

        $audiences = \is_array($claims['aud']) ? $claims['aud'] : [$claims['aud']];

        if (!\in_array($clientId, $audiences, true)) {
            throw new \RuntimeException('ID token audience validation failed.');
        }
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function validateExpiration(array $claims): void
    {
        if (!isset($claims['exp'])) {
            throw new \RuntimeException('ID token is missing the "exp" claim.');
        }

        if ((int) $claims['exp'] < time()) {
            throw new \RuntimeException('ID token has expired.');
        }
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function validateIssuedAt(array $claims): void
    {
        if (!isset($claims['iat'])) {
            throw new \RuntimeException('ID token is missing the "iat" claim.');
        }

        if ((int) $claims['iat'] > time() + self::IAT_LEEWAY_SECONDS) {
            throw new \RuntimeException('ID token "iat" claim is in the future.');
        }
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function validateNonce(array $claims, string $nonce): void
    {
        if (!isset($claims['nonce']) || !hash_equals($nonce, (string) $claims['nonce'])) {
            throw new \RuntimeException('ID token nonce validation failed.');
        }
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function validateAuthorizedParty(array $claims, string $clientId): void
    {
        if (!isset($claims['azp'])) {
            return;
        }

        $audiences = \is_array($claims['aud']) ? $claims['aud'] : [$claims['aud']];

        if (\count($audiences) > 1 && $claims['azp'] !== $clientId) {
            throw new \RuntimeException('ID token authorized party validation failed.');
        }
    }
}
