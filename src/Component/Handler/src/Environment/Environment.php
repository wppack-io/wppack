<?php

declare(strict_types=1);

namespace WpPack\Component\Handler\Environment;

use WpPack\Component\Handler\Configuration;

class Environment implements EnvironmentInterface
{
    private readonly string $platform;

    /** @var array<string, mixed> */
    private array $platformInfo = [];

    public function __construct(
        private readonly Configuration $config,
    ) {
        $this->platform = $this->detectPlatform();

        if ($this->platform === 'lambda') {
            $this->platformInfo = $this->collectLambdaInfo();
        }
    }

    public function setup(): void
    {
        match ($this->platform) {
            'lambda' => $this->setupLambda(),
            default => null,
        };
    }

    public function isLambda(): bool
    {
        return $this->platform === 'lambda';
    }

    /**
     * @return array<string, mixed>
     */
    public function getInfo(): array
    {
        $info = ['platform' => $this->platform];

        if ($this->platform === 'lambda') {
            $info['lambda'] = $this->platformInfo;
        }

        return $info;
    }

    private function detectPlatform(): string
    {
        $lambdaConfig = $this->config->get('lambda', []);

        if (isset($lambdaConfig['enabled'])) {
            return $lambdaConfig['enabled'] ? 'lambda' : 'standard';
        }

        return $this->autoDetectPlatform();
    }

    private function autoDetectPlatform(): string
    {
        $isLambda = isset($_ENV['AWS_LAMBDA_FUNCTION_NAME'])
            || isset($_ENV['LAMBDA_TASK_ROOT'])
            || isset($_ENV['_HANDLER']);

        return $isLambda ? 'lambda' : 'standard';
    }

    /**
     * @return array<string, mixed>
     */
    private function collectLambdaInfo(): array
    {
        $info = [];
        $envMap = [
            'AWS_LAMBDA_FUNCTION_NAME' => 'function_name',
            'LAMBDA_TASK_ROOT' => 'task_root',
            'AWS_REGION' => 'region',
            'AWS_DEFAULT_REGION' => 'region',
            'AWS_LAMBDA_FUNCTION_MEMORY_SIZE' => 'memory_limit',
            'AWS_LAMBDA_FUNCTION_VERSION' => 'function_version',
            'AWS_LAMBDA_LOG_GROUP_NAME' => 'log_group',
            'AWS_LAMBDA_LOG_STREAM_NAME' => 'log_stream',
        ];

        foreach ($envMap as $envKey => $infoKey) {
            if (isset($_ENV[$envKey]) && !isset($info[$infoKey])) {
                $info[$infoKey] = $infoKey === 'memory_limit'
                    ? (int) $_ENV[$envKey]
                    : $_ENV[$envKey];
            }
        }

        return $info;
    }

    private function setupLambda(): void
    {
        $defaultDirs = ['/tmp/uploads', '/tmp/cache', '/tmp/sessions'];
        $dirs = $this->config->get('lambda.directories', $defaultDirs);

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0o777, true);
            }
        }
    }
}
