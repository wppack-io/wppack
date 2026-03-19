# WpPack Messenger

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=messenger)](https://codecov.io/github/wppack-io/wppack)

Async message bus for WordPress with SQS/Lambda support.

## Installation

```bash
composer require wppack/messenger
```

## Usage

### Define Messages

```php
use WpPack\Component\Messenger\Attribute\AsMessage;

#[AsMessage]
final readonly class SendEmailMessage
{
    public function __construct(
        public int $userId,
        public string $subject,
        public string $body,
    ) {}
}
```

### Define Handlers

```php
use WpPack\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SendEmailMessageHandler
{
    public function __invoke(SendEmailMessage $message): void
    {
        // Handle the message
    }
}
```

### Dispatch Messages

```php
$messageBus->dispatch(new SendEmailMessage(
    userId: 123,
    subject: 'Welcome!',
    body: 'Thanks for joining!',
));
```

### Envelope & Stamps

```php
use WpPack\Component\Messenger\Envelope;
use WpPack\Component\Messenger\Stamp\DelayStamp;

$messageBus->dispatch(new Envelope(
    new SendEmailMessage(userId: 123, subject: 'Reminder', body: '...'),
    stamps: [new DelayStamp(seconds: 300)],
));
```

## Architecture

Messages are dispatched to SQS, then consumed by Lambda (Bref) workers that bootstrap WordPress and execute the appropriate handler.

## Testing

```php
use WpPack\Component\Messenger\Test\TestMessageBus;

$testBus = new TestMessageBus();
$testBus->dispatch(new SendEmailMessage(...));
$testBus->assertDispatched(SendEmailMessage::class);
```

## Documentation

See [docs/components/messenger.md](../../docs/components/messenger.md) for full documentation.

## License

MIT
