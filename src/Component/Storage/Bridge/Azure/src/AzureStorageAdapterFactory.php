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

use AzureOss\Storage\Blob\BlobServiceClient;
use WpPack\Component\Storage\Adapter\Dsn;
use WpPack\Component\Storage\Adapter\StorageAdapterFactoryInterface;
use WpPack\Component\Storage\Adapter\StorageAdapterInterface;
use WpPack\Component\Storage\Exception\InvalidArgumentException;

final class AzureStorageAdapterFactory implements StorageAdapterFactoryInterface
{
    public function create(Dsn $dsn, array $options = []): StorageAdapterInterface
    {
        $account = $this->parseAccount($dsn, $options);

        if ($account === null) {
            throw new InvalidArgumentException('Cannot determine account name from Azure storage DSN. Supported formats: "azure://{account}.blob.core.windows.net/{container}" or "azure://{account}/{container}".');
        }

        $container = $this->parseContainer($dsn, $options);

        if ($container === null) {
            throw new InvalidArgumentException('Cannot determine container name from Azure storage DSN. Supported formats: "azure://{account}.blob.core.windows.net/{container}" or "azure://{account}/{container}".');
        }

        $prefix = $this->parsePrefix($dsn, $options);
        $publicUrl = $dsn->getOption('public_url') ?? $options['public_url'] ?? null;
        $connectionString = $dsn->getOption('connection_string') ?? $options['connection_string'] ?? null;

        if (isset($options['client']) && $options['client'] instanceof AzureBlobClientInterface) {
            $client = $options['client'];
        } else {
            if (isset($options['service_client']) && $options['service_client'] instanceof BlobServiceClient) {
                $serviceClient = $options['service_client'];
            } elseif ($connectionString !== null) {
                $serviceClient = BlobServiceClient::fromConnectionString($connectionString);
            } else {
                $accountKey = $dsn->getPassword() ?? $options['account_key'] ?? null;
                $accountName = $dsn->getUser() ?? $account;

                if ($accountKey !== null) {
                    $connStr = sprintf(
                        'DefaultEndpointsProtocol=https;AccountName=%s;AccountKey=%s',
                        $accountName,
                        $accountKey,
                    );
                    $serviceClient = BlobServiceClient::fromConnectionString($connStr);
                } else {
                    $serviceClient = BlobServiceClient::fromConnectionString(
                        sprintf('DefaultEndpointsProtocol=https;AccountName=%s', $account),
                    );
                }
            }

            $client = new AzureBlobClient($serviceClient, $container);
        }

        return new AzureStorageAdapter(
            client: $client,
            prefix: $prefix,
            publicUrl: $publicUrl,
        );
    }

    public function supports(Dsn $dsn): bool
    {
        return $dsn->getScheme() === 'azure';
    }

    /**
     * Parse account name from DSN host.
     *
     * Supported formats:
     *   {account}.blob.core.windows.net → account name
     *   {account}                       → plain account name
     *
     * @param array<string, mixed> $options
     */
    private function parseAccount(Dsn $dsn, array $options): ?string
    {
        $host = $dsn->getHost();

        if ($host === null) {
            return $options['account'] ?? null;
        }

        // {account}.blob.core.windows.net
        if (preg_match('/^(.+)\.blob\.core\.windows\.net$/', $host, $matches)) {
            return $matches[1];
        }

        // Plain host = account name
        return $host;
    }

    /**
     * Parse container name from DSN path.
     *
     * @param array<string, mixed> $options
     */
    private function parseContainer(Dsn $dsn, array $options): ?string
    {
        $path = ltrim($dsn->getPath() ?? '', '/');

        if ($path !== '') {
            // First segment of the path is the container
            $segments = explode('/', $path, 2);

            return $segments[0];
        }

        return $options['container'] ?? null;
    }

    /**
     * Parse prefix from DSN path (everything after container).
     *
     * @param array<string, mixed> $options
     */
    private function parsePrefix(Dsn $dsn, array $options): string
    {
        $path = ltrim($dsn->getPath() ?? '', '/');

        if ($path !== '') {
            $segments = explode('/', $path, 2);

            return $segments[1] ?? '';
        }

        return $options['prefix'] ?? '';
    }
}
