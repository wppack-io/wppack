<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Plugin\RedisCachePlugin\Admin;

use WPPack\Component\Cache\Adapter\AdapterDefinition;
use WPPack\Component\Cache\Adapter\AdapterFactoryInterface;
use WPPack\Component\Cache\Bridge\Apcu\ApcuAdapterFactory;
use WPPack\Component\Cache\Bridge\DynamoDb\DynamoDbAdapterFactory;
use WPPack\Component\Cache\Bridge\Memcached\MemcachedAdapterFactory;
use WPPack\Component\Cache\Bridge\Redis\Adapter\RedisAdapterFactory;
use WPPack\Component\Dsn\Dsn;
use WPPack\Component\Dsn\Exception\InvalidDsnException;
use WPPack\Component\HttpFoundation\JsonResponse;
use WPPack\Component\Rest\AbstractRestController;
use WPPack\Component\Rest\Attribute\RestRoute;
use WPPack\Component\Rest\HttpMethod;
use WPPack\Component\Role\Attribute\IsGranted;
use WPPack\Plugin\RedisCachePlugin\Configuration\RedisCacheConfiguration;

#[RestRoute(namespace: 'wppack/v1/cache')]
#[IsGranted('manage_options')]
final class RedisCacheSettingsController extends AbstractRestController
{
    /** @var list<class-string<AdapterFactoryInterface>> */
    private const FACTORIES = [
        RedisAdapterFactory::class,
        DynamoDbAdapterFactory::class,
        MemcachedAdapterFactory::class,
        ApcuAdapterFactory::class,
    ];

    private const GLOBAL_OPTION_CONSTANTS = [
        'prefix' => 'WPPACK_CACHE_PREFIX',
        'maxTtl' => 'WPPACK_CACHE_MAX_TTL',
        'hashAlloptions' => 'WPPACK_CACHE_HASH_ALLOPTIONS',
        'asyncFlush' => 'WPPACK_CACHE_ASYNC_FLUSH',
        'compression' => 'WPPACK_CACHE_COMPRESSION',
        'serializer' => 'WPPACK_CACHE_REDIS_SERIALIZER',
        'clientLibrary' => 'WPPACK_CACHE_REDIS_CLIENT',
    ];

    #[RestRoute(route: '/settings', methods: HttpMethod::GET)]
    public function getSettings(): JsonResponse
    {
        return $this->json($this->buildResponse());
    }

    #[RestRoute(route: '/settings', methods: HttpMethod::POST)]
    public function saveSettings(\WP_REST_Request $request): JsonResponse
    {
        /** @var array<string, mixed> $params */
        $params = $request->get_json_params();

        $error = $this->validateDsn($params);
        if ($error !== null) {
            return $this->json(['error' => $error], 400);
        }

        $this->persistOptions($params);

        delete_option('rewrite_rules');

        return $this->json($this->buildResponse());
    }

