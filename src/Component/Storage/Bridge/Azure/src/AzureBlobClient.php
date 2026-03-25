<?php
/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WpPack\Component\Storage\Bridge\Azure;

use AzureOss\Storage\Blob\BlobContainerClient;
use AzureOss\Storage\Blob\BlobServiceClient;
use AzureOss\Storage\Blob\Models\Blob;
use AzureOss\Storage\Blob\Models\BlobPrefix;
use AzureOss\Storage\Blob\Models\BlobProperties;
use AzureOss\Storage\Blob\Models\UploadBlobOptions;
use AzureOss\Storage\Blob\Sas\BlobSasBuilder;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

final class AzureBlobClient implements AzureBlobClientInterface
{
    private readonly BlobContainerClient $containerClient;

    public function __construct(
        BlobServiceClient $serviceClient,
        string $container,
    ) {
        $this->containerClient = $serviceClient->getContainerClient($container);
    }

    public function upload(string $blobName, mixed $content, ?UploadBlobOptions $options = null): void
    {
        $this->containerClient->getBlobClient($blobName)->upload($content, $options);
    }

    public function downloadStreamingContent(string $blobName): StreamInterface
    {
        return $this->containerClient->getBlobClient($blobName)->downloadStreaming()->content;
    }

    public function delete(string $blobName): void
    {
        $this->containerClient->getBlobClient($blobName)->delete();
    }

    public function getProperties(string $blobName): BlobProperties
    {
        return $this->containerClient->getBlobClient($blobName)->getProperties();
    }

    public function syncCopyFromUri(string $destinationBlobName, UriInterface $source): void
    {
        $this->containerClient->getBlobClient($destinationBlobName)->syncCopyFromUri($source);
    }

    public function getBlobUri(string $blobName): UriInterface
    {
        return $this->containerClient->getBlobClient($blobName)->uri;
    }

    public function generateSasUri(string $blobName, BlobSasBuilder $builder): UriInterface
    {
        return $this->containerClient->getBlobClient($blobName)->generateSasUri($builder);
    }

    /** @return iterable<Blob|BlobPrefix> */
    public function listBlobsByHierarchy(?string $prefix = null, string $delimiter = '/'): iterable
    {
        return $this->containerClient->getBlobsByHierarchy($prefix, $delimiter);
    }
}
