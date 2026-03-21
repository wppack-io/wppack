# wppack/amazon-mailer-plugin

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=amazon_mailer_plugin)](https://codecov.io/github/wppack-io/wppack)

WordPress plugin for SES-based email delivery. Replaces WordPress email sending with Amazon SES, including bounce and complaint handling via SQS.

## Installation

```bash
composer require wppack/amazon-mailer-plugin
```

## Requirements

- PHP 8.2+
- WordPress 6.x
- AWS account with SES

## Architecture

AmazonMailerPlugin implements `PluginInterface` and bootstraps via `Kernel::registerPlugin()`:

1. **Bootstrap** (`amazon-mailer-plugin.php`) registers the plugin with the Kernel
2. **ServiceProvider** registers Mailer, Transport, and Handler services in the DI container
3. **`Mailer::boot()`** registers the `wp_mail` filter, replacing the global `$phpmailer` with an SES-backed transport
4. **Handlers** process bounce/complaint notifications from SES via SNS → SQS → Messenger

## Configuration

Set `MAILER_DSN` in `wp-config.php` or as an environment variable:

```php
// wp-config.php
define('MAILER_DSN', 'ses+api://ACCESS_KEY:SECRET_KEY@default?region=ap-northeast-1');
```

For IAM role authentication (recommended on AWS infrastructure):

```php
define('MAILER_DSN', 'ses+api://default?region=ap-northeast-1');
```

### Supported DSN Schemes

| Scheme | Transport | Description |
|--------|-----------|-------------|
| `ses`, `ses+api` | SesApiTransport | SES API (SendEmail) |
| `ses+https` | SesHttpTransport | SES v2 API (SendRawEmail) |
| `ses+smtp`, `ses+smtps` | SesSmtpTransport | SES SMTP |

## Usage

### Automatic wp_mail() Integration

Once activated, all `wp_mail()` calls are automatically routed through SES:

```php
// No code changes needed - existing wp_mail() calls use SES
wp_mail('user@example.com', 'Subject', 'Message body');
```

### Direct Mailer API

```php
use WpPack\Component\Mailer\Mailer;
use WpPack\Component\Mailer\Email;

$email = (new Email())
    ->from('noreply@example.com')
    ->to('user@example.com')
    ->subject('Welcome!')
    ->html('<h1>Welcome to our site</h1>');

$mailer->send($email);
```

### Bounce/Complaint Handling

SES bounce and complaint notifications are processed via SNS → SQS → WpPack Messenger:

- **Permanent bounces** are logged and added to the suppression list (`wp_options`)
- **Transient bounces** are logged only
- **Complaints** are logged and added to the suppression list

## Documentation

See [full documentation](../../docs/plugins/amazon-mailer-plugin.md) for details.

## License

MIT
