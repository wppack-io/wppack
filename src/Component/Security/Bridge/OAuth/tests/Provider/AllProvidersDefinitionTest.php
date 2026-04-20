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

namespace WPPack\Component\Security\Bridge\OAuth\Tests\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Security\Bridge\OAuth\Configuration\OAuthConfiguration;
use WPPack\Component\Security\Bridge\OAuth\Provider\AmazonProvider;
use WPPack\Component\Security\Bridge\OAuth\Provider\AppleProvider;
use WPPack\Component\Security\Bridge\OAuth\Provider\Auth0Provider;
use WPPack\Component\Security\Bridge\OAuth\Provider\CognitoProvider;
use WPPack\Component\Security\Bridge\OAuth\Provider\DAccountProvider;
use WPPack\Component\Security\Bridge\OAuth\Provider\DiscordProvider;
use WPPack\Component\Security\Bridge\OAuth\Provider\EntraIdProvider;
use WPPack\Component\Security\Bridge\OAuth\Provider\FacebookProvider;
use WPPack\Component\Security\Bridge\OAuth\Provider\KeycloakProvider;
use WPPack\Component\Security\Bridge\OAuth\Provider\LineProvider;
use WPPack\Component\Security\Bridge\OAuth\Provider\MicrosoftProvider;
use WPPack\Component\Security\Bridge\OAuth\Provider\OktaProvider;
use WPPack\Component\Security\Bridge\OAuth\Provider\OneLoginProvider;
use WPPack\Component\Security\Bridge\OAuth\Provider\ProviderDefinition;
use WPPack\Component\Security\Bridge\OAuth\Provider\ProviderInterface;
use WPPack\Component\Security\Bridge\OAuth\Provider\SlackProvider;
use WPPack\Component\Security\Bridge\OAuth\Provider\YahooJapanProvider;
use WPPack\Component\Security\Bridge\OAuth\Provider\YahooProvider;

