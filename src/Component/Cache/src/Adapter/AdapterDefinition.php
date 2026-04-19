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

namespace WPPack\Component\Cache\Adapter;

/**
 * Self-describing metadata for a cache adapter type.
 */
final readonly class AdapterDefinition
{
    /**
     * @param list<AdapterField>         $fields
     * @param array<string, string>      $extraOptions Extra query params merged into the DSN (e.g., ['redis_cluster' => '1'])
     * @param list<string>               $capabilities Supported global options (e.g., 'compression', 'serializer', 'clientLibrary', 'hashAlloptions', 'asyncFlush')
     */
    public function __construct(
        public string $scheme,
        public string $label,
        public array $fields = [],
        public ?string $dsnScheme = null,
        public array $extraOptions = [],
        public array $capabilities = [],
    ) {}

    /**
     * Build a DSN string from field values using dsnPart mappings.
     *
     * @param array<string, string> $values
     */
    public function buildDsn(array $values): string
    {
        $scheme = $this->dsnScheme ?? $this->scheme;
        $user = '';
        $password = '';
        $host = null;
        $port = null;
        $path = null;
        $hosts = [];
        $options = $this->extraOptions;

        foreach ($this->fields as $field) {
            /** @var mixed $raw */
            $raw = $values[$field->name] ?? $field->default ?? '';
            // Boolean fields: convert true/false to '1'/''
            if ($raw === true) {
                $value = '1';
            } elseif ($raw === false || $raw === '') {
                $value = '';
            } else {
                $value = (string) $raw;
            }
            if ($value === '' || $field->dsnPart === null) {
                continue;
            }

            if ($field->dsnPart === 'user') {
                $user = $value;
            } elseif ($field->dsnPart === 'password') {
                $password = $value;
            } elseif ($field->dsnPart === 'host') {
                $host = $value;
            } elseif ($field->dsnPart === 'port') {
                $port = (int) $value;
            } elseif ($field->dsnPart === 'path') {
                $path = $value;
            } elseif ($field->dsnPart === 'hosts') {
                $hosts = array_filter(
                    array_map('trim', explode("\n", $value)),
                    static fn(string $line): bool => $line !== '',
                );
            } elseif (str_starts_with($field->dsnPart, 'option:')) {
                $options[substr($field->dsnPart, 7)] = $value;
            }
        }

        $auth = '';
        if ($user !== '' || $password !== '') {
            $auth = urlencode($user) . ($password !== '' ? ':' . urlencode($password) : '') . '@';
        }

        // Multi-host format: scheme://[auth]?host[node1:port]&host[node2:port]&options
        if ($hosts !== []) {
            $queryParts = [];
            foreach ($hosts as $hostEntry) {
                $queryParts[] = 'host[' . urlencode($hostEntry) . ']';
            }
            foreach ($options as $key => $optValue) {
                $queryParts[] = urlencode($key) . '=' . urlencode($optValue);
            }

            return $scheme . '://' . $auth . '?' . implode('&', $queryParts);
        }

        $hostStr = $host ?? 'default';
        $portStr = $port !== null ? ':' . $port : '';
        $pathStr = $path !== null ? '/' . ltrim($path, '/') : '';
        $query = $options !== [] ? '?' . http_build_query($options, '', '&', \PHP_QUERY_RFC3986) : '';

        return $scheme . '://' . $auth . $hostStr . $portStr . $pathStr . $query;
    }
}
