<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Bridge\OAuth\Tests\Token;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Security\Bridge\OAuth\Token\IdTokenValidator;

#[CoversClass(IdTokenValidator::class)]
final class IdTokenValidatorTest extends TestCase
{
    private IdTokenValidator $validator;
    private \OpenSSLAsymmetricKey $privateKey;

    /** @var array<string, mixed> */
    private array $jwks;

    private string $kid = 'test-key-1';
    private string $issuer = 'https://idp.example.com';
    private string $clientId = 'test-client-id';
    private string $nonce = 'test-nonce-abc';

    protected function setUp(): void
    {
        if (!class_exists(JWT::class)) {
            self::markTestSkipped('firebase/php-jwt is not installed.');
        }

        $this->validator = new IdTokenValidator();

        $resource = openssl_pkey_new([
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => \OPENSSL_KEYTYPE_RSA,
        ]);
        \assert($resource instanceof \OpenSSLAsymmetricKey);
        $this->privateKey = $resource;

        $details = openssl_pkey_get_details($this->privateKey);
        \assert(\is_array($details));

        $this->jwks = [
            [
                'kty' => 'RSA',
                'kid' => $this->kid,
                'use' => 'sig',
                'alg' => 'RS256',
                'n' => rtrim(strtr(base64_encode($details['rsa']['n']), '+/', '-_'), '='),
                'e' => rtrim(strtr(base64_encode($details['rsa']['e']), '+/', '-_'), '='),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createIdToken(array $overrides = []): string
    {
        $payload = array_merge([
            'iss' => $this->issuer,
            'aud' => $this->clientId,
            'sub' => 'user-123',
            'exp' => time() + 3600,
            'iat' => time() - 10,
            'nonce' => $this->nonce,
        ], $overrides);

        return JWT::encode($payload, $this->privateKey, 'RS256', $this->kid);
    }

    #[Test]
    public function validTokenReturnsDecodedClaims(): void
    {
        $token = $this->createIdToken();

        $claims = $this->validator->validate(
            $token,
            $this->nonce,
            $this->clientId,
            $this->issuer,
            $this->jwks,
        );

        self::assertSame($this->issuer, $claims['iss']);
        self::assertSame($this->clientId, $claims['aud']);
        self::assertSame('user-123', $claims['sub']);
        self::assertSame($this->nonce, $claims['nonce']);
    }

    #[Test]
    public function expiredTokenThrowsException(): void
    {
        $token = $this->createIdToken(['exp' => time() - 3600]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ID token validation failed.');

        $this->validator->validate(
            $token,
            $this->nonce,
            $this->clientId,
            $this->issuer,
            $this->jwks,
        );
    }

    #[Test]
    public function wrongNonceThrowsException(): void
    {
        $token = $this->createIdToken();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('nonce validation failed');

        $this->validator->validate(
            $token,
            'wrong-nonce',
            $this->clientId,
            $this->issuer,
            $this->jwks,
        );
    }

    #[Test]
    public function wrongIssuerThrowsException(): void
    {
        $token = $this->createIdToken();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('issuer validation failed');

        $this->validator->validate(
            $token,
            $this->nonce,
            $this->clientId,
            'https://wrong-issuer.example.com',
            $this->jwks,
        );
    }

    #[Test]
    public function wrongAudienceThrowsException(): void
    {
        $token = $this->createIdToken();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('audience validation failed');

        $this->validator->validate(
            $token,
            $this->nonce,
            'wrong-client-id',
            $this->issuer,
            $this->jwks,
        );
    }

    #[Test]
    public function missingNonceThrowsException(): void
    {
        $payload = [
            'iss' => $this->issuer,
            'aud' => $this->clientId,
            'sub' => 'user-123',
            'exp' => time() + 3600,
            'iat' => time() - 10,
        ];
        $token = JWT::encode($payload, $this->privateKey, 'RS256', $this->kid);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('nonce validation failed');

        $this->validator->validate(
            $token,
            $this->nonce,
            $this->clientId,
            $this->issuer,
            $this->jwks,
        );
    }

    #[Test]
    public function iatInFarFutureThrowsException(): void
    {
        $token = $this->createIdToken(['iat' => time() + 600]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ID token validation failed.');

        $this->validator->validate(
            $token,
            $this->nonce,
            $this->clientId,
            $this->issuer,
            $this->jwks,
        );
    }

    #[Test]
    public function arrayAudienceWithMatchingClientId(): void
    {
        $token = $this->createIdToken([
            'aud' => [$this->clientId, 'other-client'],
        ]);

        $claims = $this->validator->validate(
            $token,
            $this->nonce,
            $this->clientId,
            $this->issuer,
            $this->jwks,
        );

        self::assertSame([$this->clientId, 'other-client'], $claims['aud']);
    }

    #[Test]
    public function azpValidationWithMultipleAudiences(): void
    {
        $token = $this->createIdToken([
            'aud' => [$this->clientId, 'other-client'],
            'azp' => $this->clientId,
        ]);

        $claims = $this->validator->validate(
            $token,
            $this->nonce,
            $this->clientId,
            $this->issuer,
            $this->jwks,
        );

        self::assertSame($this->clientId, $claims['azp']);
    }

    #[Test]
    public function azpMismatchWithMultipleAudiencesThrowsException(): void
    {
        $token = $this->createIdToken([
            'aud' => [$this->clientId, 'other-client'],
            'azp' => 'other-client',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('authorized party validation failed');

        $this->validator->validate(
            $token,
            $this->nonce,
            $this->clientId,
            $this->issuer,
            $this->jwks,
        );
    }

    #[Test]
    public function invalidJwkThrowsException(): void
    {
        $token = $this->createIdToken();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ID token validation failed.');

        $this->validator->validate(
            $token,
            $this->nonce,
            $this->clientId,
            $this->issuer,
            [['kty' => 'RSA', 'kid' => 'wrong-key', 'n' => 'invalid', 'e' => 'AQAB']],
        );
    }
}
