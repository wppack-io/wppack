# ElastiCache Auth

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=elasticache_auth)](https://codecov.io/github/wppack-io/wppack)

AWS ElastiCache IAM authentication bridge for WPPack Cache.

## Installation

```bash
composer require wppack/elasticache-auth
```

## Requirements

- PHP 8.2+
- `async-aws/core` ^1.28
- AWS ElastiCache Redis 7.0+ or Valkey 7.2+ with IAM authentication enabled
- TLS connection required (`rediss://` or `valkeys://` scheme)

## Usage

### Option 1: IAM Auth Shortcut (Recommended)

The simplest way. Add IAM parameters to your cache options:

```php
// wp-config.php
define('WPPACK_CACHE_DSN', 'rediss://my-cluster.xxxxx.apne1.cache.amazonaws.com:6379');
define('WPPACK_CACHE_OPTIONS', [
    'iam_auth' => true,
    'iam_region' => 'ap-northeast-1',
    'iam_user_id' => 'my-iam-user',
]);
```

### Option 2: DSN Query Parameters

```php
define('WPPACK_CACHE_DSN', 'rediss://my-cluster.xxxxx.apne1.cache.amazonaws.com:6379?iam_auth=1&iam_region=ap-northeast-1&iam_user_id=my-iam-user');
```

### Option 3: Manual credential_provider

For advanced use cases where you need custom credential resolution:

```php
use WPPack\Component\Cache\Bridge\ElastiCacheAuth\ElastiCacheIamTokenGenerator;

$generator = new ElastiCacheIamTokenGenerator(
    region: 'ap-northeast-1',
    userId: 'my-iam-user',
);

define('WPPACK_CACHE_DSN', 'rediss://my-cluster.xxxxx.apne1.cache.amazonaws.com:6379');
define('WPPACK_CACHE_OPTIONS', [
    'credential_provider' => $generator->createProvider(
        'my-cluster.xxxxx.apne1.cache.amazonaws.com:6379'
    ),
]);
```

## How It Works

1. `ElastiCacheIamTokenGenerator` creates a SigV4 presigned URL for the `connect` action
2. The presigned URL (without the `http://` prefix) is used as the AUTH password
3. Tokens are valid for 15 minutes
4. A new token is generated on each connection (per PHP-FPM request)

## AWS Credentials

The generator uses `async-aws/core`'s default credential chain:

1. Environment variables (`AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`)
2. Web identity token (EKS IRSA)
3. SSO credentials
4. Shared credentials file (`~/.aws/credentials`)
5. ECS container credentials
6. EC2 instance metadata (instance profile)

## License

MIT
