<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Authentication;

/**
 * Marker interface for authenticators that work via the determine_current_user filter.
 *
 * Stateless authenticators run on every request and are used for token-based
 * authentication (cookies, API keys, application passwords).
 */
interface StatelessAuthenticatorInterface extends AuthenticatorInterface {}
