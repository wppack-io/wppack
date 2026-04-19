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

namespace WPPack\Component\Security\Bridge\OAuth\Tests\Pkce;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Security\Bridge\OAuth\Pkce\PkceGenerator;

#[CoversClass(PkceGenerator::class)]
final class PkceGeneratorTest extends TestCase
{
    #[Test]
    public function generateReturnsExpectedKeys(): void
    {
        $result = PkceGenerator::generate();

        self::assertArrayHasKey('code_verifier', $result);
        self::assertArrayHasKey('code_challenge', $result);
        self::assertArrayHasKey('code_challenge_method', $result);
        self::assertSame('S256', $result['code_challenge_method']);
    }

    #[Test]
    public function generateVerifierDefaultLength(): void
    {
        $verifier = PkceGenerator::generateVerifier();

        self::assertSame(128, strlen($verifier));
    }

    #[Test]
    public function generateVerifierCustomLength(): void
    {
        $verifier = PkceGenerator::generateVerifier(43);

        self::assertSame(43, strlen($verifier));
    }

    #[Test]
    public function generateVerifierUsesBase64UrlCharacters(): void
    {
        $verifier = PkceGenerator::generateVerifier();

        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $verifier);
    }

    #[Test]
    public function generateVerifierThrowsForLengthBelowMinimum(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Code verifier length must be between 43 and 128 characters.');

        PkceGenerator::generateVerifier(42);
    }

    #[Test]
    public function generateVerifierThrowsForLengthAboveMaximum(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Code verifier length must be between 43 and 128 characters.');

        PkceGenerator::generateVerifier(129);
    }

    #[Test]
    public function computeChallengeProducesBase64UrlEncodedHash(): void
    {
        $challenge = PkceGenerator::computeChallenge('test-verifier');

        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $challenge);
        self::assertStringNotContainsString('=', $challenge);
        self::assertStringNotContainsString('+', $challenge);
        self::assertStringNotContainsString('/', $challenge);
    }

    #[Test]
    public function computeChallengeIsDeterministic(): void
    {
        $challenge1 = PkceGenerator::computeChallenge('same-verifier');
        $challenge2 = PkceGenerator::computeChallenge('same-verifier');

        self::assertSame($challenge1, $challenge2);
    }

    #[Test]
    public function computeChallengeDifferentVerifiersProduceDifferentChallenges(): void
    {
        $challenge1 = PkceGenerator::computeChallenge('verifier-one');
        $challenge2 = PkceGenerator::computeChallenge('verifier-two');

        self::assertNotSame($challenge1, $challenge2);
    }

    #[Test]
    public function generateProducesValidVerifierAndChallengePair(): void
    {
        $result = PkceGenerator::generate();

        $expectedChallenge = PkceGenerator::computeChallenge($result['code_verifier']);

        self::assertSame($expectedChallenge, $result['code_challenge']);
    }

    #[Test]
    public function generateVerifierIsRandom(): void
    {
        $verifier1 = PkceGenerator::generateVerifier();
        $verifier2 = PkceGenerator::generateVerifier();

        self::assertNotSame($verifier1, $verifier2);
    }

    #[Test]
    public function computeChallengeMatchesRfc7636Example(): void
    {
        // RFC 7636 Appendix B test vector
        $verifier = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';
        $expectedChallenge = 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM';

        self::assertSame($expectedChallenge, PkceGenerator::computeChallenge($verifier));
    }
}
