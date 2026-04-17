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

namespace WpPack\Component\Database\Driver;

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
 *
 * Response-size limit: the Data API caps each ExecuteStatement response
 * at ~1 MB (subject to change by AWS). There is no nextPageToken / cursor,
 * so result sets larger than that are rejected server-side. Callers that
 * need to stream large result sets must paginate at the SQL level with
 * LIMIT / OFFSET (or keyset pagination). Result-set handling here loads
 * every row into memory; for production workloads above a few thousand
 * rows per call, use server-side pagination or the native engine driver
 * instead. doExecuteQuery emits a logger warning when the record count
 * crosses a soft threshold so operators can catch these callsites early.
 */
trait DataApiDriverTrait
{
    /**
     * Soft threshold for a Data API query response. Above this row count we
     * emit a PSR logger warning so callers can add LIMIT / keyset pagination
     * before the 1 MB hard limit starts truncating real traffic.
     */
    private const LARGE_RESULT_WARNING_THRESHOLD = 5000;
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

    /**
     * Override the parent driver's escape so we never reach for an unset
     * native MySQL/PgSQL connection — Data API is stateless HTTP with no
     * live socket. Values always leave this driver via structured
     * parameter binding (executeQuery($sql, $params)); this method is
     * only used by WpPackWpdb for debug/log display.
     */
    public function quoteStringLiteral(string $value): string
    {
        return "'" . addslashes($value) . "'";
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

        try {
            $response = $this->dataApiClient->executeStatement(new ExecuteStatementRequest($request));
        } catch (\Throwable $e) {
            // Surface the 1 MB hard limit with a dedicated message so ops can
            // correlate production failures with the API-level cap instead of
            // chasing a generic "server returned 500".
            throw new DriverException($this->dataApiErrorMessage($sql, $e), 0, $e);
        }

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

        if (\count($rows) >= self::LARGE_RESULT_WARNING_THRESHOLD
            && property_exists($this, 'logger')
            && $this->logger !== null) {
            $this->logger->warning('RDS Data API query returned a large result set; add LIMIT / keyset pagination before hitting the 1 MB response cap', [
                'sql' => $sql,
                'rows' => \count($rows),
                'threshold' => self::LARGE_RESULT_WARNING_THRESHOLD,
            ]);
        }

        return new Result($rows, (int) $response->getNumberOfRecordsUpdated());
    }

    protected function doExecuteStatement(string $sql, array $params = []): int
    {
        $request = $this->buildDataApiRequest($sql, $params);

        try {
            $response = $this->dataApiClient->executeStatement(new ExecuteStatementRequest($request));
        } catch (\Throwable $e) {
            throw new DriverException($this->dataApiErrorMessage($sql, $e), 0, $e);
        }

        return (int) $response->getNumberOfRecordsUpdated();
    }

    /**
     * Wrap a Data API exception message with context (truncated SQL + the
     * AWS-side message) so operators can tell the 1 MB response cap apart
     * from auth / connection / syntax failures without spelunking through
     * the Throwable chain.
     */
    private function dataApiErrorMessage(string $sql, \Throwable $e): string
    {
        $maxLen = 200;
        $truncated = mb_strlen($sql) > $maxLen ? mb_substr($sql, 0, $maxLen) . '...' : $sql;

        return \sprintf(
            'RDS Data API call failed: %s (responses are capped at ~1 MB — use LIMIT / keyset pagination for large SELECTs) [SQL: %s]',
            $e->getMessage(),
            $truncated,
        );
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
