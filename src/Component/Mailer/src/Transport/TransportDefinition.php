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

namespace WPPack\Component\Mailer\Transport;

/**
 * Self-describing metadata for a mail transport type.
 */
final readonly class TransportDefinition
{
    /**
     * @param list<TransportField>  $fields
     * @param list<string>          $capabilities
     */
    public function __construct(
        public string $scheme,
        public string $label,
        public array $fields = [],
        public array $capabilities = [],
    ) {}

    /**
     * Build a DSN string from field values using dsnPart mappings.
     *
     * @param array<string, string> $values
     */
    public function buildDsn(array $values): string
    {
        $user = '';
        $password = '';
        $host = 'default';
        $port = null;
        $options = [];

        foreach ($this->fields as $field) {
            $value = $values[$field->name] ?? $field->default ?? '';
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
            } elseif (str_starts_with($field->dsnPart, 'option:')) {
                $options[substr($field->dsnPart, 7)] = $value;
            }
        }

        $auth = '';
        if ($user !== '' || $password !== '') {
            $auth = urlencode($user) . ($password !== '' ? ':' . urlencode($password) : '') . '@';
        }

        $portStr = $port !== null ? ':' . $port : '';
        $query = $options !== [] ? '?' . http_build_query($options, '', '&', \PHP_QUERY_RFC3986) : '';

        return $this->scheme . '://' . $auth . $host . $portStr . $query;
    }
}
