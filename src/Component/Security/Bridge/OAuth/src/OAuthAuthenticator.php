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

namespace WpPack\Component\Security\Bridge\OAuth;

use Psr\EventDispatcher\EventDispatcherInterface;
use WpPack\Component\HttpClient\HttpClient;
use WpPack\Component\HttpFoundation\RedirectResponse;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\HttpFoundation\Response;
use WpPack\Component\Security\Authentication\AuthenticatorInterface;
use WpPack\Component\Security\Authentication\Passport\Badge\UserBadge;
use WpPack\Component\Security\Authentication\Passport\Passport;
use WpPack\Component\Security\Authentication\Passport\SelfValidatingPassport;
use WpPack\Component\Security\Authentication\Token\PostAuthenticationToken;
use WpPack\Component\Security\Authentication\Token\TokenInterface;
use WpPack\Component\Security\Bridge\OAuth\Badge\OAuthTokenBadge;
use WpPack\Component\Security\Bridge\OAuth\Configuration\OAuthConfiguration;
use WpPack\Component\Security\Bridge\OAuth\Event\OAuthResponseReceivedEvent;
use WpPack\Component\Security\Bridge\OAuth\Multisite\CrossSiteRedirector;
use WpPack\Component\Security\Bridge\OAuth\Provider\ProviderInterface;
use WpPack\Component\Security\Bridge\OAuth\State\OAuthStateStore;
use WpPack\Component\Security\Bridge\OAuth\Token\IdTokenValidator;
use WpPack\Component\Security\Bridge\OAuth\Token\JwksProvider;
use WpPack\Component\Security\Bridge\OAuth\Token\TokenExchanger;
use WpPack\Component\Security\Bridge\OAuth\UserResolution\OAuthUserResolverInterface;
use WpPack\Component\Security\Exception\AuthenticationException;
use WpPack\Component\Site\BlogContextInterface;

final class OAuthAuthenticator implements AuthenticatorInterface
{
    public function __construct(
        private readonly ProviderInterface $provider,
        private readonly OAuthConfiguration $configuration,
        private readonly OAuthStateStore $stateStore,
        private readonly TokenExchanger $tokenExchanger,
        private readonly OAuthUserResolverInterface $userResolver,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly BlogContextInterface $blogContext,
        private readonly string $callbackPath = '/oauth/callback',
        private readonly ?IdTokenValidator $idTokenValidator = null,
        private readonly ?JwksProvider $jwksProvider = null,
        private readonly ?CrossSiteRedirector $crossSiteRedirector = null,
        private readonly ?HttpClient $httpClient = null,
        private readonly bool $addUserToBlog = true,
        private readonly string $verifyPath = '/oauth/verify',
    ) {}

    public function supports(Request $request): bool
    {
        // Pattern 1: IdP callback (GET + code + state, exact path match)
        if ($request->isMethod('GET') && $request->query->has('code') && $request->query->has('state')) {
            return $request->getPathInfo() === $this->callbackPath;
        }

        // Pattern 2: Cross-site transfer (POST + _wppack_oauth_token, exact path match)
        if ($request->isMethod('POST') && $request->post->has('_wppack_oauth_token')) {
            return $request->getPathInfo() === $this->verifyPath;
        }

        return false;
    }

    public function authenticate(Request $request): Passport
    {
        // Handle error response from IdP
        if ($request->query->has('error')) {
            $error = $request->query->get('error', '');
            $desc = $request->query->get('error_description', '');

            do_action('wppack_oauth_authentication_error', $error, $desc);

            throw new AuthenticationException('OAuth authentication failed.');
        }

        // Pattern 2: Cross-site token verification
        if ($request->isMethod('POST') && $request->post->has('_wppack_oauth_token')) {
            return $this->handleCrossSiteVerification($request);
        }

        // Pattern 1: Normal OAuth callback
        return $this->handleOAuthCallback($request);
    }

