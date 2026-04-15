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

use AsyncAws\Core\Configuration;
use AsyncAws\Core\Credentials\ChainProvider;
use AsyncAws\Core\Credentials\CredentialProvider;
use AsyncAws\Core\Request;
use AsyncAws\Core\RequestContext;
use AsyncAws\Core\Signer\SignerV4;
use AsyncAws\Core\Stream\StringStream;
use Psr\Log\LoggerInterface;
use WpPack\Component\Database\Bridge\AuroraDsql\Translator\AuroraDsqlQueryTranslator;
use WpPack\Component\Database\Bridge\Pgsql\PgsqlDriver;
use WpPack\Component\Database\Bridge\Pgsql\PostgresqlPlatform;
use WpPack\Component\Database\Driver\AbstractDriver;
use WpPack\Component\Database\Exception\ConnectionException;
use WpPack\Component\Database\Exception\DriverException;
use WpPack\Component\Database\Platform\PlatformInterface;
use WpPack\Component\Database\Result;
use WpPack\Component\Database\Statement;
use WpPack\Component\Database\Translator\QueryTranslatorInterface;

/**
 * Aurora DSQL driver with IAM token authentication, SSL, and OCC retry.
 *
 * Uses PostgreSQL wire protocol with:
 * - SigV4 presigned IAM tokens (auto-generated via async-aws/core)
 * - SSL verify-full (mandatory for DSQL)
 * - Optimistic Concurrency Control retry with exponential backoff + jitter
 * - Automatic token refresh when approaching expiry
 */
final class AuroraDsqlDriver extends AbstractDriver
{
    private const OCC_INITIAL_WAIT_MS = 100;
    private const OCC_MAX_WAIT_MS = 5000;
    private const OCC_MULTIPLIER = 2.0;

    /** Token refresh margin — reconnect 60s before expiry */
    private const TOKEN_REFRESH_MARGIN_SECS = 60;

    private ?PgsqlDriver $inner = null;
    private ?\DateTimeImmutable $tokenExpiresAt = null;
    private ?LoggerInterface $logger;

    public function __construct(
        private readonly string $endpoint,
        private readonly string $region,
        private readonly string $database,
        private readonly string $username = 'admin',
        #[\SensitiveParameter]
        private readonly ?string $token = null,
        private readonly int $tokenDurationSecs = 900,
        private readonly int $occMaxRetries = 3,
        private readonly ?CredentialProvider $credentialProvider = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger;
    }

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

    public function getQueryTranslator(): QueryTranslatorInterface
    {
        return new AuroraDsqlQueryTranslator();
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

        if ($this->token !== null) {
            // Static token: no expiry tracking (user must manage refresh)
            $password = $this->token;
        } else {
            // Auto-generated IAM token with expiry tracking
            $password = $this->generateToken();
        }

        $this->inner = new PgsqlDriver(
            host: $this->endpoint,
            username: $this->username,
            password: $password,
            database: $this->database,
            port: 5432,
            sslmode: 'verify-full',
        );

        $this->inner->connect();
    }

    protected function doClose(): void
    {
        $this->inner?->close();
        $this->inner = null;
        $this->tokenExpiresAt = null;
    }

    protected function doExecuteQuery(string $sql, array $params = []): Result
    {
        return $this->executeWithOccRetry(function () use ($sql, $params): Result {
            $this->ensureConnected();

            return $this->inner->executeQuery($sql, $params);
        });
    }

