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

namespace WpPack\Plugin\OAuthLoginPlugin\DependencyInjection;

use Psr\EventDispatcher\EventDispatcherInterface;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Reference;
use WpPack\Component\DependencyInjection\ServiceProviderInterface;
use WpPack\Component\EventDispatcher\DependencyInjection\EventDispatcherServiceProvider;
use WpPack\Component\EventDispatcher\EventDispatcher;
use WpPack\Component\HttpClient\DependencyInjection\HttpClientServiceProvider;
use WpPack\Component\HttpClient\HttpClient;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Routing\RouteRegistry;
use WpPack\Component\Sanitizer\Sanitizer;
use WpPack\Component\Security\Authentication\AuthenticationManager;
use WpPack\Component\Security\Authentication\AuthenticationManagerInterface;
use WpPack\Component\Security\AuthenticationSession;
use WpPack\Component\Security\Bridge\OAuth\Configuration\OAuthConfiguration;
use WpPack\Component\Security\Bridge\OAuth\Multisite\CrossSiteRedirector;
use WpPack\Component\Security\Bridge\OAuth\OAuthAuthenticator;
use WpPack\Component\Security\Bridge\OAuth\OAuthEntryPoint;
use WpPack\Component\Security\Bridge\OAuth\Provider\AppleProvider;
use WpPack\Component\Security\Bridge\OAuth\Provider\Auth0Provider;
use WpPack\Component\Security\Bridge\OAuth\Provider\CognitoProvider;
use WpPack\Component\Security\Bridge\OAuth\Provider\DAccountProvider;
use WpPack\Component\Security\Bridge\OAuth\Provider\DiscordProvider;
use WpPack\Component\Security\Bridge\OAuth\Provider\EntraIdProvider;
use WpPack\Component\Security\Bridge\OAuth\Provider\FacebookProvider;
use WpPack\Component\Security\Bridge\OAuth\Provider\GenericOidcProvider;
use WpPack\Component\Security\Bridge\OAuth\Provider\GitHubProvider;
use WpPack\Component\Security\Bridge\OAuth\Provider\GoogleProvider;
use WpPack\Component\Security\Bridge\OAuth\Provider\KeycloakProvider;
use WpPack\Component\Security\Bridge\OAuth\Provider\LineProvider;
use WpPack\Component\Security\Bridge\OAuth\Provider\OktaProvider;
use WpPack\Component\Security\Bridge\OAuth\Provider\OneLoginProvider;
use WpPack\Component\Security\Bridge\OAuth\Provider\ProviderInterface;
use WpPack\Component\Security\Bridge\OAuth\Provider\ProviderRegistry;
use WpPack\Component\Security\Bridge\OAuth\Provider\SlackProvider;
use WpPack\Component\Security\Bridge\OAuth\Provider\YahooJapanProvider;
use WpPack\Component\Security\Bridge\OAuth\Provider\YahooProvider;
use WpPack\Component\Security\Bridge\OAuth\State\OAuthStateStore;
use WpPack\Component\Security\Bridge\OAuth\Token\IdTokenValidator;
use WpPack\Component\Security\Bridge\OAuth\Token\JwksProvider;
use WpPack\Component\Security\Bridge\OAuth\Token\TokenExchanger;
use WpPack\Component\Security\Bridge\OAuth\UserResolution\OAuthUserResolver;
use WpPack\Component\Security\Bridge\OAuth\OAuthCallbackController;
use WpPack\Component\Security\Bridge\OAuth\OAuthVerifyController;
use WpPack\Component\Security\Bridge\OAuth\UserResolution\OAuthUserResolverInterface;
use WpPack\Component\Security\DependencyInjection\SecurityServiceProvider;
use WpPack\Component\Site\BlogContext;
use WpPack\Component\Site\BlogContextInterface;
use WpPack\Component\Site\SiteRepository;
use WpPack\Component\Site\SiteRepositoryInterface;
use WpPack\Component\Transient\TransientManager;
use WpPack\Component\User\UserRepositoryInterface;
use WpPack\Component\Admin\AdminPageRegistry;
use WpPack\Component\Rest\RestRegistry;
use WpPack\Component\Role\RoleProvider;
use WpPack\Plugin\OAuthLoginPlugin\Admin\OAuthLoginSettingsController;
use WpPack\Plugin\OAuthLoginPlugin\Admin\OAuthLoginSettingsPage;
use WpPack\Plugin\OAuthLoginPlugin\Configuration\OAuthLoginConfiguration;
use WpPack\Plugin\OAuthLoginPlugin\Configuration\ProviderConfiguration;
use WpPack\Plugin\OAuthLoginPlugin\OAuthLoginForm;

