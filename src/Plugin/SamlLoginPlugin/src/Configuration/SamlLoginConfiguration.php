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

namespace WpPack\Plugin\SamlLoginPlugin\Configuration;

final readonly class SamlLoginConfiguration
{
    private const NAMEID_FORMAT_MAP = [
        'emailAddress' => 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress',
        'persistent' => 'urn:oasis:names:tc:SAML:2.0:nameid-format:persistent',
        'transient' => 'urn:oasis:names:tc:SAML:2.0:nameid-format:transient',
        'unspecified' => 'urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified',
    ];

    /**
     * @param array<string, string>|null $roleMapping
     */
    public function __construct(
        public string $idpEntityId,
        public string $idpSsoUrl,
        #[\SensitiveParameter]
        public string $idpX509Cert,
        public ?string $idpSloUrl = null,
        public ?string $idpCertFingerprint = null,
        public string $spEntityId = '',
        public string $spNameIdFormat = 'urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified',
        public bool $strict = true,
        public bool $debug = false,
        public bool $wantAssertionsSigned = true,
        public bool $allowRepeatAttributeName = false,
        public bool $autoProvision = false,
        public string $defaultRole = 'subscriber',
        public string $emailAttribute = 'email',
        public ?string $firstNameAttribute = 'firstName',
        public ?string $lastNameAttribute = 'lastName',
        public ?string $displayNameAttribute = 'displayName',
        public ?string $roleAttribute = null,
        public ?array $roleMapping = null,
        public bool $addUserToBlog = true,
        public bool $ssoOnly = false,
        public string $metadataPath = '/saml/metadata',
        public string $acsPath = '/saml/acs',
        public string $sloPath = '/saml/slo',
    ) {}

    private const OPTION_NAME = 'wppack_saml_login';

    /**
     * Map of constructor parameter names to environment variable names.
     *
     * @var array<string, string>
     */
    public const ENV_MAP = [
        'idpEntityId' => 'SAML_IDP_ENTITY_ID',
        'idpSsoUrl' => 'SAML_IDP_SSO_URL',
        'idpX509Cert' => 'SAML_IDP_X509_CERT',
        'idpSloUrl' => 'SAML_IDP_SLO_URL',
        'idpCertFingerprint' => 'SAML_IDP_CERT_FINGERPRINT',
        'spEntityId' => 'SAML_SP_ENTITY_ID',
        'spNameIdFormat' => 'SAML_SP_NAMEID_FORMAT',
        'strict' => 'SAML_STRICT',
        'debug' => 'SAML_DEBUG',
        'wantAssertionsSigned' => 'SAML_WANT_ASSERTIONS_SIGNED',
        'allowRepeatAttributeName' => 'SAML_ALLOW_REPEAT_ATTRIBUTE_NAME',
        'autoProvision' => 'SAML_AUTO_PROVISION',
        'defaultRole' => 'SAML_DEFAULT_ROLE',
        'emailAttribute' => 'SAML_EMAIL_ATTRIBUTE',
        'firstNameAttribute' => 'SAML_FIRST_NAME_ATTRIBUTE',
        'lastNameAttribute' => 'SAML_LAST_NAME_ATTRIBUTE',
        'displayNameAttribute' => 'SAML_DISPLAY_NAME_ATTRIBUTE',
        'roleAttribute' => 'SAML_ROLE_ATTRIBUTE',
        'roleMapping' => 'SAML_ROLE_MAPPING',
        'addUserToBlog' => 'SAML_ADD_USER_TO_BLOG',
        'ssoOnly' => 'SAML_SSO_ONLY',
        'metadataPath' => 'SAML_METADATA_PATH',
        'acsPath' => 'SAML_ACS_PATH',
        'sloPath' => 'SAML_SLO_PATH',
    ];

    /**
     * Create from environment variables/constants with wp_options fallback.
     *
     * Priority: constant > wp_options > env > default
     */
    public static function fromEnvironmentOrOptions(): self
    {
        $raw = get_option(self::OPTION_NAME, []);
        $options = \is_array($raw) ? $raw : [];

        return new self(
            idpEntityId: self::resolveString('SAML_IDP_ENTITY_ID', $options, '') ?: self::getEnv('SAML_IDP_ENTITY_ID') ?? '',
            idpSsoUrl: self::resolveString('SAML_IDP_SSO_URL', $options, '') ?: self::getEnv('SAML_IDP_SSO_URL') ?? '',
            idpX509Cert: self::resolveString('SAML_IDP_X509_CERT', $options, '') ?: self::loadCertificateOrEmpty(),
            idpSloUrl: self::resolveNullableString('SAML_IDP_SLO_URL', $options),
            idpCertFingerprint: self::resolveNullableString('SAML_IDP_CERT_FINGERPRINT', $options),
            spEntityId: self::resolveString('SAML_SP_ENTITY_ID', $options, ''),
            spNameIdFormat: self::resolveNameIdFormat(self::resolveString('SAML_SP_NAMEID_FORMAT', $options, 'unspecified')),
            strict: self::resolveBool('SAML_STRICT', $options, true),
            debug: self::resolveBool('SAML_DEBUG', $options, false),
            wantAssertionsSigned: self::resolveBool('SAML_WANT_ASSERTIONS_SIGNED', $options, true),
            allowRepeatAttributeName: self::resolveBool('SAML_ALLOW_REPEAT_ATTRIBUTE_NAME', $options, false),
            autoProvision: self::resolveBool('SAML_AUTO_PROVISION', $options, false),
            defaultRole: self::resolveString('SAML_DEFAULT_ROLE', $options, 'subscriber'),
            emailAttribute: self::resolveString('SAML_EMAIL_ATTRIBUTE', $options, 'email'),
            firstNameAttribute: self::resolveNullableString('SAML_FIRST_NAME_ATTRIBUTE', $options) ?? 'firstName',
            lastNameAttribute: self::resolveNullableString('SAML_LAST_NAME_ATTRIBUTE', $options) ?? 'lastName',
            displayNameAttribute: self::resolveNullableString('SAML_DISPLAY_NAME_ATTRIBUTE', $options) ?? 'displayName',
            roleAttribute: self::resolveNullableString('SAML_ROLE_ATTRIBUTE', $options),
            roleMapping: self::resolveRoleMapping($options),
            addUserToBlog: self::resolveBool('SAML_ADD_USER_TO_BLOG', $options, true),
            ssoOnly: self::resolveBool('SAML_SSO_ONLY', $options, false),
            metadataPath: self::resolveString('SAML_METADATA_PATH', $options, '/saml/metadata'),
            acsPath: self::resolveString('SAML_ACS_PATH', $options, '/saml/acs'),
            sloPath: self::resolveString('SAML_SLO_PATH', $options, '/saml/slo'),
        );
    }

    public static function fromEnvironment(): self
    {
        return new self(
            idpEntityId: self::requireEnv('SAML_IDP_ENTITY_ID'),
            idpSsoUrl: self::requireEnv('SAML_IDP_SSO_URL'),
            idpX509Cert: self::loadCertificate(),
            idpSloUrl: self::getEnv('SAML_IDP_SLO_URL'),
            idpCertFingerprint: self::getEnv('SAML_IDP_CERT_FINGERPRINT'),
            spEntityId: self::getEnv('SAML_SP_ENTITY_ID') ?? '',
            spNameIdFormat: self::resolveNameIdFormat(self::getEnv('SAML_SP_NAMEID_FORMAT') ?? 'unspecified'),
            strict: self::getBool('SAML_STRICT', true),
            debug: self::getBool('SAML_DEBUG', false),
            wantAssertionsSigned: self::getBool('SAML_WANT_ASSERTIONS_SIGNED', true),
            allowRepeatAttributeName: self::getBool('SAML_ALLOW_REPEAT_ATTRIBUTE_NAME', false),
            autoProvision: self::getBool('SAML_AUTO_PROVISION', false),
            defaultRole: self::getEnv('SAML_DEFAULT_ROLE') ?? 'subscriber',
            emailAttribute: self::getEnv('SAML_EMAIL_ATTRIBUTE') ?? 'email',
            firstNameAttribute: self::getEnv('SAML_FIRST_NAME_ATTRIBUTE') ?? 'firstName',
            lastNameAttribute: self::getEnv('SAML_LAST_NAME_ATTRIBUTE') ?? 'lastName',
            displayNameAttribute: self::getEnv('SAML_DISPLAY_NAME_ATTRIBUTE') ?? 'displayName',
            roleAttribute: self::getEnv('SAML_ROLE_ATTRIBUTE'),
            roleMapping: self::loadRoleMapping(),
            addUserToBlog: self::getBool('SAML_ADD_USER_TO_BLOG', true),
            ssoOnly: self::getBool('SAML_SSO_ONLY', false),
            metadataPath: self::getEnv('SAML_METADATA_PATH') ?? '/saml/metadata',
            acsPath: self::getEnv('SAML_ACS_PATH') ?? '/saml/acs',
            sloPath: self::getEnv('SAML_SLO_PATH') ?? '/saml/slo',
        );
    }

    private static function loadCertificate(): string
    {
        // File path takes priority (recommended for production)
        $certFile = self::getEnv('SAML_IDP_X509_CERT_FILE');

        if ($certFile !== null) {
            if (!file_exists($certFile) || !is_readable($certFile)) {
                throw new \RuntimeException(\sprintf(
                    'Certificate file "%s" does not exist or is not readable.',
                    $certFile,
                ));
            }

            $content = file_get_contents($certFile);

            if ($content === false) {
                // @codeCoverageIgnoreStart
                throw new \RuntimeException(\sprintf('Failed to read certificate file "%s".', $certFile));
                // @codeCoverageIgnoreEnd
            }

            return $content;
        }

        // Direct certificate value (convert literal \n to newlines)
        $cert = self::requireEnv('SAML_IDP_X509_CERT');

        return str_replace('\\n', "\n", $cert);
    }

    /**
     * @return array<string, string>|null
     */
    private static function loadRoleMapping(): ?array
    {
        $json = self::getEnv('SAML_ROLE_MAPPING');

        if ($json === null) {
            return null;
        }

        $decoded = json_decode($json, true);

        if (!\is_array($decoded)) {
            throw new \RuntimeException('SAML_ROLE_MAPPING is not valid JSON.');
        }

        foreach ($decoded as $key => $value) {
            if (!\is_string($key) || !\is_string($value)) {
                throw new \RuntimeException('SAML_ROLE_MAPPING must be a JSON object mapping strings to strings.');
            }
        }

        /** @var array<string, string> $decoded */
        return $decoded;
    }

    private static function resolveNameIdFormat(string $format): string
    {
        return self::NAMEID_FORMAT_MAP[$format] ?? $format;
    }

    private static function requireEnv(string $name): string
    {
        $value = self::getEnv($name);

        if ($value === null) {
            throw new \RuntimeException(\sprintf('Required environment variable "%s" is not set.', $name));
        }

        return $value;
    }

    private static function getEnv(string $name): ?string
    {
        if (\defined($name)) {
            $value = \constant($name);

            return \is_string($value) && $value !== '' ? $value : null;
        }

        $value = $_ENV[$name] ?? false;

        if ($value !== false && $value !== '') {
            return $value;
        }

        $value = getenv($name);

        return ($value !== false && $value !== '') ? $value : null;
    }

    /**
     * Resolve string: constant > option > env > default.
     *
     * @param array<string, mixed> $options
     */
    private static function resolveString(string $envName, array $options, string $default): string
    {
        if (\defined($envName)) {
            $v = \constant($envName);

            return \is_string($v) && $v !== '' ? $v : $default;
        }

        $paramName = self::envToParam($envName);
        if (isset($options[$paramName]) && \is_string($options[$paramName]) && $options[$paramName] !== '') {
            return $options[$paramName];
        }

        return self::getEnv($envName) ?? $default;
    }

    /**
     * Resolve nullable string: constant > option > env > null.
     *
     * @param array<string, mixed> $options
     */
    private static function resolveNullableString(string $envName, array $options): ?string
    {
        if (\defined($envName)) {
            $v = \constant($envName);

            return \is_string($v) && $v !== '' ? $v : null;
        }

        $paramName = self::envToParam($envName);
        if (isset($options[$paramName]) && \is_string($options[$paramName]) && $options[$paramName] !== '') {
            return $options[$paramName];
        }

        return self::getEnv($envName);
    }

    /**
     * Resolve bool: constant > option > env > default.
     *
     * @param array<string, mixed> $options
     */
    private static function resolveBool(string $envName, array $options, bool $default): bool
    {
        if (\defined($envName)) {
            return (bool) \constant($envName);
        }

        $paramName = self::envToParam($envName);
        if (isset($options[$paramName])) {
            return (bool) $options[$paramName];
        }

        return self::getBool($envName, $default);
    }

    /**
     * Resolve role mapping: constant > option > env > null.
     *
     * @param array<string, mixed> $options
     * @return array<string, string>|null
     */
    private static function resolveRoleMapping(array $options): ?array
    {
        if (\defined('SAML_ROLE_MAPPING')) {
            $json = \constant('SAML_ROLE_MAPPING');
            if (\is_string($json)) {
                $decoded = json_decode($json, true);

                return \is_array($decoded) ? $decoded : null;
            }

            return null;
        }

        if (isset($options['roleMapping'])) {
            if (\is_array($options['roleMapping'])) {
                return $options['roleMapping'];
            }
            if (\is_string($options['roleMapping'])) {
                $decoded = json_decode($options['roleMapping'], true);

                return \is_array($decoded) ? $decoded : null;
            }
        }

        return self::loadRoleMapping();
    }

    private static function loadCertificateOrEmpty(): string
    {
        try {
            return self::loadCertificate();
        } catch (\RuntimeException) {
            return '';
        }
    }

    /**
     * Convert environment variable name to parameter name.
     * e.g., SAML_IDP_ENTITY_ID → idpEntityId
     */
    private static function envToParam(string $envName): string
    {
        static $flipped = null;
        $flipped ??= array_flip(self::ENV_MAP);

        return $flipped[$envName] ?? $envName;
    }

    private static function getBool(string $name, bool $default): bool
    {
        if (\defined($name)) {
            return (bool) \constant($name);
        }

        $value = $_ENV[$name] ?? false;

        if ($value === false) {
            $value = getenv($name);
        }

        if ($value === false) {
            return $default;
        }

        return \in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }
}