    protected function doExecuteStatement(string $sql, array $params = []): int
    {
        return $this->executeWithOccRetry(function () use ($sql, $params): int {
            $this->ensureConnected();

            return $this->inner->executeStatement($sql, $params);
        });
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

    /**
     * Commit without OCC retry. If COMMIT fails with OCC conflict, the caller
     * must retry the entire transaction (not just the COMMIT). Retrying COMMIT
     * alone risks duplicate writes if the first COMMIT partially succeeded.
     */
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

    /**
     * Ensure connection is alive and token is not expired.
     *
     * Reconnects if the connection is lost or the IAM token is approaching expiry.
     */
    private function ensureConnected(): void
    {
        if ($this->inner !== null && $this->inner->isConnected() && !$this->isTokenExpiring()) {
            return;
        }

        // Close stale connection before reconnecting with fresh token
        if ($this->inner !== null) {
            $this->inner->close();
            $this->inner = null;
        }

        $this->connect();
    }

    private function isTokenExpiring(): bool
    {
        if ($this->tokenExpiresAt === null) {
            return false;
        }

        return new \DateTimeImmutable() >= $this->tokenExpiresAt;
    }

    // ── IAM Token Generation ──

    /**
     * Generate IAM authentication token via SigV4 presigned URL.
     *
     * Uses the same pattern as ElastiCacheIamTokenGenerator:
     * presign a GET request to the DSQL endpoint with Action=DbConnect.
     */
    private function generateToken(): string
    {
        if (!class_exists(Configuration::class)) {
            throw new ConnectionException(
                'Aurora DSQL requires async-aws/core for IAM token generation. '
                . 'Install it via: composer require async-aws/core. '
                . 'Alternatively, provide a pre-generated token via the constructor.',
            );
        }

        $provider = $this->credentialProvider ?? ChainProvider::createDefaultChain();
        $credentials = $provider->getCredentials(
            Configuration::create(['region' => $this->region]),
        );

        if ($credentials === null) {
            throw new ConnectionException(
                'Unable to resolve AWS credentials for Aurora DSQL. '
                . 'Ensure AWS credentials are configured (env vars, IAM role, or credentials file).',
            );
        }

        $action = $this->username === 'admin' ? 'DbConnectAdmin' : 'DbConnect';

        $request = new Request('GET', '/', [], [], StringStream::create(''));
        $request->setEndpoint(\sprintf('https://%s', $this->endpoint));
        $request->setQueryAttribute('Action', $action);

        $expiresAt = new \DateTimeImmutable(\sprintf('+%d seconds', $this->tokenDurationSecs));
        $context = new RequestContext([
            'expirationDate' => $expiresAt,
        ]);

        $signer = new SignerV4('dsql', $this->region);
        $signer->presign($request, $credentials, $context);

        // Track token expiry (with refresh margin)
        $this->tokenExpiresAt = $expiresAt->modify(
            \sprintf('-%d seconds', self::TOKEN_REFRESH_MARGIN_SECS),
        );

        return str_replace('https://', '', $request->getEndpoint());
    }

    // ── OCC Retry ──

    /**
     * Execute an operation with OCC retry (exponential backoff + jitter).
     *
     * Aurora DSQL uses Optimistic Concurrency Control. On conflict, it returns
     * SQLSTATE 40001, OC000, or OC001. This method retries the operation with
     * exponential backoff and random jitter.
     *
     * @template T
     *
     * @param \Closure(): T $operation
     *
     * @return T
     */
    private function executeWithOccRetry(\Closure $operation): mixed
    {
        if ($this->occMaxRetries === 0) {
            return $operation();
        }

        $waitMs = self::OCC_INITIAL_WAIT_MS;

        for ($attempt = 0; $attempt <= $this->occMaxRetries; ++$attempt) {
            try {
                return $operation();
            } catch (DriverException $e) {
                if (!self::isOccError($e) || $attempt === $this->occMaxRetries) {
                    throw $e;
                }

                $jitter = random_int(0, $waitMs);
                $sleepMs = $waitMs + $jitter;

                $this->logger?->warning(\sprintf(
                    '[AuroraDsql] OCC conflict, retrying (attempt %d/%d, wait %.2fs)',
                    $attempt + 1,
                    $this->occMaxRetries,
                    $sleepMs / 1000.0,
                ));

                usleep($sleepMs * 1000);
                $waitMs = (int) min($waitMs * self::OCC_MULTIPLIER, self::OCC_MAX_WAIT_MS);
            }
        }

        throw new DriverException('OCC max retries exceeded');
    }

    /**
     * Check if an exception is an OCC conflict error.
     *
     * SQLSTATE codes: 40001 (serialization failure), OC000/OC001 (DSQL-specific).
     */
    private static function isOccError(\Throwable $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, '40001')
            || str_contains($message, 'OC000')
            || str_contains($message, 'OC001');
    }
}