    public function createToken(Passport $passport): TokenInterface
    {
        $user = $passport->getUser();

        $blogId = null;

        if ($this->crossSiteRedirector !== null && $this->blogContext->isMultisite()) {
            $blogId = $this->blogContext->getCurrentBlogId();
        }

        return new PostAuthenticationToken(
            $user,
            $user->roles,
            $blogId,
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        $user = $token->getUser();

        if ($this->addUserToBlog && $this->blogContext->isMultisite()) {
            if (!is_user_member_of_blog($user->ID)) {
                $role = !empty($user->roles) ? $user->roles[0] : 'subscriber';
                add_user_to_blog($this->blogContext->getCurrentBlogId(), $user->ID, $role);
            }
        }

        // For cross-site: use returnTo from POST
        $returnTo = $request->post->get('returnTo');
        $fallback = admin_url();

        if ($returnTo !== null && $this->isSameOrigin($returnTo)) {
            $redirect = wp_validate_redirect($returnTo, $fallback);
        } else {
            $redirect = $fallback;
        }

        return new RedirectResponse($redirect);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        do_action('wppack_oauth_authentication_failed', $exception);

        $loginUrl = wp_login_url();

        return new RedirectResponse($loginUrl . '?oauth_error=1');
    }

    private function handleOAuthCallback(Request $request): Passport
    {
        $code = $request->query->get('code', '');
        $state = $request->query->get('state', '');

        // Retrieve and validate state
        $storedState = $this->stateStore->retrieve($state);

        if ($storedState === null) {
            throw new AuthenticationException('OAuth authentication failed.');
        }

        // Exchange code for tokens
        $tokenSet = $this->tokenExchanger->exchange(
            $this->provider->getTokenEndpoint(),
            $code,
            $this->configuration->getRedirectUri(),
            $this->configuration->getClientId(),
            $this->configuration->getClientSecret(),
            $storedState->getCodeVerifier(),
        );

        // If OIDC with ID token, validate it
        $claims = [];
        $subject = '';

        if ($tokenSet->getIdToken() !== null && $this->provider->supportsOidc()) {
            if ($this->idTokenValidator === null || $this->jwksProvider === null) {
                throw new AuthenticationException('OAuth authentication failed.');
            }

            $jwks = $this->jwksProvider->getKeys($this->provider->getJwksUri());
            $claims = $this->idTokenValidator->validate(
                $tokenSet->getIdToken(),
                $storedState->getNonce(),
                $this->configuration->getClientId(),
                $this->provider->getIssuer(),
                $jwks,
            );
            $subject = (string) ($claims['sub'] ?? '');
        } else {
            // For non-OIDC (e.g., GitHub): fetch userinfo
            $userInfoEndpoint = $this->provider->getUserInfoEndpoint();

            if ($userInfoEndpoint !== null && !str_starts_with($userInfoEndpoint, 'https://')) {
                throw new AuthenticationException('OAuth authentication failed.');
            }

            if ($this->httpClient !== null && $userInfoEndpoint !== null) {
                $response = $this->httpClient->withHeaders([
                    'Authorization' => 'Bearer ' . $tokenSet->getAccessToken(),
                    'Accept' => 'application/json',
                ])->get($userInfoEndpoint);

                /** @var array<string, mixed> $rawClaims */
                $rawClaims = json_decode($response->body(), true) ?? [];
                $claims = $this->provider->normalizeUserInfo($rawClaims);
            }

            $subject = (string) ($claims['sub'] ?? $claims['id'] ?? '');
        }

        if ($subject === '') {
            throw new AuthenticationException('OAuth authentication failed.');
        }

        // Check if cross-site redirect needed
        $returnTo = $storedState->getReturnTo();

        if ($this->crossSiteRedirector !== null && $returnTo !== null) {
            if ($this->crossSiteRedirector->needsRedirect($returnTo)) {
                $user = $this->userResolver->resolveUser($subject, $claims);
                $this->crossSiteRedirector->redirect($returnTo, $user->ID, $returnTo);
            }
        }

        // Dispatch event
        $this->dispatcher->dispatch(new OAuthResponseReceivedEvent($subject, $claims, $tokenSet));

        do_action('wppack_oauth_authenticated', $subject, $claims);

        $userResolver = $this->userResolver;

        return new SelfValidatingPassport(
            new UserBadge(
                $subject,
                static fn(string $identifier): \WP_User => $userResolver->resolveUser($identifier, $claims),
            ),
            [new OAuthTokenBadge($subject, $claims, $tokenSet)],
        );
    }

    private function handleCrossSiteVerification(Request $request): Passport
    {
        if ($this->crossSiteRedirector === null) {
            throw new AuthenticationException('OAuth authentication failed.');
        }

        $token = $request->post->get('_wppack_oauth_token', '');
        $userId = $this->crossSiteRedirector->verifyToken($token);

        if ($userId === null) {
            throw new AuthenticationException('OAuth authentication failed.');
        }

        $user = get_user_by('id', $userId);

        if (!$user instanceof \WP_User) {
            throw new AuthenticationException('OAuth authentication failed.');
        }

        return new SelfValidatingPassport(
            new UserBadge(
                (string) $user->ID,
                static fn(): \WP_User => $user,
            ),
        );
    }

    private function isSameOrigin(string $url): bool
    {
        $host = parse_url($url, \PHP_URL_HOST);

        if ($host === null || $host === false) {
            return false;
        }

        $siteHost = parse_url(home_url(), \PHP_URL_HOST);

        return $siteHost !== null && $host === $siteHost;
    }
}