final class OAuthLoginPluginServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        // Auto-register dependent service providers
        if (!$builder->hasDefinition(EventDispatcher::class)) {
            (new EventDispatcherServiceProvider())->register($builder);
        }

        if (!$builder->hasDefinition(AuthenticationManager::class)) {
            (new SecurityServiceProvider())->register($builder);
        }

        if (!$builder->hasDefinition(HttpClient::class)) {
            (new HttpClientServiceProvider())->register($builder);
        }

        // Sanitizer
        if (!$builder->hasDefinition(Sanitizer::class)) {
            $builder->register(Sanitizer::class);
        }

        // Blog Context
        if (!$builder->hasDefinition(BlogContextInterface::class)) {
            $builder->register(BlogContextInterface::class, BlogContext::class);
        }

        // Site Repository (multisite)
        if (!$builder->hasDefinition(SiteRepositoryInterface::class)) {
            $builder->register(SiteRepositoryInterface::class, SiteRepository::class);
        }

        // Transient Manager
        if (!$builder->hasDefinition(TransientManager::class)) {
            $builder->register(TransientManager::class);
        }

        // Admin Page Registry
        if (!$builder->hasDefinition(AdminPageRegistry::class)) {
            $builder->register(AdminPageRegistry::class);
        }

        // REST Registry
        if (!$builder->hasDefinition(RestRegistry::class)) {
            $builder->register(RestRegistry::class)
                ->addArgument(new Reference(Request::class));
        }

        // Role Provider
        if (!$builder->hasDefinition(RoleProvider::class)) {
            $builder->register(RoleProvider::class);
        }

        // Read configuration eagerly so per-provider services can be registered
        $config = OAuthLoginConfiguration::fromEnvironmentOrOptions();

        $builder->register(OAuthLoginConfiguration::class)
            ->setFactory([self::class, 'returnConfig'])
            ->addArgument($config);

        // Admin Settings Page
        $builder->register(OAuthLoginSettingsPage::class);

        // REST API Settings Controller
        $builder->register(OAuthLoginSettingsController::class)
            ->addArgument(new Reference(OAuthLoginConfiguration::class))
            ->addArgument(new Reference(Sanitizer::class))
            ->addArgument(new Reference(RoleProvider::class));

        // Skip OAuth service registration if no providers configured
        if ($config->providers === []) {
            return;
        }

        // Shared services
        $builder->register(OAuthStateStore::class)
            ->addArgument(new Reference(TransientManager::class));

        $builder->register(TokenExchanger::class)
            ->addArgument(new Reference(HttpClient::class));

        $builder->register(IdTokenValidator::class);

        $builder->register(JwksProvider::class)
            ->addArgument(new Reference(HttpClient::class))
            ->addArgument(new Reference(TransientManager::class));

        // Per-provider services
        foreach ($config->providers as $providerConfig) {
            $this->registerProvider($builder, $config, $providerConfig);
        }

        // Route Registry
        if (!$builder->hasDefinition(RouteRegistry::class)) {
            $builder->register(RouteRegistry::class)
                ->addArgument(new Reference(Request::class));
        }

        // Controllers (AuthorizeController is created in boot() with resolved entry points)
        $builder->register(OAuthCallbackController::class)
            ->addArgument(new Reference(AuthenticationManagerInterface::class));

        $builder->register(OAuthVerifyController::class)
            ->addArgument(new Reference(AuthenticationManagerInterface::class));

        // Login Form
        $builder->register(OAuthLoginForm::class)
            ->setFactory([self::class, 'createLoginForm'])
            ->addArgument(new Reference(OAuthLoginConfiguration::class))
            ->addArgument(new Reference(AuthenticationSession::class))
            ->addArgument(new Reference(Request::class));
    }

    private function registerProvider(
        ContainerBuilder $builder,
        OAuthLoginConfiguration $config,
        ProviderConfiguration $providerConfig,
    ): void {
        $name = $providerConfig->name;

        // Provider-specific OAuthConfiguration
        $configId = OAuthConfiguration::class . '.' . $name;
        $builder->register($configId, OAuthConfiguration::class)
            ->setFactory([self::class, 'createOAuthConfiguration'])
            ->addArgument($providerConfig)
            ->addArgument($config)
            ->addArgument(new Reference(BlogContextInterface::class));

        // Provider instance
        $providerId = ProviderInterface::class . '.' . $name;
        $builder->register($providerId, ProviderInterface::class)
            ->setFactory([self::class, 'createProvider'])
            ->addArgument($providerConfig)
            ->addArgument(new Reference($configId));

        // User Resolver
        $resolverId = OAuthUserResolverInterface::class . '.' . $name;
        $builder->register($resolverId, OAuthUserResolver::class)
            ->setFactory([self::class, 'createUserResolver'])
            ->addArgument($providerConfig)
            ->addArgument(new Reference(UserRepositoryInterface::class))
            ->addArgument(new Reference(Sanitizer::class))
            ->addArgument(new Reference(EventDispatcherInterface::class));

        // Entry Point
        $entryPointId = OAuthEntryPoint::class . '.' . $name;
        $builder->register($entryPointId, OAuthEntryPoint::class)
            ->setFactory([self::class, 'createEntryPoint'])
            ->addArgument(new Reference($providerId))
            ->addArgument(new Reference($configId))
            ->addArgument(new Reference(OAuthStateStore::class))
            ->addArgument(new Reference(AuthenticationSession::class))
            ->addArgument(new Reference(Request::class));

        // Authenticator (tagged for RegisterAuthenticatorsPass)
        $authenticatorId = OAuthAuthenticator::class . '.' . $name;
        $builder->register($authenticatorId, OAuthAuthenticator::class)
            ->setFactory([self::class, 'createAuthenticator'])
            ->addArgument($providerConfig)
            ->addArgument($config)
            ->addArgument(new Reference($providerId))
            ->addArgument(new Reference($configId))
            ->addArgument(new Reference(OAuthStateStore::class))
            ->addArgument(new Reference(TokenExchanger::class))
            ->addArgument(new Reference($resolverId))
            ->addArgument(new Reference(EventDispatcherInterface::class))
            ->addArgument(new Reference(BlogContextInterface::class))
            ->addArgument(new Reference(UserRepositoryInterface::class))
            ->addArgument(new Reference(IdTokenValidator::class))
            ->addArgument(new Reference(JwksProvider::class))
            ->addArgument(new Reference(HttpClient::class))
            ->addArgument(new Reference(TransientManager::class))
            ->addArgument(new Reference(SiteRepositoryInterface::class))
            ->addTag('security.authenticator');
    }

    /**
     * Identity factory that returns the pre-built configuration.
     */
    public static function returnConfig(OAuthLoginConfiguration $config): OAuthLoginConfiguration
    {
        return $config;
    }

    public static function createOAuthConfiguration(
        ProviderConfiguration $providerConfig,
        OAuthLoginConfiguration $config,
        BlogContextInterface $blogContext,
    ): OAuthConfiguration {
        $blogId = $blogContext->isMultisite() ? $blogContext->getMainSiteId() : null;
        $redirectUri = get_home_url($blogId, $config->getCallbackPath($providerConfig->name));

        $definition = ProviderRegistry::definition($providerConfig->type);
        $scopes = $providerConfig->scopes ?? ($definition !== null ? $definition->defaultScopes : ['openid', 'email', 'profile']);

        return new OAuthConfiguration(
            clientId: $providerConfig->clientId,
            clientSecret: $providerConfig->clientSecret,
            redirectUri: $redirectUri,
            scopes: $scopes,
            discoveryUrl: $providerConfig->discoveryUrl,
        );
    }

    public static function createProvider(
        ProviderConfiguration $providerConfig,
        OAuthConfiguration $oauthConfig,
    ): ProviderInterface {
        $class = ProviderRegistry::providerClass($providerConfig->type);
        if ($class === null) {
            throw new \RuntimeException(\sprintf(
                'Unknown OAuth provider type "%s" for provider "%s".',
                $providerConfig->type,
                $providerConfig->name,
            ));
        }

        // Provider-specific constructor arguments
        return match ($providerConfig->type) {
            'google' => new $class(
                configuration: $oauthConfig,
                hostedDomain: $providerConfig->hostedDomain,
            ),
            'entra-id' => new $class(
                configuration: $oauthConfig,
                tenantId: self::requireField($providerConfig, 'tenantId', 'tenant_id'),
            ),
            'okta', 'auth0', 'onelogin', 'keycloak', 'cognito' => new $class(
                configuration: $oauthConfig,
                domain: self::requireField($providerConfig, 'domain', 'domain'),
            ),
            default => new $class(configuration: $oauthConfig),
        };
    }

    private static function requireField(ProviderConfiguration $config, string $property, string $fieldName): string
    {
        return $config->{$property} ?? throw new \RuntimeException(\sprintf(
            'Provider "%s" requires a "%s" configuration.',
            $config->name,
            $fieldName,
        ));
    }

    public static function createUserResolver(
        ProviderConfiguration $providerConfig,
        UserRepositoryInterface $userRepository,
        Sanitizer $sanitizer,
        EventDispatcherInterface $dispatcher,
    ): OAuthUserResolver {
        return new OAuthUserResolver(
            providerName: $providerConfig->name,
            userRepository: $userRepository,
            sanitizer: $sanitizer,
            autoProvision: $providerConfig->autoProvision,
            defaultRole: $providerConfig->defaultRole,
            roleMapping: $providerConfig->roleMapping,
            roleClaim: $providerConfig->roleClaim,
            dispatcher: $dispatcher,
        );
    }

    public static function createEntryPoint(
        ProviderInterface $provider,
        OAuthConfiguration $oauthConfig,
        OAuthStateStore $stateStore,
        AuthenticationSession $authSession,
        Request $request,
    ): OAuthEntryPoint {
        return new OAuthEntryPoint(
            provider: $provider,
            configuration: $oauthConfig,
            stateStore: $stateStore,
            authSession: $authSession,
            request: $request,
        );
    }

    public static function createAuthenticator(
        ProviderConfiguration $providerConfig,
        OAuthLoginConfiguration $config,
        ProviderInterface $provider,
        OAuthConfiguration $oauthConfig,
        OAuthStateStore $stateStore,
        TokenExchanger $tokenExchanger,
        OAuthUserResolverInterface $userResolver,
        EventDispatcherInterface $dispatcher,
        BlogContextInterface $blogContext,
        UserRepositoryInterface $userRepository,
        IdTokenValidator $idTokenValidator,
        JwksProvider $jwksProvider,
        HttpClient $httpClient,
        TransientManager $transientManager,
        SiteRepositoryInterface $siteRepository,
    ): OAuthAuthenticator {
        $crossSiteRedirector = $blogContext->isMultisite()
            ? new CrossSiteRedirector(
                blogContext: $blogContext,
                siteRepository: $siteRepository,
                transientManager: $transientManager,
                verifyPath: $config->getVerifyPath($providerConfig->name),
            )
            : null;

        return new OAuthAuthenticator(
            provider: $provider,
            configuration: $oauthConfig,
            stateStore: $stateStore,
            tokenExchanger: $tokenExchanger,
            userResolver: $userResolver,
            dispatcher: $dispatcher,
            blogContext: $blogContext,
            userRepository: $userRepository,
            callbackPath: $config->getCallbackPath($providerConfig->name),
            idTokenValidator: $provider->supportsOidc() ? $idTokenValidator : null,
            jwksProvider: $provider->supportsOidc() ? $jwksProvider : null,
            crossSiteRedirector: $crossSiteRedirector,
            httpClient: $httpClient,
            verifyPath: $config->getVerifyPath($providerConfig->name),
        );
    }

    public static function createLoginForm(
        OAuthLoginConfiguration $config,
        AuthenticationSession $authSession,
        Request $request,
    ): OAuthLoginForm {
        return new OAuthLoginForm(
            providers: array_values($config->providers),
            config: $config,
            authSession: $authSession,
            request: $request,
        );
    }
}
