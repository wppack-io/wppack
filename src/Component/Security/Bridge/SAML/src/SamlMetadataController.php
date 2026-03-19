<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Bridge\SAML;

use OneLogin\Saml2\Settings;
use WpPack\Component\Security\Bridge\SAML\Configuration\SamlConfiguration;

final class SamlMetadataController
{
    public function __construct(
        private readonly SamlConfiguration $configuration,
    ) {}

    public function getMetadataXml(): string
    {
        $settings = new Settings($this->configuration->toOneLoginArray(), true);
        $metadata = $settings->getSPMetadata();
        $errors = $settings->validateMetadata($metadata);

        // @codeCoverageIgnoreStart
        if ($errors !== []) {
            throw new \RuntimeException(\sprintf(
                'Invalid SP metadata: %s',
                implode(', ', $errors),
            ));
        }
        // @codeCoverageIgnoreEnd

        return $metadata;
    }

    /**
     * @codeCoverageIgnore
     */
    public function serve(): never
    {
        header('Content-Type: application/xml');
        echo $this->getMetadataXml();

        exit;
    }
}
