# HttpFoundation Component

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=http_foundation)](https://codecov.io/github/wppack-io/wppack)

An object-oriented layer for HTTP request handling in WordPress. Provides type-safe access to superglobals (`$_GET`, `$_POST`, `$_FILES`, etc.) and base response classes used across all WpPack components.

## Installation

```bash
composer require wppack/http-foundation
```

## Usage

### Request

```php
use WpPack\Component\HttpFoundation\Request;

$request = Request::createFromGlobals();

// Type-safe parameter access
$page = $request->query->getInt('page', 1);
$name = $request->post->getString('name');
$active = $request->query->getBoolean('active', true);

// Request information
$method = $request->getMethod();
$isAjax = $request->isAjax();
$isSecure = $request->isSecure();
$clientIp = $request->getClientIp();

// JSON body
if ($request->isJson()) {
    $data = $request->toArray();
}
```

### Response

```php
use WpPack\Component\HttpFoundation\Response;
use WpPack\Component\HttpFoundation\JsonResponse;
use WpPack\Component\HttpFoundation\RedirectResponse;
use WpPack\Component\HttpFoundation\BinaryFileResponse;

$response = new Response('Hello', 200, ['X-Custom' => 'value']);
$json = new JsonResponse(['key' => 'value'], 200);
$redirect = new RedirectResponse('/new-url', 302);
$file = new BinaryFileResponse('/path/to/file.pdf');
```

### File

```php
use WpPack\Component\HttpFoundation\File\File;

$file = new File('/path/to/document.pdf');
$mimeType = $file->getMimeType();     // Detected from disk
$extension = $file->guessExtension(); // 'pdf'
$moved = $file->move('/new/directory', 'renamed.pdf');
```

### File Uploads

```php
$file = $request->files->get('upload');

if ($file !== null && $file->isValid()) {
    $mimeType = $file->getMimeType();           // Detected from disk
    $clientMime = $file->getClientMimeType();   // Client-provided MIME
    $name = $file->getClientOriginalName();     // Original filename
    $result = $file->wpHandleUpload(['test_form' => false]);
}
```

### HTTP Exceptions

```php
use WpPack\Component\HttpFoundation\Exception\NotFoundException;
use WpPack\Component\HttpFoundation\Exception\ForbiddenException;

throw new NotFoundException('Resource not found.');
throw new ForbiddenException('Access denied.');
```

## Kernel Integration

When using the Kernel component, `Request` is automatically registered as a synthetic service in the DI container during `Kernel::boot()`.

## ArgumentResolver

`ArgumentResolver` resolves method parameters from a chain of `ValueResolverInterface` implementations.

```php
use WpPack\Component\HttpFoundation\ArgumentResolver;
use WpPack\Component\HttpFoundation\RequestValueResolver;

$argumentResolver = new ArgumentResolver([
    new RequestValueResolver($request),
]);

// Create a resolver closure for a target method
$resolver = $argumentResolver->createResolver($target, '__invoke');
```

Built-in resolvers:
- `RequestValueResolver` — resolves `Request` type-hinted parameters

Other components provide additional resolvers:
- `CurrentUserValueResolver` (`wppack/security`) — resolves `#[CurrentUser]` parameters
- `ContainerValueResolver` (`wppack/dependency-injection`) — resolves parameters from the DI container

## Resources

- [Documentation (Japanese)](../../../docs/components/http-foundation/README.md)
