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

namespace WpPack\Component\Security\Bridge\OAuth\Tests\Token;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Security\Bridge\OAuth\Token\DiscoveryDocument;

#[CoversClass(DiscoveryDocument::class)]
final class DiscoveryDocumentTest extends TestCase
{
    #[Test]
    public function requiredGetters(): void
    {
        $doc = new DiscoveryDocument(
            issuer: 'https://idp.example.com',
            authorizationEndpoint: 'https://idp.example.com/authorize',
            tokenEndpoint: 'https://idp.example.com/token',
        );

        self::assertSame('https://idp.example.com', $doc->getIssuer());
        self::assertSame('https://idp.example.com/authorize', $doc->getAuthorizationEndpoint());
        self::assertSame('https://idp.example.com/token', $doc->getTokenEndpoint());
    }

    #[Test]
    public function optionalFieldsDefaultToNull(): void
    {
        $doc = new DiscoveryDocument(
            issuer: 'https://idp.example.com',
            authorizationEndpoint: 'https://idp.example.com/authorize',
            tokenEndpoint: 'https://idp.example.com/token',
        );

        self::assertNull($doc->getUserinfoEndpoint());
        self::assertNull($doc->getJwksUri());
        self::assertNull($doc->getEndSessionEndpoint());
        self::assertNull($doc->getRevocationEndpoint());
    }

    #[Test]
    public function allGetters(): void
    {
        $doc = new DiscoveryDocument(
            issuer: 'https://idp.example.com',
            authorizationEndpoint: 'https://idp.example.com/authorize',
            tokenEndpoint: 'https://idp.example.com/token',
            userinfoEndpoint: 'https://idp.example.com/userinfo',
            jwksUri: 'https://idp.example.com/.well-known/jwks.json',
            endSessionEndpoint: 'https://idp.example.com/logout',
            revocationEndpoint: 'https://idp.example.com/revoke',
        );

        self::assertSame('https://idp.example.com/userinfo', $doc->getUserinfoEndpoint());
        self::assertSame('https://idp.example.com/.well-known/jwks.json', $doc->getJwksUri());
        self::assertSame('https://idp.example.com/logout', $doc->getEndSessionEndpoint());
        self::assertSame('https://idp.example.com/revoke', $doc->getRevocationEndpoint());
    }

    #[Test]
    public function fromArrayWithFullDocument(): void
    {
        $data = [
            'issuer' => 'https://accounts.google.com',
            'authorization_endpoint' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_endpoint' => 'https://oauth2.googleapis.com/token',
            'userinfo_endpoint' => 'https://openidconnect.googleapis.com/v1/userinfo',
            'jwks_uri' => 'https://www.googleapis.com/oauth2/v3/certs',
            'end_session_endpoint' => 'https://accounts.google.com/logout',
            'revocation_endpoint' => 'https://oauth2.googleapis.com/revoke',
        ];

        $doc = DiscoveryDocument::fromArray($data);

        self::assertSame('https://accounts.google.com', $doc->getIssuer());
        self::assertSame('https://accounts.google.com/o/oauth2/v2/auth', $doc->getAuthorizationEndpoint());
        self::assertSame('https://oauth2.googleapis.com/token', $doc->getTokenEndpoint());
        self::assertSame('https://openidconnect.googleapis.com/v1/userinfo', $doc->getUserinfoEndpoint());
        self::assertSame('https://www.googleapis.com/oauth2/v3/certs', $doc->getJwksUri());
        self::assertSame('https://accounts.google.com/logout', $doc->getEndSessionEndpoint());
        self::assertSame('https://oauth2.googleapis.com/revoke', $doc->getRevocationEndpoint());
    }

    #[Test]
    public function fromArrayWithMinimalDocument(): void
    {
        $data = [
            'issuer' => 'https://idp.example.com',
            'authorization_endpoint' => 'https://idp.example.com/authorize',
            'token_endpoint' => 'https://idp.example.com/token',
        ];

        $doc = DiscoveryDocument::fromArray($data);

        self::assertSame('https://idp.example.com', $doc->getIssuer());
        self::assertSame('https://idp.example.com/authorize', $doc->getAuthorizationEndpoint());
        self::assertSame('https://idp.example.com/token', $doc->getTokenEndpoint());
        self::assertNull($doc->getUserinfoEndpoint());
        self::assertNull($doc->getJwksUri());
        self::assertNull($doc->getEndSessionEndpoint());
        self::assertNull($doc->getRevocationEndpoint());
    }

    #[Test]
    public function fromArrayRejectsHttpAuthorizationEndpoint(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Endpoint "authorization_endpoint" must use HTTPS.');

        DiscoveryDocument::fromArray([
            'issuer' => 'https://idp.example.com',
            'authorization_endpoint' => 'http://idp.example.com/authorize',
            'token_endpoint' => 'https://idp.example.com/token',
        ]);
    }

    #[Test]
    public function fromArrayRejectsHttpTokenEndpoint(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Endpoint "token_endpoint" must use HTTPS.');

        DiscoveryDocument::fromArray([
            'issuer' => 'https://idp.example.com',
            'authorization_endpoint' => 'https://idp.example.com/authorize',
            'token_endpoint' => 'http://idp.example.com/token',
        ]);
    }

    #[Test]
    public function fromArrayRejectsHttpJwksUri(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Endpoint "jwks_uri" must use HTTPS.');

        DiscoveryDocument::fromArray([
            'issuer' => 'https://idp.example.com',
            'authorization_endpoint' => 'https://idp.example.com/authorize',
            'token_endpoint' => 'https://idp.example.com/token',
            'jwks_uri' => 'http://idp.example.com/.well-known/jwks.json',
        ]);
    }

    #[Test]
    public function fromArrayRejectsHttpUserinfoEndpoint(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Endpoint "userinfo_endpoint" must use HTTPS.');

        DiscoveryDocument::fromArray([
            'issuer' => 'https://idp.example.com',
            'authorization_endpoint' => 'https://idp.example.com/authorize',
            'token_endpoint' => 'https://idp.example.com/token',
            'userinfo_endpoint' => 'http://idp.example.com/userinfo',
        ]);
    }

    #[Test]
    public function fromArrayRejectsHttpEndSessionEndpoint(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Endpoint "end_session_endpoint" must use HTTPS.');

        DiscoveryDocument::fromArray([
            'issuer' => 'https://idp.example.com',
            'authorization_endpoint' => 'https://idp.example.com/authorize',
            'token_endpoint' => 'https://idp.example.com/token',
            'end_session_endpoint' => 'http://idp.example.com/logout',
        ]);
    }
}
