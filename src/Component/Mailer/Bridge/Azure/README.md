# Azure Mailer

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=azure_mailer)](https://codecov.io/github/wppack-io/wppack)

Azure Communication Services Email transport implementation for WpPack Mailer.

## Installation

```bash
composer require wppack/azure-mailer
```

## DSN Configuration

```php
// wp-config.php
define('MAILER_DSN', 'azure://my-resource.communication.azure.com:ACCESS_KEY@default');
```

| DSN | Transport | Method |
|-----|-----------|--------|
| `azure://` | AzureTransport | REST API delivery (recommended) |
| `azure+https://` | AzureTransport | Alias for `azure://` |
| `azure+api://` | AzureApiTransport | Structured API delivery |

No external SDK required. Azure REST API calls are made via `wppack/http-client`.

## Dependencies

- `wppack/mailer` ^1.0
- `wppack/http-client` ^1.0

## Documentation

For details, see [docs/components/mailer/azure-mailer.md](../../../docs/components/mailer/azure-mailer.md).

## Resources

- [Issues](https://github.com/wppack-io/wppack/issues)
- [Pull Requests](https://github.com/wppack-io/wppack/pulls)

Developed in the main repository [wppack-io/wppack](https://github.com/wppack-io/wppack).
