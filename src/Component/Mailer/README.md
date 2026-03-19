# WpPack Mailer

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=mailer)](https://codecov.io/github/wppack-io/wppack)

Email abstraction with pluggable transports for WordPress.

## Installation

```bash
composer require wppack/mailer
```

## Usage

```php
use WpPack\Component\Mailer\Mailer;
use WpPack\Component\Mailer\Email;

// DSN string — transport is resolved automatically
$mailer = new Mailer('smtp://user:pass@smtp.example.com:587?encryption=tls');
$mailer->boot(); // Register WordPress hooks

$email = (new Email())
    ->from('admin@example.com')
    ->to('user@example.com')
    ->subject('Welcome!')
    ->html('<h1>Thanks for joining!</h1>');

$mailer->send($email);
```

## TransportInterface

All transports implement `TransportInterface`, which provides the transport name and
sends email through the `PhpMailer` instance:

```php
use WpPack\Component\Mailer\Transport\TransportInterface;
use WpPack\Component\Mailer\PhpMailer;

interface TransportInterface
{
    public function getName(): string;
    public function send(PhpMailer $phpMailer): void;
}
```

Built-in transports:

- `NativeTransport` — PHP `mail()` function (`native://default`)
- `SmtpTransport` — SMTP delivery (`smtp://user:pass@host:port` or `smtps://user:pass@host`)
- `NullTransport` — No-op for testing (`null://default`)

For Amazon SES, install `wppack/amazon-mailer`.

## wp_mail() Integration

Call `boot()` to redirect all `wp_mail()` calls through the configured transport:

```php
$mailer = new Mailer(MAILER_DSN);
$mailer->boot();
```

## Documentation

See [docs/components/mailer/](../../../docs/components/mailer/) for full documentation.

## License

MIT
