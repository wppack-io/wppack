# wppack/amazon-mailer-plugin

WordPress plugin for SES-based email delivery. Replaces WordPress email sending with Amazon SES, including bounce and complaint handling via SQS.

## Installation

```bash
composer require wppack/amazon-mailer-plugin
```

## Requirements

- PHP 8.2+
- WordPress 6.x
- AWS account with SES

## Usage

### Automatic wp_mail() Integration

Once activated, all `wp_mail()` calls are automatically routed through SES:

```php
// No code changes needed - existing wp_mail() calls use SES
wp_mail('user@example.com', 'Subject', 'Message body');
```

### Mailer Component

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

### WP-CLI

```bash
wp wppack ses verify-identity --email=noreply@example.com
wp wppack ses test-email --to=test@example.com
```

## Configuration

Set environment variables:

```bash
AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-key
AWS_REGION=ap-northeast-1
SES_CONFIGURATION_SET=my-config    # Optional
```

## Documentation

See [full documentation](../../docs/plugins/amazon-mailer-plugin.md) for details.

## License

MIT
