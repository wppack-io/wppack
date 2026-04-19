# SamlSecurity

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=saml_security)](https://codecov.io/github/wppack-io/wppack)

SAML 2.0 Service Provider (SP) authentication bridge for WPPack Security. Wraps `onelogin/php-saml` to integrate external IdP (Okta, Azure AD, Google Workspace, etc.) SSO authentication with the WPPack Security component.

## Installation

```bash
composer require wppack/saml-security
```

## Configuration

```php
use WPPack\Component\Security\Bridge\SAML\Configuration\IdpSettings;
use WPPack\Component\Security\Bridge\SAML\Configuration\SpSettings;
use WPPack\Component\Security\Bridge\SAML\Configuration\SamlConfiguration;

$idpSettings = new IdpSettings(
    entityId: 'https://idp.example.com/metadata',
    ssoUrl: 'https://idp.example.com/sso',
    sloUrl: 'https://idp.example.com/slo',
    x509Cert: '-----BEGIN CERTIFICATE-----...-----END CERTIFICATE-----',
);

$spSettings = new SpSettings(
    entityId: 'https://example.com',
    acsUrl: 'https://example.com/saml/acs',
    sloUrl: 'https://example.com/saml/slo',
);

$configuration = new SamlConfiguration($idpSettings, $spSettings);
```

## Usage

### SP-Initiated SSO

```php
use WPPack\Component\Security\Bridge\SAML\SamlEntryPoint;
use WPPack\Component\Security\Bridge\SAML\Factory\SamlAuthFactory;

$factory = new SamlAuthFactory($configuration);
$entryPoint = new SamlEntryPoint($factory);

// Redirect to IdP
$entryPoint->start(returnTo: admin_url());

// Get login URL only
$loginUrl = $entryPoint->getLoginUrl();
```

### Assertion Consumer Service (ACS)

```php
use WPPack\Component\Security\Bridge\SAML\SamlAuthenticator;

$authenticator = new SamlAuthenticator(
    authFactory: $factory,
    userResolver: $userResolver,
    dispatcher: $eventDispatcher,
    acsPath: '/saml/acs',
);
```

### SP Metadata

```php
use WPPack\Component\Security\Bridge\SAML\Configuration\SpMetadataExporter;
use WPPack\Component\Security\Bridge\SAML\SamlMetadataController;

$exporter = new SpMetadataExporter($configuration);

// As HTTP response (Content-Type: application/xml)
$controller = new SamlMetadataController($exporter);
$response = $controller();

// As XML string
$xml = $exporter->toXml();

// Export to file
$exporter->exportToFile('/path/to/sp-metadata.xml');
```

### IdP Metadata Import

```php
use WPPack\Component\Security\Bridge\SAML\Configuration\IdpMetadataParser;

$parser = new IdpMetadataParser();

// From XML string
$idpSettings = $parser->parseXml($xmlString);

// From file
$idpSettings = $parser->parseFile('/path/to/idp-metadata.xml');

// From remote URL
$idpSettings = $parser->parseRemoteUrl('https://idp.example.com/metadata');
```

### Single Logout

```php
use WPPack\Component\Security\Bridge\SAML\SamlLogoutHandler;

$logoutHandler = new SamlLogoutHandler($factory, redirectAfterLogout: home_url());
$logoutHandler->initiateLogout($nameId, $sessionIndex);
```

## User Resolution (JIT Provisioning)

```php
use WPPack\Component\Security\Bridge\SAML\UserResolution\SamlUserResolver;

$userResolver = new SamlUserResolver(
    autoProvision: true,
    defaultRole: 'subscriber',
    emailAttribute: 'email',
    firstNameAttribute: 'firstName',
    lastNameAttribute: 'lastName',
    roleMapping: [
        'Admin' => 'administrator',
        'Editor' => 'editor',
    ],
    roleAttribute: 'groups',
);
```

## Multisite Support

### Cross-Site SSO

```php
use WPPack\Component\Security\Bridge\SAML\Multisite\CrossSiteRedirector;

$redirector = new CrossSiteRedirector(
    allowedHosts: ['site-a.example.com', 'site-b.example.com'],
);

$authenticator = new SamlAuthenticator(
    authFactory: $factory,
    userResolver: $userResolver,
    dispatcher: $eventDispatcher,
    crossSiteRedirector: $redirector,
);
```

## Dependencies

- `wppack/security` ^1.0
- `onelogin/php-saml` ^4.0

## Documentation

See [docs/components/security/saml-security.md](../../../../docs/components/security/saml-security.md) for full documentation.

## Resources

- [Issues](https://github.com/wppack-io/wppack/issues)
- [Pull Requests](https://github.com/wppack-io/wppack/pulls)

Developed in the main repository [wppack-io/wppack](https://github.com/wppack-io/wppack).
