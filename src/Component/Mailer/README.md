# WpPack Mailer

Email abstraction with pluggable transports for WordPress.

## Installation

```bash
composer require wppack/mailer
```

## Usage

```php
use WpPack\Component\Mailer\Mailer;
use WpPack\Component\Mailer\Email;
use WpPack\Component\Mailer\Transport\PhpMailerTransport;

$mailer = new Mailer(new PhpMailerTransport());

$email = (new Email())
    ->from('admin@example.com')
    ->to('user@example.com')
    ->subject('Welcome!')
    ->html('<h1>Thanks for joining!</h1>');

$mailer->send($email);
```

## TransportInterface

All mail delivery goes through `TransportInterface`, making it easy to swap transports:

```php
use WpPack\Component\Mailer\Transport\TransportInterface;
use WpPack\Component\Mailer\Email;
use WpPack\Component\Mailer\SentMessage;

interface TransportInterface
{
    public function send(Email $email): SentMessage;
}
```

Built-in transports:

- `PhpMailerTransport` - WordPress PHPMailer wrapper (default)
- `SmtpTransport` - Direct SMTP delivery
- `NullTransport` - For testing (no-op)

For Amazon SES, use `wppack/amazon-mailer-plugin`.

## wp_mail() Integration

Redirect all `wp_mail()` calls through WpPack Mailer:

```php
use WpPack\Component\Mailer\WordPress\WpMailIntegration;

WpMailIntegration::override($mailer);
```

## Documentation

See [docs/components/mailer.md](../../docs/components/mailer.md) for full documentation.

## License

MIT
