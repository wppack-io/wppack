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

namespace WPPack\Component\Security\Bridge\SAML;

use LightSaml\Binding\HttpRedirectBinding;
use LightSaml\Context\Profile\MessageContext;
use LightSaml\Helper;
use LightSaml\Model\Assertion\Issuer;
use LightSaml\Model\Assertion\NameID;
use LightSaml\Model\Protocol\LogoutRequest;
use LightSaml\SamlConstants;
use WPPack\Component\HttpFoundation\Request;
use WPPack\Component\Security\AuthenticationSession;
use WPPack\Component\Security\Bridge\SAML\Factory\SamlAuthFactory;

final class SamlLogoutHandler
{
    public function __construct(
        private readonly SamlAuthFactory $authFactory,
        private readonly AuthenticationSession $authSession,
        private readonly ?string $redirectAfterLogout = null,
    ) {}

    /**
     * Build and send a SAML LogoutRequest to the IdP.
     *
     * @return never
     */
    public function initiateLogout(?string $nameId, ?string $sessionIndex, ?string $returnTo = null): void
    {
        $config = $this->authFactory->getConfiguration();

        $logoutRequest = new LogoutRequest();
        $logoutRequest->setID(Helper::generateID());
        $logoutRequest->setIssueInstant(new \DateTime());
        $logoutRequest->setDestination($config->getIdpSloUrl());
        $logoutRequest->setIssuer(new Issuer($config->getSpEntityId()));

        if ($nameId !== null) {
            $logoutRequest->setNameID(new NameID($nameId, SamlConstants::NAME_ID_FORMAT_UNSPECIFIED));
        }

        if ($sessionIndex !== null) {
            $logoutRequest->setSessionIndex($sessionIndex);
        }

        $relayState = $returnTo ?? $this->redirectAfterLogout;
        if ($relayState !== null) {
            $logoutRequest->setRelayState($relayState);
        }

        $messageContext = new MessageContext();
        $messageContext->setMessage($logoutRequest);

        $binding = new HttpRedirectBinding();
        /** @var \Symfony\Component\HttpFoundation\RedirectResponse $symfonyResponse */
        $symfonyResponse = $binding->send($messageContext);

        // @codeCoverageIgnoreStart
        header('Location: ' . $symfonyResponse->getTargetUrl());
        exit;
        // @codeCoverageIgnoreEnd
    }

    /**
     * Handle an IdP-initiated LogoutRequest received via HTTP-Redirect binding.
     */
    public function handleIdpLogoutRequest(Request $request): void
    {
        $symfonyRequest = SamlAuthFactory::toSymfonyRequest($request);
        $bindingFactory = $this->authFactory->createBindingFactory();

        $binding = $bindingFactory->getBindingByRequest($symfonyRequest);
        $messageContext = new MessageContext();
        $binding->receive($symfonyRequest, $messageContext);

        // The received message should be a LogoutRequest — process it
        // by logging out the local WordPress session.
        $this->authSession->logout();
    }

    public function isLogoutRequest(Request $request): bool
    {
        return $request->query->has('SAMLRequest');
    }

    public function isLogoutResponse(Request $request): bool
    {
        return $request->query->has('SAMLResponse');
    }
}
