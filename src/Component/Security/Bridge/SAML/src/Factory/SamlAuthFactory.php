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

namespace WPPack\Component\Security\Bridge\SAML\Factory;

use LightSaml\Binding\BindingFactory;
use LightSaml\Credential\X509Certificate;
use LightSaml\Credential\X509Credential;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use WPPack\Component\HttpFoundation\Request;
use WPPack\Component\Security\Bridge\SAML\Configuration\SamlConfiguration;

class SamlAuthFactory
{
    public function __construct(
        private readonly SamlConfiguration $configuration,
    ) {}

    public function createBindingFactory(): BindingFactory
    {
        return new BindingFactory();
    }

    public function createCredential(): X509Credential
    {
        $cert = new X509Certificate();
        $cert->setData($this->configuration->getIdpX509Cert());

        return new X509Credential($cert);
    }

    public function getConfiguration(): SamlConfiguration
    {
        return $this->configuration;
    }

    /**
     * Convert a WPPack Request to a Symfony HttpFoundation Request.
     *
     * LightSAML bindings require Symfony Request objects. This helper
     * bridges the two request abstractions.
     */
    public static function toSymfonyRequest(Request $request): SymfonyRequest
    {
        return new SymfonyRequest(
            query: $request->query->all(),
            request: $request->post->all(),
            attributes: [],
            cookies: $request->cookies->all(),
            files: [],
            server: $request->server->all(),
            content: $request->getContent(),
        );
    }
}