#[CoversClass(AmazonProvider::class)]
#[CoversClass(AppleProvider::class)]
#[CoversClass(Auth0Provider::class)]
#[CoversClass(CognitoProvider::class)]
#[CoversClass(DAccountProvider::class)]
#[CoversClass(DiscordProvider::class)]
#[CoversClass(EntraIdProvider::class)]
#[CoversClass(FacebookProvider::class)]
#[CoversClass(KeycloakProvider::class)]
#[CoversClass(LineProvider::class)]
#[CoversClass(MicrosoftProvider::class)]
#[CoversClass(OktaProvider::class)]
#[CoversClass(OneLoginProvider::class)]
#[CoversClass(SlackProvider::class)]
#[CoversClass(YahooProvider::class)]
#[CoversClass(YahooJapanProvider::class)]
#[CoversClass(ProviderDefinition::class)]
final class AllProvidersDefinitionTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string<ProviderInterface>, string, bool}>
     */
    public static function providerRegistry(): iterable
    {
        // Providers that need only an OAuthConfiguration — can be
        // instantiated cleanly in every test below.
        yield 'amazon' => [AmazonProvider::class, 'amazon', false];
        yield 'apple' => [AppleProvider::class, 'apple', true];
        yield 'd-account' => [DAccountProvider::class, 'd-account', true];
        yield 'discord' => [DiscordProvider::class, 'discord', false];
        yield 'facebook' => [FacebookProvider::class, 'facebook', false];
        yield 'line' => [LineProvider::class, 'line', true];
        yield 'microsoft' => [MicrosoftProvider::class, 'microsoft', true];
        yield 'slack' => [SlackProvider::class, 'slack', true];
        yield 'yahoo' => [YahooProvider::class, 'yahoo', true];
        yield 'yahoo-japan' => [YahooJapanProvider::class, 'yahoo-japan', true];
    }

    /**
     * @return iterable<string, array{class-string<ProviderInterface>, string, bool, string}>
     */
    public static function tenantScopedProviderRegistry(): iterable
    {
        // Providers that take an extra tenant / domain string.
        yield 'auth0' => [Auth0Provider::class, 'auth0', true, 'example.auth0.com'];
        yield 'cognito' => [CognitoProvider::class, 'cognito', true, 'example.auth.us-east-1.amazoncognito.com'];
        yield 'entra-id' => [EntraIdProvider::class, 'entra-id', true, 'common'];
        yield 'keycloak' => [KeycloakProvider::class, 'keycloak', true, 'https://keycloak.example.com/realms/master'];
        yield 'okta' => [OktaProvider::class, 'okta', true, 'example.okta.com'];
        yield 'onelogin' => [OneLoginProvider::class, 'onelogin', true, 'example.onelogin.com'];
    }

    private static function configuration(): OAuthConfiguration
    {
        return new OAuthConfiguration(
            clientId: 'client-abc',
            clientSecret: 'secret',
            redirectUri: 'https://wp.example.com/oauth/callback',
            pkceEnabled: true,
        );
    }

    /**
     * @param class-string<ProviderInterface> $providerClass
     */
    #[Test]
    #[DataProvider('providerRegistry')]
    public function definitionExposesExpectedMetadata(string $providerClass, string $expectedType, bool $expectedOidc): void
    {
        /** @var callable(): ProviderDefinition $definitionFactory */
        $definitionFactory = [$providerClass, 'definition'];
        $def = $definitionFactory();

        self::assertInstanceOf(ProviderDefinition::class, $def);
        self::assertSame($expectedType, $def->type);
        self::assertSame($expectedOidc, $def->oidc);
        self::assertNotSame('', $def->label, 'label must not be empty');
        self::assertNotSame('', $def->dropdownLabel, 'dropdownLabel must not be empty');
    }

    /**
     * @param class-string<ProviderInterface> $providerClass
     */
    #[Test]
    #[DataProvider('tenantScopedProviderRegistry')]
    public function tenantScopedDefinitionExposesExpectedMetadata(string $providerClass, string $expectedType, bool $expectedOidc, string $_scope): void
    {
        /** @var callable(): ProviderDefinition $definitionFactory */
        $definitionFactory = [$providerClass, 'definition'];
        $def = $definitionFactory();

        self::assertSame($expectedType, $def->type);
        self::assertSame($expectedOidc, $def->oidc);
    }

    /**
     * @param class-string<ProviderInterface> $providerClass
     */
    #[Test]
    #[DataProvider('providerRegistry')]
    public function authorizationUrlContainsClientIdAndStateAndRedirectUri(string $providerClass, string $_type, bool $_oidc): void
    {
        /** @var ProviderInterface $provider */
        $provider = new $providerClass(self::configuration());

        $url = $provider->getAuthorizationUrl('state-123', 'nonce-456', codeChallenge: 'challenge-xyz');

        self::assertStringContainsString('client_id=client-abc', $url);
        self::assertStringContainsString('state=state-123', $url);
        self::assertStringContainsString(rawurlencode('https://wp.example.com/oauth/callback'), $url);
    }

    /**
     * @param class-string<ProviderInterface> $providerClass
     */
    #[Test]
    #[DataProvider('tenantScopedProviderRegistry')]
    public function tenantScopedAuthorizationUrlContainsClientIdAndState(string $providerClass, string $_type, bool $_oidc, string $scope): void
    {
        /** @var ProviderInterface $provider */
        $provider = new $providerClass(self::configuration(), $scope);

        $url = $provider->getAuthorizationUrl('state-123', 'nonce-456');

        self::assertStringContainsString('client_id=client-abc', $url);
        self::assertStringContainsString('state=state-123', $url);
    }

    /**
     * @param class-string<ProviderInterface> $providerClass
     */
    #[Test]
    #[DataProvider('providerRegistry')]
    public function supportsOidcAgreesWithDefinition(string $providerClass, string $_type, bool $expectedOidc): void
    {
        /** @var ProviderInterface $provider */
        $provider = new $providerClass(self::configuration());

        self::assertSame($expectedOidc, $provider->supportsOidc());
    }

    #[Test]
    public function entraIdRejectsInvalidTenantIdFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new EntraIdProvider(self::configuration(), 'bogus tenant with spaces');
    }
}
