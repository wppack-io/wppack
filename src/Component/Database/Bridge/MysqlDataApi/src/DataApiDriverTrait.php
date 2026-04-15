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

namespace WpPack\Component\Database\Bridge\MysqlDataApi;

use AsyncAws\RdsDataService\Input\BeginTransactionRequest;
use AsyncAws\RdsDataService\Input\CommitTransactionRequest;
use AsyncAws\RdsDataService\Input\ExecuteStatementRequest;
use AsyncAws\RdsDataService\Input\RollbackTransactionRequest;
use AsyncAws\RdsDataService\RdsDataServiceClient;
use AsyncAws\RdsDataService\ValueObject\Field;
use AsyncAws\RdsDataService\ValueObject\SqlParameter;
use WpPack\Component\Database\Exception\DriverException;
use WpPack\Component\Database\Result;
use WpPack\Component\Database\Statement;

/**
 * Shared RDS Data API logic for MySQL and PostgreSQL Data API drivers.
 *
 * HTTP-based, stateless — no persistent connections.
 */
trait DataApiDriverTrait
{
    private RdsDataServiceClient $dataApiClient;
    private string $resourceArn;
    private string $secretArn;
    private string $dataApiDatabase;
    private ?string $transactionId = null;

    public function isConnected(): bool
    {
        return true; // Stateless HTTP
    }

    public function inTransaction(): bool
    {
        return $this->transactionId !== null;
    }

    public function getNativeConnection(): RdsDataServiceClient
    {
        return $this->dataApiClient;
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
        $request = $this->buildDataApiRequest($sql, $params);
        $request['includeResultMetadata'] = true;

        $response = $this->dataApiClient->executeStatement(new ExecuteStatementRequest($request));

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
        $request = $this->buildDataApiRequest($sql, $params);
        $response = $this->dataApiClient->executeStatement(new ExecuteStatementRequest($request));

        return (int) $response->getNumberOfRecordsUpdated();
    }

    protected function doPrepare(string $sql): Statement
    {
        $driver = $this;

        $executeQuery = static function (array $params) use ($driver, $sql): Result {
            return $driver->executeQuery($sql, $params);
        };

        $executeStatement = static function (array $params) use ($driver, $sql): int {
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
        $response = $this->dataApiClient->beginTransaction(new BeginTransactionRequest([
            'resourceArn' => $this->resourceArn,
            'secretArn' => $this->secretArn,
            'database' => $this->dataApiDatabase,
        ]));

        $this->transactionId = $response->getTransactionId();
    }

    protected function doCommit(): void
    {
        if ($this->transactionId === null) {
            throw new DriverException('No active transaction to commit.');
        }

        $this->dataApiClient->commitTransaction(new CommitTransactionRequest([
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

        $this->dataApiClient->rollbackTransaction(new RollbackTransactionRequest([
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
    private function buildDataApiRequest(string $sql, array $params): array
    {
        $request = [
            'resourceArn' => $this->resourceArn,
            'secretArn' => $this->secretArn,
            'database' => $this->dataApiDatabase,
            'sql' => $this->convertDataApiPlaceholders($sql),
        ];

        if ($this->transactionId !== null) {
            $request['transactionId'] = $this->transactionId;
        }

        if ($params !== []) {
            $request['parameters'] = $this->buildDataApiParameters($params);
        }

        return $request;
    }

    private function convertDataApiPlaceholders(string $sql): string
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
    private function buildDataApiParameters(array $params): array
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
