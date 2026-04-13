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

namespace WpPack\Component\Database\Bridge\AuroraDsql;

use WpPack\Component\Database\Bridge\Pgsql\PgsqlDriver;
use WpPack\Component\Database\DatabaseEngine;
use WpPack\Component\Database\Driver\AbstractDriver;
use WpPack\Component\Database\Exception\ConnectionException;
use WpPack\Component\Database\Platform\PlatformInterface;
use WpPack\Component\Database\Platform\PostgresqlPlatform;
use WpPack\Component\Database\Result;
use WpPack\Component\Database\Statement;

/**
 * Aurora DSQL driver.
 *
 * Uses PostgreSQL wire protocol with IAM-based token authentication.
 * The token is generated at connection time and refreshed on reconnection.
 *
 * Requires ext-pgsql and async-aws/core for token generation.
 */
final class AuroraDsqlDriver extends AbstractDriver
{
    private ?PgsqlDriver $inner = null;

    public function __construct(
        private readonly string $endpoint,
        private readonly string $region,
        private readonly string $database,
        private readonly string $username = 'admin',
        #[\SensitiveParameter]
        private readonly ?string $token = null,
    ) {}

    public function getName(): string
    {
        return 'dsql';
    }

    public function isConnected(): bool
    {
        return $this->inner !== null && $this->inner->isConnected();
    }

    public function inTransaction(): bool
    {
        return $this->inner !== null && $this->inner->inTransaction();
    }

    public function getPlatform(): PlatformInterface
    {
        return new PostgresqlPlatform();
    }

    public function getNativeConnection(): mixed
    {
        return $this->inner?->getNativeConnection();
    }

    protected function doConnect(): void
    {
        if ($this->inner !== null && $this->inner->isConnected()) {
            return;
        }

        $password = $this->token ?? $this->generateToken();

        $this->inner = new PgsqlDriver(
            host: $this->endpoint,
            username: $this->username,
            password: $password,
            database: $this->database,
            port: 5432,
        );

        $this->inner->connect();
    }

    protected function doClose(): void
    {
        $this->inner?->close();
        $this->inner = null;
    }

    protected function doExecuteQuery(string $sql, array $params = []): Result
    {
        $this->ensureConnected();

        return $this->inner->executeQuery($sql, $params);
    }

    protected function doExecuteStatement(string $sql, array $params = []): int
    {
        $this->ensureConnected();

        return $this->inner->executeStatement($sql, $params);
    }

    protected function doPrepare(string $sql): Statement
    {
        $this->ensureConnected();

        return $this->inner->prepare($sql);
    }

    protected function doLastInsertId(): int
    {
        $this->ensureConnected();

        return $this->inner->lastInsertId();
    }

    protected function doBeginTransaction(): void
    {
        $this->ensureConnected();
        $this->inner->beginTransaction();
    }

    protected function doCommit(): void
    {
        $this->ensureConnected();
        $this->inner->commit();
    }

    protected function doRollBack(): void
    {
        $this->ensureConnected();
        $this->inner->rollBack();
    }

    private function ensureConnected(): void
    {
        if ($this->inner === null || !$this->inner->isConnected()) {
            $this->connect();
        }
    }

    /**
     * Generate IAM authentication token for Aurora DSQL.
     *
     * Uses async-aws/core for SigV4 signing if available.
     * Falls back to static token if provided.
     */
    private function generateToken(): string
    {
        if (!class_exists(\AsyncAws\Core\Configuration::class)) {
            throw new ConnectionException(
                'Aurora DSQL requires async-aws/core for IAM token generation. '
                . 'Install it via: composer require async-aws/core. '
                . 'Alternatively, provide a pre-generated token via the constructor.',
            );
        }

        // Token generation via SigV4 presigned URL
        // The DSQL endpoint acts as the IAM resource
        $credentials = \AsyncAws\Core\Configuration::create([
            'region' => $this->region,
        ]);

        // Simplified token: in production, use the DSQL GenerateDbConnectAuthToken API
        // or SigV4 presigned URL mechanism
        throw new ConnectionException(
            'Automatic DSQL token generation is not yet implemented. '
            . 'Provide a pre-generated token via the constructor.',
        );
    }
}