    #[RestRoute(route: '/test', methods: HttpMethod::POST)]
    public function testConnection(): JsonResponse
    {
        try {
            $result = wp_cache_set('wppack_test', 'ok', '', 60);
            $value = wp_cache_get('wppack_test');
            wp_cache_delete('wppack_test');

            $success = $result && $value === 'ok';

            return $this->json(['success' => $success]);
        } catch (\Throwable $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildResponse(): array
    {
        $raw = get_option(RedisCacheConfiguration::OPTION_NAME, []);
        $saved = \is_array($raw) ? $raw : [];

        $source = 'default';
        $dsn = '';

        if (\defined('CACHE_DSN')) {
            $source = 'constant';
            $dsn = RedisCacheConfiguration::MASKED_VALUE;
        } elseif (isset($_ENV['CACHE_DSN']) || getenv('CACHE_DSN') !== false) {
            $source = 'constant';
            $dsn = RedisCacheConfiguration::MASKED_VALUE;
        } elseif (isset($saved['dsn']) && $saved['dsn'] !== '') {
            $source = 'option';
            $dsn = $saved['dsn'];
        }

        // Mask DSN password part for option-sourced values
        $maskedDsn = $dsn;
        if ($source === 'option' && $dsn !== '') {
            $maskedDsn = preg_replace('/:([^@]+)@/', ':' . RedisCacheConfiguration::MASKED_VALUE . '@', $dsn) ?? $dsn;
        }

        // Collect available adapter definitions
        $definitions = [];
        foreach (self::FACTORIES as $factoryClass) {
            if (!class_exists($factoryClass)) {
                continue;
            }
            foreach ($factoryClass::definitions() as $def) {
                $definitions[$def->scheme] = $this->serializeDefinition($def);
            }
        }

        // Add DSN direct input option
        $definitions['dsn'] = [
            'scheme' => 'dsn',
            'label' => 'DSN (Direct Input)',
            'fields' => [
                ['name' => 'dsn', 'label' => 'DSN', 'type' => 'text', 'required' => true, 'default' => null, 'help' => 'e.g., redis://password@127.0.0.1:6379'],
            ],
        ];

        // Detect AWS region from environment
        $awsRegion = \defined('AWS_DEFAULT_REGION') ? (string) \constant('AWS_DEFAULT_REGION') : '';
        if ($awsRegion === '') {
            $awsRegion = getenv('AWS_DEFAULT_REGION') ?: (getenv('AWS_REGION') ?: '');
        }

        // Global options (constant → saved → default)
        $globalOptions = $this->buildGlobalOptions($saved);

        // Parse DSN into fields for readonly display when set via constant/env
        $parsedFields = [];
        $parsedProvider = '';
        if ($source === 'constant') {
            $rawDsn = \defined('CACHE_DSN') ? (string) \constant('CACHE_DSN') : (getenv('CACHE_DSN') ?: '');
            if ($rawDsn !== '') {
                $parsed = $this->parseDsnToFields($rawDsn, $definitions);
                $parsedProvider = $parsed['provider'];
                $parsedFields = $parsed['fields'];
            }
        }

        return [
            'dsn' => $maskedDsn,
            'provider' => $saved['provider'] ?? $parsedProvider,
            'fields' => $saved['fields'] ?? $parsedFields,
            'parsedProvider' => $parsedProvider,
            'parsedFields' => $parsedFields,
            'source' => $source,
            'readonly' => $source === 'constant',
            'definitions' => $definitions,
            'awsRegion' => $awsRegion,
            'globalOptions' => $globalOptions,
            'extensions' => [
                'redis' => \extension_loaded('redis'),
                'relay' => \extension_loaded('relay'),
                'predis' => class_exists(\Predis\Client::class),
                'igbinary' => \extension_loaded('igbinary'),
                'msgpack' => \extension_loaded('msgpack'),
                'zstd' => \extension_loaded('zstd'),
                'lz4' => \extension_loaded('lz4'),
                'lzf' => \extension_loaded('lzf'),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $saved
     * @return array<string, mixed>
     */
    private function buildGlobalOptions(array $saved): array
    {
        $defaults = [
            'prefix' => 'wp:',
            'maxTtl' => '',
            'hashAlloptions' => false,
            'asyncFlush' => false,
            'compression' => 'none',
            'serializer' => 'none',
            'clientLibrary' => '',
        ];

        $result = [];
        $readonlyFields = [];

        foreach (self::GLOBAL_OPTION_CONSTANTS as $key => $constant) {
            if (\defined($constant)) {
                $result[$key] = \constant($constant);
                $readonlyFields[$key] = true;
            } elseif (isset($saved[$key])) {
                $result[$key] = $saved[$key];
            } else {
                $result[$key] = $defaults[$key];
            }
        }

        $result['readonlyFields'] = $readonlyFields;

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeDefinition(AdapterDefinition $def): array
    {
        $fields = [];
        foreach ($def->fields as $field) {
            $f = [
                'name' => $field->name,
                'label' => $field->label,
                'type' => $field->type,
                'required' => $field->required,
                'default' => $field->default,
                'help' => $field->help,
            ];
            if ($field->options !== null) {
                $f['options'] = $field->options;
            }
            if ($field->maxWidth !== null) {
                $f['maxWidth'] = $field->maxWidth;
            }
            if ($field->conditional !== null) {
                $f['conditional'] = $field->conditional;
            }
            $fields[] = $f;
        }

        return [
            'scheme' => $def->scheme,
            'label' => $def->label,
            'fields' => $fields,
            'capabilities' => $def->capabilities,
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    private function validateDsn(array $input): ?string
    {
        $provider = isset($input['provider']) && \is_string($input['provider']) ? $input['provider'] : '';
        if ($provider !== 'dsn') {
            return null;
        }

        $fields = isset($input['fields']) && \is_array($input['fields']) ? $input['fields'] : [];
        $dsn = isset($fields['dsn']) && \is_string($fields['dsn']) ? $fields['dsn'] : '';

        if ($dsn !== '' && $dsn !== RedisCacheConfiguration::MASKED_VALUE) {
            try {
                Dsn::fromString($dsn);
            } catch (InvalidDsnException) {
                return 'Invalid DSN format.';
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function persistOptions(array $input): void
    {
        if (\defined('CACHE_DSN')) {
            return;
        }

        $raw = get_option(RedisCacheConfiguration::OPTION_NAME, []);
        $saved = \is_array($raw) ? $raw : [];

        $provider = isset($input['provider']) && \is_string($input['provider']) ? $input['provider'] : '';
        $fields = isset($input['fields']) && \is_array($input['fields']) ? $input['fields'] : [];

        if ($provider === 'dsn') {
            $dsn = isset($fields['dsn']) && \is_string($fields['dsn']) ? $fields['dsn'] : '';
            // Skip if masked
            if ($dsn !== RedisCacheConfiguration::MASKED_VALUE) {
                $saved['dsn'] = $dsn;
            }
        } elseif ($provider !== '') {
            // Build DSN from definition + fields
            $definition = $this->findDefinition($provider);
            if ($definition !== null) {
                // Restore masked passwords from existing saved fields
                foreach ($definition->fields as $field) {
                    if ($field->type === 'password' && isset($fields[$field->name]) && $fields[$field->name] === RedisCacheConfiguration::MASKED_VALUE) {
                        $fields[$field->name] = $saved['fields'][$field->name] ?? '';
                    }
                }

                /** @var array<string, string> $stringFields */
                $stringFields = [];
                foreach ($fields as $k => $v) {
                    if (\is_string($k) && \is_string($v)) {
                        $stringFields[$k] = $v;
                    }
                }

                $saved['dsn'] = $definition->buildDsn($stringFields);
            }
        }

        $saved['provider'] = $provider;
        $saved['fields'] = $fields;

        // Persist global options (skip constant-sourced values)
        $globalOptions = isset($input['globalOptions']) && \is_array($input['globalOptions']) ? $input['globalOptions'] : [];
        foreach (self::GLOBAL_OPTION_CONSTANTS as $key => $constant) {
            if (\defined($constant)) {
                continue;
            }
            if (\array_key_exists($key, $globalOptions)) {
                $saved[$key] = $globalOptions[$key];
            }
        }

        update_option(RedisCacheConfiguration::OPTION_NAME, $saved);
    }

    private function findDefinition(string $scheme): ?AdapterDefinition
    {
        foreach (self::FACTORIES as $factoryClass) {
            if (!class_exists($factoryClass)) {
                continue;
            }
            foreach ($factoryClass::definitions() as $def) {
                if ($def->scheme === $scheme) {
                    return $def;
                }
            }
        }

        return null;
    }

    /**
     * Parse a DSN string into provider + field values for readonly display.
     *
     * @param array<string, mixed> $definitions
     * @return array{provider: string, fields: array<string, string>}
     */
    private function parseDsnToFields(string $dsn, array $definitions): array
    {
        try {
            $parsed = Dsn::fromString($dsn);
        } catch (InvalidDsnException) {
            return ['provider' => 'dsn', 'fields' => ['dsn' => $dsn]];
        }

        $scheme = $parsed->getScheme();
        $fields = [];

        // Try to match a definition
        $matchedScheme = '';
        foreach ($definitions as $defScheme => $def) {
            if ($defScheme === $scheme || (isset($def['dsnScheme']) && $def['dsnScheme'] === $scheme)) {
                $matchedScheme = (string) $defScheme;
                break;
            }
        }

        if ($matchedScheme === '') {
            // Check for cluster/sentinel
            if (str_contains($dsn, 'redis_cluster=')) {
                $matchedScheme = 'redis-cluster';
            } elseif (str_contains($dsn, 'redis_sentinel=')) {
                $matchedScheme = 'redis-sentinel';
            } else {
                $matchedScheme = $scheme;
            }
        }

        $user = $parsed->getUser();
        if ($user !== null && $user !== '') {
            $fields['user'] = $user;
        }
        if ($parsed->getPassword() !== null) {
            $fields['password'] = RedisCacheConfiguration::MASKED_VALUE;
        }

        $host = $parsed->getHost();
        if ($host !== null) {
            $fields['host'] = $host;
        }
        $port = $parsed->getPort();
        if ($port !== null) {
            $fields['port'] = (string) $port;
        }
        $path = $parsed->getPath();
        if ($path !== null && $path !== '') {
            $fields['path'] = ltrim($path, '/');
        }

        // Process query-string options
        foreach ($parsed->getOptions() as $key => $value) {
            if ($key === 'host' && \is_array($value)) {
                $fields['nodes'] = implode("\n", $value);
            } elseif ($key === 'redis_sentinel' && \is_string($value)) {
                $fields['masterName'] = $value;
            } elseif (\is_string($value)) {
                $fields[$key] = $value;
            }
        }

        // Map generic fields to definition field names
        $mappedFields = [];
        $def = $definitions[$matchedScheme] ?? null;
        if ($def !== null && isset($def['fields'])) {
            foreach ($def['fields'] as $fieldDef) {
                $dsnPart = $fieldDef['dsnPart'] ?? null;
                $name = $fieldDef['name'];
                if ($dsnPart === 'host' && isset($fields['host'])) {
                    $mappedFields[$name] = $fields['host'];
                } elseif ($dsnPart === 'port' && isset($fields['port'])) {
                    $mappedFields[$name] = $fields['port'];
                } elseif ($dsnPart === 'user' && isset($fields['user'])) {
                    $mappedFields[$name] = $fields['user'];
                } elseif ($dsnPart === 'password' && isset($fields['password'])) {
                    $mappedFields[$name] = $fields['password'];
                } elseif ($dsnPart === 'path' && isset($fields['path'])) {
                    $mappedFields[$name] = $fields['path'];
                } elseif ($dsnPart === 'hosts' && isset($fields['nodes'])) {
                    $mappedFields[$name] = $fields['nodes'];
                } elseif ($dsnPart !== null && str_starts_with($dsnPart, 'option:')) {
                    $optKey = substr($dsnPart, 7);
                    if (isset($fields[$optKey])) {
                        $mappedFields[$name] = $fields[$optKey];
                    }
                }
            }
        }

        return ['provider' => $matchedScheme, 'fields' => $mappedFields];
    }
}
