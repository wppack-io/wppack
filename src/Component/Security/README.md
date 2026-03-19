# WpPack Security

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=security)](https://codecov.io/github/wppack-io/wppack)

A component that provides a pluggable authentication and authorization framework on WordPress. Supports request-based authentication via the Authenticator pattern, value-object representation of authentication requirements through Passport/Badge, and Voter-based authorization checks.

## Installation

```bash
composer require wppack/security
```

## Usage

### Authentication

Authenticate requests using the Authenticator pattern:

```php
use WpPack\Component\Security\Authentication\AuthenticatorInterface;
use WpPack\Component\Security\Authentication\Passport\Passport;
use WpPack\Component\Security\Authentication\Passport\Badge\UserBadge;
use WpPack\Component\Security\Authentication\Passport\Badge\CredentialsBadge;

final class MyAuthenticator implements AuthenticatorInterface
{
    public function supports(Request $request): bool
    {
        return $request->isMethod('POST')
            && $request->getPathInfo() === '/my-login';
    }

    public function authenticate(Request $request): Passport
    {
        return new Passport(
            new UserBadge($request->post->getString('email')),
            new CredentialsBadge($request->post->getString('password')),
        );
    }
    // ...
}
```

### Authorization

Check permissions using the Voter pattern:

```php
use WpPack\Component\Security\Security;

$security->denyAccessUnlessGranted('edit_posts');

if ($security->isGranted('ROLE_ADMINISTRATOR')) {
    // Administrators only
}
```

### Named Hook Attributes

```php
use WpPack\Component\Hook\Attribute\Security\Action\WpLoginAction;
use WpPack\Component\Hook\Attribute\Security\Filter\AuthenticateFilter;

class SecuritySubscriber
{
    #[AuthenticateFilter(priority: 5)]
    public function onAuthenticate($user, string $username, string $password)
    {
        // Custom authentication logic
        return $user;
    }

    #[WpLoginAction]
    public function onLogin(string $userLogin, \WP_User $user): void
    {
        // Post-login processing
    }
}
```

### Multisite

Supports Super Admin checks and blog context:

```php
// Super Admin check
if ($security->isGranted('ROLE_SUPER_ADMIN')) {
    // Network administrators only
}

// Get the blog ID at the time of authentication from the token
$blogId = $security->getToken()->getBlogId();
```

## Documentation

For details, see [docs/components/security.md](../../docs/components/security.md).

## Resources

- [Issues](https://github.com/wppack-io/wppack/issues)
- [Pull Requests](https://github.com/wppack-io/wppack/pulls)

Development takes place in the main repository [wppack-io/wppack](https://github.com/wppack-io/wppack).

## License

MIT
