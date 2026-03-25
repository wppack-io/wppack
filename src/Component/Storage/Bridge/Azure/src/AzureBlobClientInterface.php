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

use AzureOss\Storage\Blob\Models\Blob;
use AzureOss\Storage\Blob\Models\BlobPrefix;
use AzureOss\Storage\Blob\Models\BlobProperties;
use AzureOss\Storage\Blob\Models\UploadBlobOptions;
use AzureOss\Storage\Blob\Sas\BlobSasBuilder;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

interface AzureBlobClientInterface
{
    public function upload(string $blobName, mixed $content, ?UploadBlobOptions $options = null): void;

    public function downloadStreamingContent(string $blobName): StreamInterface;

    public function delete(string $blobName): void;

    public function getProperties(string $blobName): BlobProperties;

    public function syncCopyFromUri(string $destinationBlobName, UriInterface $source): void;

    public function getBlobUri(string $blobName): UriInterface;

    public function generateSasUri(string $blobName, BlobSasBuilder $builder): UriInterface;

    /** @return iterable<Blob|BlobPrefix> */
    public function listBlobsByHierarchy(?string $prefix = null, string $delimiter = '/'): iterable;
}
