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

namespace WpPack\Component\Security\Bridge\OAuth\Provider;

/**
 * Registry of all known OAuth provider types.
 */
final class ProviderRegistry
{
    /** @var array<string, class-string<ProviderInterface>> */
    private const PROVIDERS = [
        'apple' => AppleProvider::class,
        'auth0' => Auth0Provider::class,
        'cognito' => CognitoProvider::class,
        'd-account' => DAccountProvider::class,
        'discord' => DiscordProvider::class,
        'entra-id' => EntraIdProvider::class,
        'facebook' => FacebookProvider::class,
        'github' => GitHubProvider::class,
        'google' => GoogleProvider::class,
        'keycloak' => KeycloakProvider::class,
        'line' => LineProvider::class,
        'microsoft' => MicrosoftProvider::class,
        'okta' => OktaProvider::class,
        'onelogin' => OneLoginProvider::class,
        'slack' => SlackProvider::class,
        'yahoo' => YahooProvider::class,
        'yahoo-japan' => YahooJapanProvider::class,
        'oidc' => GenericOidcProvider::class,
    ];

    /**
     * @return array<string, ProviderDefinition>
     */
    public static function definitions(): array
    {
        $definitions = [];
        foreach (self::PROVIDERS as $type => $class) {
            $definitions[$type] = $class::definition();
        }

        return $definitions;
    }

    public static function definition(string $type): ?ProviderDefinition
    {
        $class = self::PROVIDERS[$type] ?? null;

        return $class !== null ? $class::definition() : null;
    }

    /**
     * @return class-string<ProviderInterface>|null
     */
    public static function providerClass(string $type): ?string
    {
        return self::PROVIDERS[$type] ?? null;
    }

    /**
     * @return list<string>
     */
    public static function types(): array
    {
        return array_keys(self::PROVIDERS);
    }
}
