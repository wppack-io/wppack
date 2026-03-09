<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Bridge\OAuth;

use WpPack\Component\Security\Bridge\OAuth\Configuration\OAuthConfiguration;
use WpPack\Component\Security\Bridge\OAuth\Pkce\PkceGenerator;
use WpPack\Component\Security\Bridge\OAuth\Provider\ProviderInterface;
use WpPack\Component\Security\Bridge\OAuth\State\OAuthStateStore;
use WpPack\Component\Security\Bridge\OAuth\State\StoredState;

final class OAuthEntryPoint
{
    public function __construct(
        private readonly ProviderInterface $provider,
        private readonly OAuthConfiguration $configuration,
        private readonly OAuthStateStore $stateStore,
    ) {}

    /**
     * Start OAuth authorization flow.
     *
     * @return never
     */
    public function start(?string $returnTo = null): void
    {
        $url = $this->getLoginUrl($returnTo);

        if (function_exists('wp_redirect')) {
            wp_redirect($url);
        }

        exit;
    }

    public function getLoginUrl(?string $returnTo = null): string
    {
        $state = bin2hex(random_bytes(32));
        $nonce = bin2hex(random_bytes(32));

        $codeVerifier = null;
        $codeChallenge = null;

        if ($this->configuration->isPkceEnabled()) {
            $pkce = PkceGenerator::generate();
            $codeVerifier = $pkce['code_verifier'];
            $codeChallenge = $pkce['code_challenge'];
        }

        $storedState = StoredState::create($nonce, $codeVerifier, $returnTo);
        $this->stateStore->store($state, $storedState);

        return $this->provider->getAuthorizationUrl($state, $nonce, $codeChallenge);
    }
}
