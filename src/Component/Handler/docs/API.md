# Handler API Reference

## Handler

```php
use WpPack\Component\Handler\Handler;
use WpPack\Component\Handler\Configuration;
use Psr\Log\LoggerInterface;

$handler = new Handler(?Configuration $config = null, ?LoggerInterface $logger = null);
$handler->handle(Request $request): void;
$handler->addProcessor(ProcessorInterface $processor, int $priority = 100): void;
```

### `handle(Request $request): void`

Processes the request through the full lifecycle:

1. Sets up the environment (Lambda directories, etc.)
2. Prepares the request (cleans server variables)
3. Runs the processor chain
4. If a processor returns a `Response`, sends it and returns
5. If a PHP file is resolved, prepares `$_SERVER` variables
6. If `wppack/kernel` is available, calls `Kernel::create($request)`
7. `require`s the target PHP file

### `addProcessor(ProcessorInterface $processor, int $priority = 100): void`

Inserts a custom processor at the given position in the chain.

## Configuration

```php
$config = new Configuration(array $config = []);
$config->get(string $key, mixed $default = null): mixed;  // Supports dot notation
$config->all(): array;
```

## ProcessorInterface

```php
interface ProcessorInterface
{
    public function process(Request $request, Configuration $config): Request|Response|null;
}
```

Return values:
- `Request` — Modified request, passed to the next processor
- `Response` — Sent immediately, stops the chain
- `null` — Continue to the next processor with the current request

## Environment

```php
$env = new Environment(Configuration $config);
$env->setup(): void;      // Creates Lambda directories if needed
$env->isLambda(): bool;
$env->getInfo(): array;   // ['platform' => 'standard'|'lambda', ...]
```

## PathValidator

```php
$validator = new PathValidator(string $webRoot, bool $checkSymlinks = true);
$validator->validate(string $path): string;              // Throws SecurityException
$validator->validateFilePath(string $filePath): string;  // Throws SecurityException
$validator->isHiddenPath(string $path): bool;
```

## Exceptions

All exceptions implement `ExceptionInterface`:

- `HandlerException` — Base exception (`RuntimeException`)
- `SecurityException` — Security violations (HTTP 403)
- `FileNotFoundException` — Missing files (HTTP 404)
