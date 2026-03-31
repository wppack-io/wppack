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

namespace WpPack\Plugin\RedisCachePlugin\Admin;

use WpPack\Component\Cache\Adapter\AdapterDefinition;
use WpPack\Component\Cache\Adapter\AdapterFactoryInterface;
use WpPack\Component\Cache\Bridge\Apcu\ApcuAdapterFactory;
use WpPack\Component\Cache\Bridge\DynamoDb\DynamoDbAdapterFactory;
use WpPack\Component\Cache\Bridge\Memcached\MemcachedAdapterFactory;
use WpPack\Component\Cache\Bridge\Redis\Adapter\RedisAdapterFactory;
use WpPack\Component\HttpFoundation\JsonResponse;
use WpPack\Component\Rest\AbstractRestController;
use WpPack\Component\Rest\Attribute\RestRoute;
use WpPack\Component\Rest\HttpMethod;
use WpPack\Component\Role\Attribute\IsGranted;
use WpPack\Plugin\RedisCachePlugin\Configuration\RedisCacheConfiguration;

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

        if (\defined('WPPACK_CACHE_DSN')) {
            $source = 'constant';
            $dsn = RedisCacheConfiguration::MASKED_VALUE;
        } elseif (isset($_ENV['WPPACK_CACHE_DSN']) || getenv('WPPACK_CACHE_DSN') !== false) {
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

        return [
            'dsn' => $maskedDsn,
            'provider' => $saved['provider'] ?? '',
            'fields' => $saved['fields'] ?? [],
            'source' => $source,
            'readonly' => $source === 'constant',
            'definitions' => $definitions,
            'awsRegion' => $awsRegion,
            'globalOptions' => $globalOptions,
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
            $fields[] = $f;
        }

        return [
            'scheme' => $def->scheme,
            'label' => $def->label,
            'fields' => $fields,
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    private function persistOptions(array $input): void
    {
        if (\defined('WPPACK_CACHE_DSN')) {
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
}
