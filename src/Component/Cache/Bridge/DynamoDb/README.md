# WPPack DynamoDB Cache

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=dynamodb_cache)](https://codecov.io/github/wppack-io/wppack)

AWS DynamoDB cache adapter for the WPPack Cache component.

## Installation

```bash
composer require wppack/dynamodb-cache
```

## Requirements

- PHP 8.2+
- `wppack/cache` ^0.1
- `async-aws/dynamo-db` ^3.0
- AWS DynamoDB table with the required schema

## DynamoDB Table Schema

Create a table with the following schema:

| Attribute | Type | Key |
|-----------|------|-----|
| `p` | String (S) | Partition Key (HASH) |
| `k` | String (S) | Sort Key (RANGE) |
| `v` | String (S) | Cache value |
| `t` | Number (N) | TTL timestamp (enable DynamoDB TTL on this attribute) |

### AWS CLI

```bash
aws dynamodb create-table \
    --table-name cache \
    --key-schema \
        AttributeName=p,KeyType=HASH \
        AttributeName=k,KeyType=RANGE \
    --attribute-definitions \
        AttributeName=p,AttributeType=S \
        AttributeName=k,AttributeType=S \
    --billing-mode PAY_PER_REQUEST

aws dynamodb update-time-to-live \
    --table-name cache \
    --time-to-live-specification Enabled=true,AttributeName=t
```

## DSN Format

```php
// Basic
'dynamodb://ap-northeast-1/my_cache_table'

// DynamoDB Local (development/testing)
'dynamodb://us-east-1/my_cache_table?endpoint=http://localhost:8000'

// Table name omitted (defaults to "cache")
'dynamodb://ap-northeast-1'

// Custom key prefix
'dynamodb://ap-northeast-1/my_cache_table?key_prefix=mysite:'
```

## Configuration (wp-config.php)

```php
define('WPPACK_CACHE_DSN', 'dynamodb://ap-northeast-1/cache');
define('WPPACK_CACHE_PREFIX', 'wp:');
```

## Local Development with DynamoDB Local

```yaml
# docker-compose.yml
services:
  dynamodb:
    image: amazon/dynamodb-local:latest
    ports:
      - '8000:8000'
    tmpfs:
      - /home/dynamodblocal/data
```

```php
define('WPPACK_CACHE_DSN', 'dynamodb://us-east-1/cache?endpoint=http://localhost:8000');
```
