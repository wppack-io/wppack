# SQS Messenger

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=sqs_messenger)](https://codecov.io/github/wppack-io/wppack)

Amazon SQS transport for WPPack Messenger.

## Installation

```bash
composer require wppack/sqs-messenger
```

## Usage

### Sending messages to SQS

```php
use AsyncAws\Sqs\SqsClient;
use WPPack\Component\Messenger\Bridge\Sqs\Transport\SqsTransport;

$transport = new SqsTransport(
    sqsClient: new SqsClient(['region' => 'ap-northeast-1']),
    serializer: $serializer,
    queueUrl: 'https://sqs.ap-northeast-1.amazonaws.com/123456789/my-queue',
);

// Use with SendMessageMiddleware
$middleware = new SendMessageMiddleware(['sqs' => $transport]);
```

### Processing SQS events in Lambda

```php
use WPPack\Component\Messenger\Bridge\Sqs\Handler\SqsEventHandler;

$handler = new SqsEventHandler(
    wordpressPath: '/var/task/wordpress',
    messageBus: $messageBus,
    serializer: $serializer,
);

// Lambda entry point
return $handler($event);
```

The handler supports SQS partial batch failure reporting, returning failed message IDs so that only failed messages are retried.

## Dependencies

- `wppack/messenger` ^1.0
- `async-aws/sqs` ^2.0

## Documentation

For details, see [docs/components/messenger.md](../../../../../docs/components/messenger.md).

## Resources

- [Issues](https://github.com/wppack-io/wppack/issues)
- [Pull Requests](https://github.com/wppack-io/wppack/pulls)

Developed in the main repository [wppack-io/wppack](https://github.com/wppack-io/wppack).
