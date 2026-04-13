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

namespace WpPack\Component\Database\Bridge\RdsDataApi;

use AsyncAws\RdsDataService\Input\BatchExecuteStatementRequest;
use AsyncAws\RdsDataService\Input\BeginTransactionRequest;
use AsyncAws\RdsDataService\Input\CommitTransactionRequest;
use AsyncAws\RdsDataService\Input\ExecuteStatementRequest;
use AsyncAws\RdsDataService\Input\RollbackTransactionRequest;
use AsyncAws\RdsDataService\RdsDataServiceClient;
use AsyncAws\RdsDataService\ValueObject\Field;
use AsyncAws\RdsDataService\ValueObject\SqlParameter;
use WpPack\Component\Database\Driver\AbstractDriver;
use WpPack\Component\Database\Exception\DriverException;
use WpPack\Component\Database\Platform\MysqlPlatform;
use WpPack\Component\Database\Platform\PlatformInterface;
use WpPack\Component\Database\Result;
use WpPack\Component\Database\Statement;

/**
 * RDS Data API driver for Aurora MySQL/PostgreSQL Serverless.
 *
 * HTTP-based, stateless driver — no persistent connections.
 * Requires async-aws/rds-data-service package.
 */
final class RdsDataApiDriver extends AbstractDriver
{
    private ?string $transactionId = null;

    public function __construct(
        private readonly RdsDataServiceClient $client,
        private readonly string $resourceArn,
        #[\SensitiveParameter]
        private readonly string $secretArn,
        private readonly string $database,
    ) {}

    public function getName(): string
    {
        return 'rds-data-api';
    }

    public function isConnected(): bool
    {
        return true;
    }

    public function inTransaction(): bool
    {
        return $this->transactionId !== null;
    }

    public function getPlatform(): PlatformInterface
    {
        return new MysqlPlatform();
    }

    public function getNativeConnection(): RdsDataServiceClient
    {
        return $this->client;
    }

    protected function doConnect(): void
    {
        // Stateless HTTP — no connection to establish
    }

    protected function doClose(): void
    {
        $this->transactionId = null;
    }

    protected function doExecuteQuery(string $sql, array $params = []): Result
    {
        $request = $this->buildRequest($sql, $params);
        $request['includeResultMetadata'] = true;

        $response = $this->client->executeStatement(new ExecuteStatementRequest($request));

        $columnMetadata = $response->getColumnMetadata();
        $records = $response->getRecords();

        $columnNames = [];

        foreach ($columnMetadata as $col) {
            $columnNames[] = $col->getLabel() ?? $col->getName() ?? '';
        }

        $rows = [];

        foreach ($records as $record) {
            $row = [];

            foreach ($record as $i => $field) {
                $name = $columnNames[$i] ?? "col_{$i}";
                $row[$name] = $this->fieldToPhp($field);
            }

            $rows[] = $row;
        }

        return new Result($rows, (int) $response->getNumberOfRecordsUpdated());
    }

    protected function doExecuteStatement(string $sql, array $params = []): int
    {
        $request = $this->buildRequest($sql, $params);
        $response = $this->client->executeStatement(new ExecuteStatementRequest($request));

        return (int) $response->getNumberOfRecordsUpdated();
    }

    protected function doPrepare(string $sql): Statement
    {
        $driver = $this;

        $executeQuery = function (array $params) use ($driver, $sql): Result {
            return $driver->executeQuery($sql, $params);
        };

        $executeStatement = function (array $params) use ($driver, $sql): int {
            return $driver->executeStatement($sql, $params);
        };

        $close = static function (): void {};

        return new Statement($executeQuery, $executeStatement, $close);
    }

    protected function doLastInsertId(): int
    {
        $result = $this->executeQuery('SELECT LAST_INSERT_ID() AS id');
        $row = $result->fetchAssociative();

        return $row !== null ? (int) ($row['id'] ?? 0) : 0;
    }

    protected function doBeginTransaction(): void
    {
        $response = $this->client->beginTransaction(new BeginTransactionRequest([
            'resourceArn' => $this->resourceArn,
            'secretArn' => $this->secretArn,
            'database' => $this->database,
        ]));

        $this->transactionId = $response->getTransactionId();
    }

    protected function doCommit(): void
    {
        if ($this->transactionId === null) {
            throw new DriverException('No active transaction to commit.');
        }

        $this->client->commitTransaction(new CommitTransactionRequest([
            'resourceArn' => $this->resourceArn,
            'secretArn' => $this->secretArn,
            'transactionId' => $this->transactionId,
        ]));

        $this->transactionId = null;
    }

    protected function doRollBack(): void
    {
        if ($this->transactionId === null) {
            throw new DriverException('No active transaction to roll back.');
        }

        $this->client->rollbackTransaction(new RollbackTransactionRequest([
            'resourceArn' => $this->resourceArn,
            'secretArn' => $this->secretArn,
            'transactionId' => $this->transactionId,
        ]));

        $this->transactionId = null;
    }

    /**
     * @param list<mixed> $params
     *
     * @return array<string, mixed>
     */
    private function buildRequest(string $sql, array $params): array
    {
        $request = [
            'resourceArn' => $this->resourceArn,
            'secretArn' => $this->secretArn,
            'database' => $this->database,
            'sql' => $this->convertPlaceholders($sql),
        ];

        if ($this->transactionId !== null) {
            $request['transactionId'] = $this->transactionId;
        }

        if ($params !== []) {
            $request['parameters'] = $this->buildParameters($params);
        }

        return $request;
    }

    /**
     * Convert ? placeholders to :param1, :param2, ... for RDS Data API.
     */
    private function convertPlaceholders(string $sql): string
    {
        $index = 0;

        return (string) preg_replace_callback('/\?/', static function () use (&$index): string {
            return ':param' . (++$index);
        }, $sql);
    }

    /**
     * @param list<mixed> $params
     *
     * @return list<SqlParameter>
     */
    private function buildParameters(array $params): array
    {
        $sqlParams = [];

        foreach ($params as $i => $value) {
            $name = 'param' . ($i + 1);

            $field = match (true) {
                $value === null => ['isNull' => true],
                \is_int($value) => ['longValue' => $value],
                \is_float($value) => ['doubleValue' => $value],
                \is_bool($value) => ['booleanValue' => $value],
                default => ['stringValue' => (string) $value],
            };

            $sqlParams[] = new SqlParameter([
                'name' => $name,
                'value' => new Field($field),
            ]);
        }

        return $sqlParams;
    }

    private function fieldToPhp(Field $field): mixed
    {
        if ($field->getIsNull()) {
            return null;
        }

        if (($v = $field->getLongValue()) !== null) {
            return (int) $v;
        }

        if (($v = $field->getDoubleValue()) !== null) {
            return $v;
        }

        if (($v = $field->getBooleanValue()) !== null) {
            return $v;
        }

        if (($v = $field->getBlobValue()) !== null) {
            return $v;
        }

        return $field->getStringValue();
    }
}
