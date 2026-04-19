# Azure Storage

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=azure_storage)](https://codecov.io/github/wppack-io/wppack)

Azure Blob Storage adapter for [WPPack Storage](../../README.md).

## Installation

```bash
composer require wppack/azure-storage
```

## Usage

### Via DSN

```php
use WPPack\Component\Storage\Adapter\Storage;

// Using account name from host
$adapter = Storage::fromDsn('azure://myaccount.blob.core.windows.net/mycontainer/uploads');

// With explicit credentials
$adapter = Storage::fromDsn('azure://myaccount:ACCOUNT_KEY@myaccount.blob.core.windows.net/mycontainer');

// With public URL (CDN)
$adapter = Storage::fromDsn('azure://myaccount.blob.core.windows.net/mycontainer/uploads?public_url=https://cdn.example.com');

// With connection string
$adapter = Storage::fromDsn('azure://myaccount.blob.core.windows.net/mycontainer?connection_string=DefaultEndpointsProtocol%3Dhttps%3BAccountName%3D...');
```

### Direct Instantiation

```php
use AzureOss\Storage\Blob\BlobServiceClient;
use WPPack\Component\Storage\Bridge\Azure\AzureBlobClient;
use WPPack\Component\Storage\Bridge\Azure\AzureStorageAdapter;

$serviceClient = BlobServiceClient::fromConnectionString('DefaultEndpointsProtocol=https;AccountName=myaccount;AccountKey=...');

$adapter = new AzureStorageAdapter(
    client: new AzureBlobClient($serviceClient, 'mycontainer'),
    prefix: 'uploads',
    publicUrl: 'https://cdn.example.com',
);
```

### Temporary URLs (SAS Token)

```php
$url = $adapter->temporaryUrl('private/document.pdf', new \DateTimeImmutable('+1 hour'));
```

### Temporary Upload URLs (SAS Token with Write Permission)

```php
$url = $adapter->temporaryUploadUrl('uploads/photo.jpg', new \DateTimeImmutable('+1 hour'), [
    'Content-Type' => 'image/jpeg',
    'Content-Length' => 1024000,
]);
```

## DSN Format

```
azure://{account}.blob.core.windows.net/{container}/{prefix}
```

| Part | Meaning | Example |
|------|---------|---------|
| Host | `{account}.blob.core.windows.net` | `myaccount.blob.core.windows.net` |
| Path | `/{container}/{prefix}` | `/mycontainer/uploads` |
| User:Pass | Account name + access key (optional) | `myaccount:KEY@` |
| Query | Extra options | `?public_url=https://cdn.example.com` |

### Query Options

| Option | Description | Example |
|--------|-------------|---------|
| `public_url` | Public base URL for `publicUrl()` | `https://cdn.example.com` |
| `connection_string` | Azure connection string | `DefaultEndpointsProtocol=https;...` |

### Alternative Host Formats

```php
// Plain account name
'azure://myaccount/mycontainer/uploads'
```
