# WpPack HttpClient

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=http_client)](https://codecov.io/github/wppack-io/wppack)

A PSR-18 compliant wrapper for the WordPress HTTP API. Provides type-safe HTTP requests with a Fluent API.

## Installation

```bash
composer require wppack/http-client
```

## Usage

### Basic Request

```php
use WpPack\Component\HttpClient\HttpClient;

$http = new HttpClient();

$response = $http->get('https://api.example.com/users');

if ($response->successful()) {
    $data = $response->json();
}
```

### Fluent API

```php
$response = $http
    ->withHeaders(['Authorization' => 'Bearer ' . $token])
    ->timeout(30)
    ->asJson()
    ->post('https://api.example.com/users', [
        'json' => ['name' => 'John', 'email' => 'john@example.com'],
    ]);

$response->throw(); // Throws on 4xx/5xx
```

### Base URI

```php
$client = $http->baseUri('https://api.example.com/v1');

$response = $client->get('/users');     // https://api.example.com/v1/users
$response = $client->get('/posts');     // https://api.example.com/v1/posts
```

### Response Helpers

```php
$response->status();        // 200
$response->headers();       // ['Content-Type' => ['application/json']]
$response->header('Content-Type'); // 'application/json'
$response->body();          // Response body string
$response->json();          // JSON decoded result
$response->successful();    // 200-299
$response->failed();        // 400-599
$response->clientError();   // 400-499
$response->serverError();   // 500-599
$response->throw();         // Throws exception on error
```

### PSR-18 Compatible

```php
use Psr\Http\Client\ClientInterface;

function fetchData(ClientInterface $client): array
{
    $factory = new \WpPack\Component\HttpClient\RequestFactory();
    $request = $factory->createRequest('GET', 'https://api.example.com/data');

    $response = $client->sendRequest($request);

    return json_decode((string) $response->getBody(), true);
}
```

### Named Hook Attributes

```php
use WpPack\Component\HttpClient\Attribute\HttpRequestArgsFilter;
use WpPack\Component\HttpClient\Attribute\HttpResponseFilter;

final class HttpHooks
{
    #[HttpRequestArgsFilter]
    public function addCustomHeader(array $args, string $url): array
    {
        $args['headers']['X-Custom'] = 'value';
        return $args;
    }

    #[HttpResponseFilter(priority: 20)]
    public function logResponse(array $response, array $args, string $url): array
    {
        // Response logging
        return $response;
    }
}
```

## Error Handling

```php
use WpPack\Component\HttpClient\Exception\ConnectionException;
use WpPack\Component\HttpClient\Exception\RequestException;

try {
    $response = $http->get('https://api.example.com/data');
    $response->throw();
} catch (ConnectionException $e) {
    // Network error (WP_Error)
} catch (RequestException $e) {
    // HTTP error (4xx/5xx)
    $status = $e->response->status();
}
```

## Documentation

For details, see [docs/components/http-client/](../../docs/components/http-client/).

## Resources

- [Issues](https://github.com/wppack-io/wppack/issues)
- [Pull Requests](https://github.com/wppack-io/wppack/pulls)

Developed in the main repository [wppack-io/wppack](https://github.com/wppack-io/wppack).

## License

MIT
