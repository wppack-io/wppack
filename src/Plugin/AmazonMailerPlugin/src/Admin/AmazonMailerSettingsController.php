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

namespace WpPack\Plugin\AmazonMailerPlugin\Admin;

use WpPack\Component\HttpFoundation\JsonResponse;
use WpPack\Component\Mailer\Bridge\Amazon\Transport\SesTransportFactory;
use WpPack\Component\Mailer\Bridge\Azure\Transport\AzureTransportFactory;
use WpPack\Component\Mailer\Bridge\SendGrid\Transport\SendGridTransportFactory;
use WpPack\Component\Mailer\Transport\NativeTransportFactory;
use WpPack\Component\Mailer\Transport\TransportDefinition;
use WpPack\Component\Mailer\Transport\TransportFactoryInterface;
use WpPack\Component\Rest\AbstractRestController;
use WpPack\Component\Rest\Attribute\RestRoute;
use WpPack\Component\Rest\HttpMethod;
use WpPack\Component\Role\Attribute\IsGranted;
use WpPack\Plugin\AmazonMailerPlugin\Configuration\AmazonMailerConfiguration;

#[RestRoute(namespace: 'wppack/v1/mailer')]
#[IsGranted('manage_options')]
final class AmazonMailerSettingsController extends AbstractRestController
{
    /** @var list<class-string<TransportFactoryInterface>> */
    private const FACTORIES = [
        SesTransportFactory::class,
        AzureTransportFactory::class,
        SendGridTransportFactory::class,
        NativeTransportFactory::class, // SMTP and native always last
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
    public function sendTestEmail(): JsonResponse
    {
        $to = get_option('admin_email');

        $result = wp_mail(
            $to,
            'WpPack Mailer Test',
            'This is a test email sent from WpPack Mailer settings.',
        );

        return $this->json(['success' => $result, 'to' => $to]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildResponse(): array
    {
        $raw = get_option(AmazonMailerConfiguration::OPTION_NAME, []);
        $saved = \is_array($raw) ? $raw : [];

        $source = 'default';
        $dsn = '';

        if (\defined('MAILER_DSN')) {
            $source = 'constant';
            $dsn = AmazonMailerConfiguration::MASKED_VALUE;
        } elseif (isset($_ENV['MAILER_DSN']) || getenv('MAILER_DSN') !== false) {
            $source = 'constant';
            $dsn = AmazonMailerConfiguration::MASKED_VALUE;
        } elseif (isset($saved['dsn']) && $saved['dsn'] !== '') {
            $source = 'option';
            $dsn = $saved['dsn'];
        }

        // Mask DSN password part for option-sourced values
        $maskedDsn = $dsn;
        if ($source === 'option' && $dsn !== '') {
            $maskedDsn = preg_replace('/:([^@]+)@/', ':' . AmazonMailerConfiguration::MASKED_VALUE . '@', $dsn) ?? $dsn;
        }

        // Collect available transport definitions
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
                ['name' => 'dsn', 'label' => 'DSN', 'type' => 'text', 'required' => true, 'default' => null, 'help' => 'e.g., ses+api://KEY:SECRET@default?region=us-east-1'],
            ],
        ];

        // Suppression list
        $suppressionRaw = get_option('wppack_ses_suppression_list', '[]');
        $suppression = json_decode(\is_string($suppressionRaw) ? $suppressionRaw : '[]', true);

        return [
            'dsn' => $maskedDsn,
            'provider' => $saved['provider'] ?? '',
            'fields' => $saved['fields'] ?? [],
            'source' => $source,
            'readonly' => $source === 'constant',
            'definitions' => $definitions,
            'suppression' => \is_array($suppression) ? $suppression : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeDefinition(TransportDefinition $def): array
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
        if (\defined('MAILER_DSN')) {
            return;
        }

        $raw = get_option(AmazonMailerConfiguration::OPTION_NAME, []);
        $saved = \is_array($raw) ? $raw : [];

        $provider = isset($input['provider']) && \is_string($input['provider']) ? $input['provider'] : '';
        $fields = isset($input['fields']) && \is_array($input['fields']) ? $input['fields'] : [];

        if ($provider === 'dsn') {
            $dsn = isset($fields['dsn']) && \is_string($fields['dsn']) ? $fields['dsn'] : '';
            // Skip if masked
            if ($dsn !== AmazonMailerConfiguration::MASKED_VALUE) {
                $saved['dsn'] = $dsn;
            }
        } elseif ($provider !== '') {
            // Build DSN from definition + fields
            $definition = $this->findDefinition($provider);
            if ($definition !== null) {
                // Restore masked passwords from existing saved fields
                foreach ($definition->fields as $field) {
                    if ($field->type === 'password' && isset($fields[$field->name]) && $fields[$field->name] === AmazonMailerConfiguration::MASKED_VALUE) {
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

        update_option(AmazonMailerConfiguration::OPTION_NAME, $saved);
    }

    private function findDefinition(string $scheme): ?TransportDefinition
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
