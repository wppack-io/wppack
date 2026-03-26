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
        public string $spAcsUrl = '',
        public string $spSloUrl = '',
        public string $spNameIdFormat = 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress',
        public bool $strict = true,
        public bool $debug = false,
        public bool $wantAssertionsSigned = true,
        public bool $allowRepeatAttributeName = false,
        public bool $autoProvision = false,
        public string $defaultRole = 'subscriber',
        public ?string $roleAttribute = null,
        public ?array $roleMapping = null,
        public bool $addUserToBlog = true,
        public string $metadataPath = '/saml/metadata',
        public string $acsPath = '/saml/acs',
        public string $sloPath = '/saml/slo',
    ) {}

    public static function fromEnvironment(): self
    {
        return new self(
            idpEntityId: self::requireEnv('SAML_IDP_ENTITY_ID'),
            idpSsoUrl: self::requireEnv('SAML_IDP_SSO_URL'),
            idpX509Cert: self::loadCertificate(),
            idpSloUrl: self::getEnv('SAML_IDP_SLO_URL'),
            idpCertFingerprint: self::getEnv('SAML_IDP_CERT_FINGERPRINT'),
            spEntityId: self::getEnv('SAML_SP_ENTITY_ID') ?? '',
            spAcsUrl: self::getEnv('SAML_SP_ACS_URL') ?? '',
            spSloUrl: self::getEnv('SAML_SP_SLO_URL') ?? '',
            spNameIdFormat: self::resolveNameIdFormat(self::getEnv('SAML_SP_NAMEID_FORMAT') ?? 'emailAddress'),
            strict: self::getBool('SAML_STRICT', true),
            debug: self::getBool('SAML_DEBUG', false),
            wantAssertionsSigned: self::getBool('SAML_WANT_ASSERTIONS_SIGNED', true),
            allowRepeatAttributeName: self::getBool('SAML_ALLOW_REPEAT_ATTRIBUTE_NAME', false),
            autoProvision: self::getBool('SAML_AUTO_PROVISION', false),
            defaultRole: self::getEnv('SAML_DEFAULT_ROLE') ?? 'subscriber',
            roleAttribute: self::getEnv('SAML_ROLE_ATTRIBUTE'),
            roleMapping: self::loadRoleMapping(),
            addUserToBlog: self::getBool('SAML_ADD_USER_TO_BLOG', true),
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

        /** @var array<string, string>|null $decoded */
        $decoded = json_decode($json, true);

        if ($decoded === null) {
            throw new \RuntimeException('SAML_ROLE_MAPPING is not valid JSON.');
        }

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
